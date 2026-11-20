<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_Biblioteca {

    public static function register(){
        // BIBLIOTECA: listar actividades de una semana
        register_rest_route('liga-creus/v1', '/biblioteca', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'actividad' => ['required'=>true, 'type'=>'string'],
                'year'      => ['required'=>true, 'type'=>'integer'],
                'week'      => ['required'=>true, 'type'=>'integer'],
            ],
        ]);

        // BIBLIOTECA: crear/actualizar actividad
        register_rest_route('liga-creus/v1', '/biblioteca', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'actividad'    => ['required'=>true, 'type'=>'string'],
                'year'         => ['required'=>true, 'type'=>'integer'],
                'week'         => ['required'=>true, 'type'=>'integer'],
                'participantes'=> ['required'=>false, 'type'=>'string'],
                'puntos'       => ['required'=>false, 'type'=>'integer'],
                'notas'        => ['required'=>false, 'type'=>'string'],
                'clase_a'      => ['required'=>false, 'type'=>'string'],
                'clase_b'      => ['required'=>false, 'type'=>'string'],
                'ganador'      => ['required'=>false, 'type'=>'string'],
                'alumno_a'     => ['required'=>false, 'type'=>'integer'],
                'alumno_b'     => ['required'=>false, 'type'=>'integer'],
                'id'           => ['required'=>false, 'type'=>'integer'],
            ],
        ]);
    }

    /**
     * Lista actividades de una semana específica
     */
    public static function list(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $actividad = sanitize_text_field($req['actividad']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, actividad, participantes, puntos, clase_a, clase_b, alumno_a, alumno_b, ganador, notas
                            FROM {$p}lc_biblioteca
                            WHERE actividad=%s AND iso_year=%d AND iso_week=%d
                            ORDER BY id ASC",
                            $actividad, $year, $week), ARRAY_A
        );

        $alumnos = LC_Alumnos::mapa();
        foreach ($rows as &$r) {
            $r['alumno_a_nombre'] = $alumnos[(int)$r['alumno_a']]['nombre'] ?? '';
            $r['alumno_b_nombre'] = $alumnos[(int)$r['alumno_b']]['nombre'] ?? '';
        }
        unset($r);

        return ['ok'=>true, 'actividades'=>$rows];
    }

    /**
     * Crea o actualiza una actividad
     */
    public static function upsert(\WP_REST_Request $req){
        $actividad = sanitize_text_field($req['actividad']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];
        $notas = sanitize_textarea_field($req->get_param('notas'));

        $config = LC_Competiciones::get_actividad($actividad);
        if (!$config) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Esa actividad no existe en la configuración'], 400);
        }
        $modo = $config['modo'] ?? 'participantes';

        if ($modo === 'enfrentamiento' || $modo === 'alumnos') {
            return self::upsert_enfrentamiento($req, $config, $actividad, $year, $week, $notas, $modo);
        }
        return self::upsert_participantes($req, $config, $actividad, $year, $week, $notas);
    }

    private static function upsert_participantes(\WP_REST_Request $req, $config, $actividad, $year, $week, $notas){
        global $wpdb; $p = $wpdb->prefix;

        $participantes = sanitize_text_field($req->get_param('participantes'));
        $puntos = (int)$req->get_param('puntos');

        if (empty($participantes)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Participantes requeridos'], 400);
        }

        // Los puntos enviados deben corresponder a uno de los niveles configurados para la actividad.
        $niveles = $config['niveles'] ?? [];
        $puntos_validos = array_map(function($n){ return (int)$n['puntos']; }, $niveles);
        if (!empty($puntos_validos) && !in_array($puntos, $puntos_validos, true)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Valoración no válida para esta actividad'], 400);
        }

        $data = [
            'actividad' => $actividad,
            'iso_year' => $year,
            'iso_week' => $week,
            'participantes' => $participantes,
            'puntos' => $puntos,
            'notas' => $notas,
            'created_by' => LC_Helpers::get_current_user_email(),
        ];
        $formats = ['%s', '%d', '%d', '%s', '%d', '%s', '%s'];
        $id = (int)$req->get_param('id');

        if ($id > 0) {
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_biblioteca WHERE id=%d AND actividad=%s AND iso_year=%d AND iso_week=%d",
                $id, $actividad, $year, $week
            ));
            if (!$valid) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Registro no encontrado'], 404);
            }
            $result = $wpdb->update("{$p}lc_biblioteca", $data, ['id' => $id], $formats, ['%d']);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_biblioteca
                 WHERE actividad=%s AND iso_year=%d AND iso_week=%d AND participantes=%s",
                $actividad, $year, $week, $participantes
            ));
            $result = $exists
                ? $wpdb->update("{$p}lc_biblioteca", $data, ['id' => $exists], $formats, ['%d'])
                : $wpdb->insert("{$p}lc_biblioteca", $data, $formats);
        }

        if ($result === false) {
            error_log('LC Biblioteca DB Error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB: ' . $wpdb->last_error], 500);
        }

        return self::respond_list($actividad, $year, $week);
    }

    private static function upsert_enfrentamiento(\WP_REST_Request $req, $config, $actividad, $year, $week, $notas, $modo = 'enfrentamiento'){
        global $wpdb; $p = $wpdb->prefix;

        $clase_a = sanitize_text_field($req->get_param('clase_a'));
        $clase_b = sanitize_text_field($req->get_param('clase_b'));
        $ganador = sanitize_text_field($req->get_param('ganador'));
        $participantes = sanitize_text_field($req->get_param('participantes')); // informativo, no puntúa
        $alumno_a = max(0, (int)$req->get_param('alumno_a'));
        $alumno_b = max(0, (int)$req->get_param('alumno_b'));
        $por_alumnos = ($modo === 'alumnos');

        if (empty($clase_a) || empty($clase_b)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Clases requeridas'], 400);
        }
        if ($clase_a === $clase_b) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Las clases deben ser distintas'], 400);
        }

        if ($por_alumnos) {
            if (!$alumno_a || !$alumno_b) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Selecciona el alumno de cada clase'], 400);
            }
            if ($alumno_a === $alumno_b) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Los alumnos deben ser distintos'], 400);
            }
            foreach ([[$alumno_a, $clase_a], [$alumno_b, $clase_b]] as $par) {
                $alumno = LC_Alumnos::get($par[0]);
                if (!$alumno || $alumno['clase'] !== $par[1]) {
                    return new \WP_REST_Response(['ok'=>false,'error'=>'Un alumno no pertenece a la clase indicada'], 400);
                }
            }
        }
        if (!in_array($ganador, ['empate', 'clase-a', 'clase-b'], true)) {
            $ganador = 'empate';
        }

        // Normaliza el orden como en Deportes: sin esto "1ºA vs 1ºB" y "1ºB vs 1ºA"
        // en la misma semana eran dos partidas distintas y puntuaban dos veces.
        // El ganador y los alumnos viajan con su clase.
        if (strnatcmp($clase_a, $clase_b) > 0) {
            [$clase_a, $clase_b] = [$clase_b, $clase_a];
            [$alumno_a, $alumno_b] = [$alumno_b, $alumno_a];
            if ($ganador !== 'empate') $ganador = $ganador === 'clase-a' ? 'clase-b' : 'clase-a';
        }

        // No se guardan pts_a/pts_b (la tabla no tiene esas columnas): los puntos de
        // victoria/empate/derrota se calculan a partir de "ganador" en el momento de
        // construir la clasificación, con la configuración vigente de la actividad.
        $data = [
            'actividad' => $actividad,
            'iso_year' => $year,
            'iso_week' => $week,
            'participantes' => $participantes ?: wp_json_encode([]),
            'puntos' => 0, // no se usa en modo enfrentamiento
            'notas' => $notas,
            'clase_a' => $clase_a,
            'clase_b' => $clase_b,
            'alumno_a' => $alumno_a,
            'alumno_b' => $alumno_b,
            'ganador' => $ganador,
            'created_by' => LC_Helpers::get_current_user_email(),
        ];
        $formats = ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s'];
        $id = (int)$req->get_param('id');

        if ($id > 0) {
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_biblioteca WHERE id=%d AND actividad=%s AND iso_year=%d AND iso_week=%d",
                $id, $actividad, $year, $week
            ));
            if (!$valid) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Registro no encontrado'], 404);
            }
            $result = $wpdb->update("{$p}lc_biblioteca", $data, ['id' => $id], $formats, ['%d']);
        } elseif ($por_alumnos) {
            // Un registro por enfrentamiento: las mismas dos clases pueden jugar
            // varias partidas en la misma semana con alumnos distintos.
            $result = $wpdb->insert("{$p}lc_biblioteca", $data, $formats);
        } else {
            // También en orden inverso, por las partidas guardadas antes de normalizar.
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_biblioteca
                 WHERE actividad=%s AND iso_year=%d AND iso_week=%d
                   AND ((clase_a=%s AND clase_b=%s) OR (clase_a=%s AND clase_b=%s))",
                $actividad, $year, $week, $clase_a, $clase_b, $clase_b, $clase_a
            ));
            $result = $exists
                ? $wpdb->update("{$p}lc_biblioteca", $data, ['id' => $exists], $formats, ['%d'])
                : $wpdb->insert("{$p}lc_biblioteca", $data, $formats);
        }

        if ($result === false) {
            error_log('LC Biblioteca DB Error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB: ' . $wpdb->last_error], 500);
        }

        return self::respond_list($actividad, $year, $week);
    }

    private static function respond_list($actividad, $year, $week){
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/biblioteca');
        $r->set_param('actividad', $actividad);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }
}
