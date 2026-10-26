<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Partes de conducta: una fila por parte, asociada al alumno que lo recibe o,
 * con alumno_id = 0, a toda la clase.
 *
 * Sustituye al modelo anterior, que guardaba contadores agregados por clase y
 * semana en lc_partes. Los puntos de la clase salen ahora de sumar los partes de
 * sus alumnos, así que el total por clase se sigue calculando igual de bien y
 * además se puede hacer un ranking por alumno.
 */
class LC_REST_Partes {

    const TIPOS = ['positivo', 'leve', 'grave'];

    private static function tabla() {
        global $wpdb;
        return $wpdb->prefix . 'lc_partes_alumnos';
    }

    /** Puntos de un tipo de parte, según Competición > Partes. */
    private static function puntos_de($tipo) {
        $pts = LC_Competiciones::get_partes();
        return isset($pts[$tipo]) ? (int) $pts[$tipo] : 0;
    }

    public static function register(){
        register_rest_route('liga-creus/v1', '/partes', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required'=>true, 'type'=>'string'],
                'year'  => ['required'=>true, 'type'=>'integer'],
                'week'  => ['required'=>true, 'type'=>'integer'],
            ],
        ]);

        register_rest_route('liga-creus/v1', '/partes', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'create'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase'     => ['required'=>true,  'type'=>'string'],
                'year'      => ['required'=>true,  'type'=>'integer'],
                'week'      => ['required'=>true,  'type'=>'integer'],
                'alumno_id' => ['required'=>false, 'type'=>'integer', 'default'=>0],
                'tipo'      => ['required'=>true,  'type'=>'string'],
                'notas'     => ['required'=>false, 'type'=>'string'],
            ],
        ]);

        register_rest_route('liga-creus/v1', '/partes', [
            'methods'  => 'DELETE',
            'callback' => [__CLASS__, 'delete'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'id' => ['required'=>true, 'type'=>'integer'],
            ],
        ]);
    }

    /**
     * Partes de una clase en una semana, con el nombre del alumno resuelto y el
     * resumen de puntos, para que el formulario no tenga que recalcularlo.
     */
    public static function list(\WP_REST_Request $req){
        global $wpdb;
        $clase = sanitize_text_field($req['clase']);
        $year  = (int) $req['year'];
        $week  = (int) $req['week'];
        $t = self::tabla();

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, alumno_id, tipo, notas
             FROM {$t}
             WHERE clase=%s AND iso_year=%d AND iso_week=%d
             ORDER BY id ASC",
            $clase, $year, $week
        ), ARRAY_A) ?: [];

        $alumnos = LC_Alumnos::mapa();
        $puntos = 0;
        $resumen = array_fill_keys(self::TIPOS, 0);

        foreach ($rows as &$r) {
            $r['id'] = (int) $r['id'];
            $r['alumno_id'] = (int) $r['alumno_id'];
            // El alumno pudo borrarse después de registrar el parte: el parte se
            // conserva y sigue puntuando para la clase, solo pierde el nombre.
            if (!$r['alumno_id']) {
                $r['alumno'] = 'Toda la clase';
            } else {
                $r['alumno'] = $alumnos[$r['alumno_id']]['nombre'] ?? 'Alumno eliminado';
            }
            $r['puntos'] = self::puntos_de($r['tipo']);
            $puntos += $r['puntos'];
            if (isset($resumen[$r['tipo']])) $resumen[$r['tipo']]++;
        }
        unset($r);

        return ['ok'=>true, 'partes'=>$rows, 'resumen'=>$resumen, 'puntos'=>$puntos];
    }

    public static function create(\WP_REST_Request $req){
        global $wpdb;
        $clase     = sanitize_text_field($req->get_param('clase'));
        $year      = (int) $req->get_param('year');
        $week      = (int) $req->get_param('week');
        $alumno_id = (int) $req->get_param('alumno_id');
        $tipo      = sanitize_text_field($req->get_param('tipo'));
        $notas     = sanitize_textarea_field($req->get_param('notas'));

        if (!in_array($tipo, self::TIPOS, true)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Tipo de parte no válido'], 400);
        }

        if ($alumno_id) {
            $alumno = LC_Alumnos::get($alumno_id);
            if (!$alumno) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'El alumno no existe'], 400);
            }
            // La clase del parte se toma del alumno, no del formulario: así sus
            // puntos no pueden acabar contando para una clase que no es la suya.
            $clase = $alumno['clase'];
        } elseif (!in_array($clase, LC_Competiciones::get_clases(), true)) {
            // Parte a toda la clase (alumno_id = 0): la clase sale del formulario.
            return new \WP_REST_Response(['ok'=>false,'error'=>'Clase no válida'], 400);
        }

        $ok = $wpdb->insert(self::tabla(), [
            'alumno_id'  => $alumno_id,
            'clase'      => $clase,
            'iso_year'   => $year,
            'iso_week'   => $week,
            'tipo'       => $tipo,
            'notas'      => $notas,
            'created_by' => LC_Helpers::get_current_user_email(),
        ], ['%d','%s','%d','%d','%s','%s','%s']);

        if ($ok === false) {
            error_log('LC partes create error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB'], 500);
        }

        return self::responder_lista($clase, $year, $week);
    }

    public static function delete(\WP_REST_Request $req){
        global $wpdb;
        $id = (int) $req->get_param('id');
        $t = self::tabla();

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT clase, iso_year, iso_week FROM {$t} WHERE id=%d", $id
        ), ARRAY_A);
        if (!$row) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Parte no encontrado'], 404);
        }

        $wpdb->delete($t, ['id' => $id], ['%d']);
        return self::responder_lista($row['clase'], (int)$row['iso_year'], (int)$row['iso_week']);
    }

    private static function responder_lista($clase, $year, $week){
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/partes');
        $r->set_param('clase', $clase);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }
}
