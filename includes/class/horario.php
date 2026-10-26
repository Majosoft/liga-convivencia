<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Horario del profesorado y gomets del Semáforo por hora de clase (1.0).
 *
 * Antes el Semáforo se registraba a mano, una vez por semana y clase (cuántos
 * verdes/amarillos/rojos). Ahora cada profesor, al entrar, ve sus clases del
 * día elegido (según su horario) y pone UN gomet por hora y clase. El recuento
 * semanal de lc_semaforo se rehace solo a partir de esos gomets, así que la
 * Clasificación sigue funcionando igual.
 *
 * Reglas (las decide el centro):
 * - Cuando en una misma hora los alumnos de una clase están repartidos en
 *   varias sesiones (desdobles, religión católica/evangélica y atención
 *   educativa, optativas que mezclan A y B), a esa clase le cuenta el gomet de
 *   MENOR valor de todas esas sesiones.
 * - Los apoyos (C1, C0, AL y el "APOYO compartido") no ponen gomet.
 * - Cada clase tiene 6 horas diarias: como mucho 30 gomets por semana.
 */
class LC_Horario {
    const OPT_PROFESORES = 'lc_profesores';  // [{iniciales, nombre, email}]

    // Sesiones que no ponen gomet: apoyos dentro del aula o fuera de ella.
    const MATERIAS_SIN_GOMET = ['C1', 'C0', 'AL'];
    const PROFESORES_SIN_GOMET = ['APOYO'];

    const COLORES = ['verde', 'amarillo', 'rojo'];
    const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
    const HORAS = 6;

    // ---------------------------------------------------------------- tablas

    public static function create_tables($wpdb, $cc, $p) {
        // grupo: tal como sale en el horario (p. ej. "3ºADI"); clases: las de la
        // Liga a las que cuenta su gomet, separadas por comas (ver cobertura()).
        dbDelta("CREATE TABLE {$p}lc_horario (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          profesor VARCHAR(20) NOT NULL,
          dia TINYINT NOT NULL,
          hora TINYINT NOT NULL,
          materia VARCHAR(20) NOT NULL DEFAULT '',
          aula VARCHAR(20) NOT NULL DEFAULT '',
          grupo VARCHAR(20) NOT NULL,
          n TINYINT NOT NULL DEFAULT 0,
          clases VARCHAR(100) NOT NULL DEFAULT '',
          cuenta TINYINT NOT NULL DEFAULT 1,
          PRIMARY KEY (id),
          KEY idx_prof_dia (profesor, dia)
        ) $cc;");

        // Un gomet por sesión y fecha: la clave única es la que garantiza "un
        // gomet por hora y clase" (cambiarlo es sobrescribir, no añadir otro).
        dbDelta("CREATE TABLE {$p}lc_gomets (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          sesion_id BIGINT UNSIGNED NOT NULL,
          fecha DATE NOT NULL,
          profesor VARCHAR(20) NOT NULL,
          color VARCHAR(10) NOT NULL,
          updated_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY uniq_sesion_fecha (sesion_id, fecha),
          KEY idx_fecha (fecha)
        ) $cc;");
    }

    /**
     * Primera carga: profesores y horario del curso 2026-27 que vienen con el
     * plugin (data/), solo si todavía no hay nada. Después se gestionan desde
     * Liga Convivencia > Profesorado.
     */
    public static function seed() {
        global $wpdb;
        if (!get_option(self::OPT_PROFESORES)) {
            $profes = [];
            foreach (self::leer_csv(LC_PATH . 'data/profesores.csv') as $f) {
                $profes[] = ['iniciales' => $f['iniciales'], 'nombre' => $f['nombre'], 'email' => ''];
            }
            update_option(self::OPT_PROFESORES, $profes);
        }
        $hay = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lc_horario");
        if ($hay === 0) {
            self::importar(file_get_contents(LC_PATH . 'data/horario.csv'));
        }
    }

    // ------------------------------------------------------------ profesores

    public static function get_profesores() {
        $p = get_option(self::OPT_PROFESORES, []);
        return is_array($p) ? $p : [];
    }

    public static function save_profesores($profes) {
        update_option(self::OPT_PROFESORES, array_values($profes));
    }

    public static function profesor_por_email($email) {
        $email = strtolower(sanitize_email($email));
        if (!$email) return null;
        foreach (self::get_profesores() as $p) {
            if ($email === strtolower($p['email'] ?? '')) return $p;
        }
        return null;
    }

    // -------------------------------------------------------------- horario

    private static function leer_csv($ruta_o_texto, $es_texto = false) {
        $texto = $es_texto ? $ruta_o_texto : (string) @file_get_contents($ruta_o_texto);
        $lineas = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $texto))));
        if (!$lineas) return [];
        $cab = array_map('trim', str_getcsv(array_shift($lineas), ",", "\"", ""));
        $filas = [];
        foreach ($lineas as $l) {
            $v = str_getcsv($l, ",", "\"", "");
            if (count($v) < count($cab)) $v = array_pad($v, count($cab), '');
            $filas[] = array_combine($cab, array_map('trim', array_slice($v, 0, count($cab))));
        }
        return $filas;
    }

    /** "3ºADI" -> "3ºA": la clase de la Liga a la que pertenece un grupo o desdoble. */
    private static function clase_base($grupo) {
        return mb_substr($grupo, 0, 3);
    }

    /**
     * Qué clases de la Liga cubre cada sesión. Partimos del grupo que pone el
     * horario, pero el horario solo escribe el PRIMER grupo de una clase que
     * mezcla varios (lo marca con un número: "4ºA 3" = 4ºA y 3 grupos más):
     * - En la hora de religión de un curso, todas las sesiones mezcladas
     *   (católica, evangélica, atención educativa) cubren a todas sus clases.
     * - Si a una hora una clase del curso no tiene sesión propia, sus alumnos
     *   están en las sesiones mezcladas de ese curso (optativas de A y B).
     * Con el horario de 2026-27 así quedan las 8 clases con sus 30 horas.
     */
    private static function cobertura(array $sesiones) {
        $ligas = LC_Competiciones::get_clases();
        foreach ($sesiones as &$s) {
            $base = self::clase_base($s['grupo']);
            $s['clases'] = in_array($base, $ligas, true) ? [$base] : [];
        }
        unset($s);

        $por_franja = [];
        foreach ($sesiones as $i => $s) {
            if (!$s['cuenta'] || !$s['clases']) continue;
            $por_franja[$s['dia'] . '-' . $s['hora'] . '-' . mb_substr($s['grupo'], 0, 1)][] = $i;
        }
        foreach ($por_franja as $clave => $idx) {
            $nivel = explode('-', $clave)[2];
            $del_nivel = array_values(array_filter($ligas, function($c) use ($nivel) { return mb_substr($c, 0, 1) === $nivel; }));
            $mezcladas = array_values(array_filter($idx, function($i) use ($sesiones) { return $sesiones[$i]['n'] > 0; }));
            $propias = [];
            foreach ($idx as $i) $propias = array_merge($propias, $sesiones[$i]['clases']);

            $hay_religion = false;
            foreach ($idx as $i) if ($sesiones[$i]['materia'] === 'RE') $hay_religion = true;

            foreach ($del_nivel as $c) {
                if (!$hay_religion && in_array($c, $propias, true)) continue;
                foreach ($mezcladas as $i) {
                    if (!in_array($c, $sesiones[$i]['clases'], true)) $sesiones[$i]['clases'][] = $c;
                }
            }
        }
        return $sesiones;
    }

    /**
     * Sustituye el horario entero por el del CSV (cabecera:
     * profesor,dia,hora,materia,aula,grupo,n). Los gomets ya puestos se
     * conservan si su sesión sigue existiendo con el mismo profesor, día, hora
     * y grupo; si no, se borran (ya no hay hora a la que asignarlos).
     * Devuelve el número de sesiones o un mensaje de error.
     */
    public static function importar($csv) {
        global $wpdb; $p = $wpdb->prefix;
        $filas = self::leer_csv($csv, true);
        if (!$filas) return 'El CSV está vacío.';
        $necesarias = ['profesor', 'dia', 'hora', 'materia', 'aula', 'grupo', 'n'];
        if (array_diff($necesarias, array_keys($filas[0]))) {
            return 'La cabecera debe ser: ' . implode(',', $necesarias);
        }

        $sesiones = [];
        foreach ($filas as $k => $f) {
            $dia = (int) $f['dia']; $hora = (int) $f['hora'];
            if ($f['profesor'] === '' || $f['grupo'] === '' || $dia < 1 || $dia > 5 || $hora < 1 || $hora > self::HORAS) {
                return 'Fila ' . ($k + 2) . ' no válida (profesor, grupo, día 1-5 y hora 1-' . self::HORAS . ' son obligatorios).';
            }
            $sesiones[] = [
                'profesor' => strtoupper(sanitize_text_field($f['profesor'])),
                'dia' => $dia, 'hora' => $hora,
                'materia' => strtoupper(sanitize_text_field($f['materia'])),
                'aula' => sanitize_text_field($f['aula']),
                'grupo' => sanitize_text_field($f['grupo']),
                'n' => (int) $f['n'],
            ];
        }
        foreach ($sesiones as &$s) {
            $s['cuenta'] = !in_array($s['materia'], self::MATERIAS_SIN_GOMET, true)
                && !in_array($s['profesor'], self::PROFESORES_SIN_GOMET, true);
        }
        unset($s);
        $sesiones = self::cobertura($sesiones);

        // Para conservar gomets: clave natural de la sesión -> id antiguo.
        $clave = function($s) { return $s['profesor'] . '|' . $s['dia'] . '|' . $s['hora'] . '|' . $s['grupo']; };
        $antiguas = [];
        foreach ($wpdb->get_results("SELECT id, profesor, dia, hora, grupo FROM {$p}lc_horario", ARRAY_A) as $a) {
            $antiguas[$clave($a)] = (int) $a['id'];
        }

        $wpdb->query('START TRANSACTION');
        $wpdb->query("DELETE FROM {$p}lc_horario");
        $mapa = [];
        foreach ($sesiones as $s) {
            $ok = $wpdb->insert("{$p}lc_horario", [
                'profesor' => $s['profesor'], 'dia' => $s['dia'], 'hora' => $s['hora'],
                'materia' => $s['materia'], 'aula' => $s['aula'], 'grupo' => $s['grupo'],
                'n' => $s['n'], 'clases' => implode(',', $s['clases']), 'cuenta' => $s['cuenta'] ? 1 : 0,
            ], ['%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%d']);
            if ($ok === false) { $wpdb->query('ROLLBACK'); return 'Error de base de datos: ' . $wpdb->last_error; }
            if (isset($antiguas[$clave($s)])) $mapa[$antiguas[$clave($s)]] = (int) $wpdb->insert_id;
        }
        // Reasigna los gomets a su sesión nueva y borra los que se quedan sin
        // ella. Los ids nuevos siempre son mayores que los viejos (el
        // autoincremento no vuelve atrás con DELETE), así que no chocan.
        if ($mapa) {
            $casos = '';
            foreach ($mapa as $viejo => $nuevo) $casos .= $wpdb->prepare(' WHEN %d THEN %d', $viejo, $nuevo);
            $viejos = implode(',', array_map('intval', array_keys($mapa)));
            $wpdb->query("UPDATE {$p}lc_gomets SET sesion_id = CASE sesion_id $casos END WHERE sesion_id IN ($viejos)");
            $wpdb->query("DELETE FROM {$p}lc_gomets WHERE sesion_id NOT IN (" . implode(',', array_map('intval', $mapa)) . ")");
        } else {
            $wpdb->query("DELETE FROM {$p}lc_gomets");
        }
        $wpdb->query('COMMIT');

        // La cobertura puede haber cambiado: rehace las semanas que tienen gomets.
        self::recalcular_todo();
        return count($sesiones);
    }

    /** Rehace el recuento de todas las clases en todas las semanas con gomets. */
    public static function recalcular_todo() {
        global $wpdb;
        $semanas = [];
        foreach ($wpdb->get_col("SELECT DISTINCT fecha FROM {$wpdb->prefix}lc_gomets") as $fecha) {
            $ts = strtotime($fecha);
            $semanas[date('o', $ts) . '-' . date('W', $ts)] = [(int) date('o', $ts), (int) date('W', $ts)];
        }
        foreach ($semanas as [$y, $w]) {
            foreach (LC_Competiciones::get_clases_activas() as $c) self::recalcular_semana($c, $y, $w);
        }
    }

    /** Horas cubiertas por clase (de 30): para comprobar el horario en Ajustes. */
    public static function comprobar_cobertura() {
        global $wpdb;
        $res = [];
        foreach (LC_Competiciones::get_clases_activas() as $c) $res[$c] = [];
        $filas = $wpdb->get_results("SELECT dia, hora, clases FROM {$wpdb->prefix}lc_horario WHERE cuenta = 1");
        foreach ($filas as $f) {
            foreach (array_filter(explode(',', $f->clases)) as $c) {
                if (isset($res[$c])) $res[$c][$f->dia . '-' . $f->hora] = true;
            }
        }
        $out = [];
        foreach ($res as $c => $franjas) {
            $huecos = [];
            foreach (self::DIAS as $d => $nombre) {
                for ($h = 1; $h <= self::HORAS; $h++) {
                    if (empty($franjas["$d-$h"])) $huecos[] = mb_substr($nombre, 0, 2) . " {$h}ª";
                }
            }
            $out[$c] = ['cubiertas' => count($franjas), 'huecos' => $huecos];
        }
        return $out;
    }

    // --------------------------------------------------------------- gomets

    /**
     * Horario completo de la semana de un profesor (también los apoyos, que no
     * ponen gomet), para que lo compruebe en su app. Solo lleva lo que se cargó
     * del horario del centro: las clases de ESO y sus desdobles.
     */
    public static function horario_semana($iniciales) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT dia, hora, materia, grupo, n, clases, cuenta FROM {$wpdb->prefix}lc_horario
             WHERE profesor = %s ORDER BY dia, hora", $iniciales), ARRAY_A);
    }

    /** Sesiones con gomet de un profesor en una fecha, con el color ya puesto. */
    public static function sesiones_del_dia($iniciales, $fecha) {
        global $wpdb; $p = $wpdb->prefix;
        $dia = (int) date('N', strtotime($fecha));
        if ($dia > 5) return [];
        return $wpdb->get_results($wpdb->prepare(
            "SELECT h.id, h.hora, h.materia, h.grupo, h.clases, g.color
             FROM {$p}lc_horario h
             LEFT JOIN {$p}lc_gomets g ON g.sesion_id = h.id AND g.fecha = %s
             WHERE h.profesor = %s AND h.dia = %d AND h.cuenta = 1 AND h.clases <> ''
             ORDER BY h.hora", $fecha, $iniciales, $dia), ARRAY_A);
    }

    /**
     * Pone (o quita, con $color vacío) el gomet de una sesión en una fecha y
     * rehace el recuento semanal de las clases afectadas. Comprueba que la
     * sesión sea del profesor y caiga en ese día de la semana.
     */
    public static function poner_gomet($iniciales, $sesion_id, $fecha, $color) {
        global $wpdb; $p = $wpdb->prefix;
        $s = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}lc_horario WHERE id = %d", $sesion_id));
        if (!$s || $s->profesor !== $iniciales || !$s->cuenta || $s->clases === '') return 'Esa clase no es tuya.';
        if ((int) date('N', strtotime($fecha)) !== (int) $s->dia) return 'Esa clase no es de ese día.';

        if ($color === '') {
            $wpdb->delete("{$p}lc_gomets", ['sesion_id' => $sesion_id, 'fecha' => $fecha], ['%d', '%s']);
        } else {
            $ok = $wpdb->replace("{$p}lc_gomets", [
                'sesion_id' => $sesion_id, 'fecha' => $fecha, 'profesor' => $iniciales,
                'color' => $color, 'updated_at' => current_time('mysql'),
            ], ['%d', '%s', '%s', '%s', '%s']);
            if ($ok === false) return 'Error de base de datos: ' . $wpdb->last_error;
        }

        $ts = strtotime($fecha);
        foreach (array_filter(explode(',', $s->clases)) as $clase) {
            self::recalcular_semana($clase, (int) date('o', $ts), (int) date('W', $ts));
        }
        return true;
    }

    /**
     * Recuento de una clase en una semana ISO: por cada día y hora, el gomet de
     * menor valor de las sesiones que cubren a la clase (si hay alguno), y se
     * guarda en lc_semaforo como antes hacía el admin a mano.
     */
    public static function recalcular_semana($clase, $year, $week) {
        global $wpdb; $p = $wpdb->prefix;
        $lunes = (new DateTime())->setISODate($year, $week, 1)->format('Y-m-d');
        $viernes = (new DateTime())->setISODate($year, $week, 5)->format('Y-m-d');
        $filas = $wpdb->get_results($wpdb->prepare(
            "SELECT g.fecha, h.hora, g.color
             FROM {$p}lc_gomets g JOIN {$p}lc_horario h ON h.id = g.sesion_id
             WHERE g.fecha BETWEEN %s AND %s AND FIND_IN_SET(%s, h.clases) > 0",
            $lunes, $viernes, $clase));

        $puntos = LC_Competiciones::get_semaforo();
        $peor = [];
        foreach ($filas as $f) {
            $k = $f->fecha . '-' . $f->hora;
            if (!isset($peor[$k]) || $puntos[$f->color] < $puntos[$peor[$k]]) $peor[$k] = $f->color;
        }
        $cuenta = array_count_values($peor) + ['verde' => 0, 'amarillo' => 0, 'rojo' => 0];
        LC_REST_Semaforo::guardar_semana($clase, $year, $week, $cuenta['verde'], $cuenta['amarillo'], $cuenta['rojo'], null, 'horario');
    }
}
