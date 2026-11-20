<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Alumnos: entidad compartida por Deportes, Biblioteca y Partes.
 *
 * Un alumno pertenece a una clase y se referencia SIEMPRE por id, no por nombre.
 * Es la diferencia importante con las clases (que se vinculan por su nombre
 * exacto): aquí renombrar a un alumno no rompe nada de su histórico.
 */
class LC_Alumnos {

    private static function tabla() {
        global $wpdb;
        return $wpdb->prefix . 'lc_alumnos';
    }

    /** Alumnos de una clase, ordenados por nombre. Solo activos salvo que se pidan todos. */
    public static function de_clase($clase, $incluir_inactivos = false) {
        global $wpdb;
        $t = self::tabla();
        $sql = $incluir_inactivos
            ? "SELECT id, nombre, clase, activo FROM {$t} WHERE clase = %s ORDER BY nombre ASC"
            : "SELECT id, nombre, clase, activo FROM {$t} WHERE clase = %s AND activo = 1 ORDER BY nombre ASC";
        return $wpdb->get_results($wpdb->prepare($sql, $clase), ARRAY_A) ?: [];
    }

    /** Todos los alumnos, para el panel de administración y los rankings. */
    public static function todos($incluir_inactivos = true) {
        global $wpdb;
        $t = self::tabla();
        $where = $incluir_inactivos ? '' : ' WHERE activo = 1';
        return $wpdb->get_results(
            "SELECT id, nombre, clase, activo FROM {$t}{$where} ORDER BY clase ASC, nombre ASC",
            ARRAY_A
        ) ?: [];
    }

    public static function get($id) {
        global $wpdb;
        $id = (int) $id;
        if ($id <= 0) return null;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, nombre, clase, activo FROM " . self::tabla() . " WHERE id = %d", $id
        ), ARRAY_A);
    }

    /**
     * Mapa id => ['nombre','clase'] de una sola consulta. Los rankings recorren
     * muchas filas y resolver cada alumno por separado dispararía las consultas.
     */
    public static function mapa() {
        $map = [];
        foreach (self::todos() as $a) {
            $map[(int) $a['id']] = ['nombre' => $a['nombre'], 'clase' => $a['clase']];
        }
        return $map;
    }

    /**
     * Devuelve el id del alumno, creándolo si no existe. Es lo que permite darlos
     * de alta al vuelo desde cualquier formulario y reutilizarlos después en los
     * demás. Devuelve 0 si los datos no son válidos.
     */
    public static function find_or_create($nombre, $clase) {
        global $wpdb;
        $nombre = trim(sanitize_text_field($nombre));
        $clase  = sanitize_text_field($clase);
        if ($nombre === '' || !self::clase_valida($clase)) return 0;

        $t = self::tabla();
        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t} WHERE nombre = %s AND clase = %s", $nombre, $clase
        ));
        if ($id) return (int) $id;

        $wpdb->insert($t, [
            'nombre'     => $nombre,
            'clase'      => $clase,
            'activo'     => 1,
            'created_by' => LC_Helpers::get_current_user_email(),
        ], ['%s', '%s', '%d', '%s']);

        return (int) $wpdb->insert_id;
    }

    /** Solo clases configuradas en Liga Convivencia > Clases. */
    public static function clase_valida($clase) {
        return $clase !== '' && in_array($clase, LC_Competiciones::get_clases(), true);
    }

    /** Cambia nombre y/o clase. El histórico se mantiene porque va por id. */
    public static function update($id, $nombre, $clase, $activo) {
        global $wpdb;
        $id = (int) $id;
        if ($id <= 0) return false;
        $nombre = trim(sanitize_text_field($nombre));
        $clase  = sanitize_text_field($clase);
        if ($nombre === '' || !self::clase_valida($clase)) return false;

        return $wpdb->update(self::tabla(), [
            'nombre' => $nombre,
            'clase'  => $clase,
            'activo' => $activo ? 1 : 0,
        ], ['id' => $id], ['%s', '%s', '%d'], ['%d']) !== false;
    }

    /**
     * Borra un alumno y sus datos personales (derecho de supresión, RGPD):
     * - Sus partes se conservan, porque los puntos son de la clase, pero
     *   pierden las notas (texto libre que puede hablar del alumno) y pasan a
     *   mostrarse como "Alumno eliminado".
     * - Donde su nombre aparece escrito (participantes de Biblioteca y de las
     *   pruebas por marca de Deportes) se sustituye por "Alumno eliminado".
     * Los puntos de la clase no cambian.
     */
    public static function delete($id) {
        global $wpdb; $p = $wpdb->prefix;
        $id = (int) $id;
        if ($id <= 0) return false;
        $alumno = self::get($id);
        if (!$alumno) return false;

        $wpdb->update("{$p}lc_partes_alumnos", ['notas' => null], ['alumno_id' => $id], ['%s'], ['%d']);

        foreach (["{$p}lc_biblioteca", "{$p}lc_deportes"] as $tabla) {
            // El nombre puede estar guardado tal cual o con los acentos escapados
            // en el JSON ("Luc\u00eda"): se buscan las dos formas.
            $escapado = trim(wp_json_encode($alumno['nombre']), '"');
            $filas = $wpdb->get_results($wpdb->prepare(
                "SELECT id, participantes FROM {$tabla} WHERE participantes LIKE %s OR participantes LIKE %s",
                '%' . $wpdb->esc_like($alumno['nombre']) . '%', '%' . $wpdb->esc_like($escapado) . '%'), ARRAY_A);
            foreach ((array) $filas as $f) {
                $lista = json_decode((string) $f['participantes'], true);
                if (!is_array($lista)) continue;
                $cambia = false;
                foreach ($lista as &$part) {
                    if (is_array($part) && ($part['nombre'] ?? '') === $alumno['nombre'] && ($part['clase'] ?? '') === $alumno['clase']) {
                        $part['nombre'] = 'Alumno eliminado';
                        $cambia = true;
                    }
                }
                unset($part);
                if ($cambia) {
                    $wpdb->update($tabla, ['participantes' => wp_json_encode($lista, JSON_UNESCAPED_UNICODE)], ['id' => (int) $f['id']], ['%s'], ['%d']);
                }
            }
        }

        return $wpdb->delete(self::tabla(), ['id' => $id], ['%d']) !== false;
    }
}
