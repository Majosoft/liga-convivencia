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
                'participantes'=> ['required'=>true, 'type'=>'string'],
                'puntos'       => ['required'=>true, 'type'=>'integer'],
                'notas'        => ['required'=>false, 'type'=>'string'],
                'clase_a'      => ['required'=>false, 'type'=>'string'],
                'clase_b'      => ['required'=>false, 'type'=>'string'],
                'ganador'      => ['required'=>false, 'type'=>'string'],
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
            $wpdb->prepare("SELECT id, actividad, participantes, puntos, clase_a, clase_b, ganador, notas
                            FROM {$p}lc_biblioteca
                            WHERE actividad=%s AND iso_year=%d AND iso_week=%d
                            ORDER BY id ASC",
                            $actividad, $year, $week), ARRAY_A
        );

        return ['ok'=>true, 'actividades'=>$rows];
    }

    /**
     * Crea o actualiza una actividad
     */
    public static function upsert(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $actividad = sanitize_text_field($req['actividad']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];
        $participantes = sanitize_text_field($req['participantes']);
        $puntos = (int)$req['puntos'];
        $notas = sanitize_textarea_field($req->get_param('notas'));
        $clase_a = sanitize_text_field($req->get_param('clase_a'));
        $clase_b = sanitize_text_field($req->get_param('clase_b'));
        $ganador = sanitize_text_field($req->get_param('ganador'));

        // Validaciones
        if(empty($participantes)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Participantes requeridos'], 400);
        }

        if($actividad === 'ajedrez') {
            if(empty($clase_a) || empty($clase_b)) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Clases requeridas para ajedrez'], 400);
            }
            if($clase_a === $clase_b) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Las clases deben ser distintas'], 400);
            }
        }

        // Determinar si es una actividad nueva o actualización
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}lc_biblioteca 
             WHERE actividad=%s AND iso_year=%d AND iso_week=%d 
             AND participantes=%s",
            $actividad, $year, $week, $participantes
        ));

        $data = [
            'actividad' => $actividad,
            'iso_year' => $year,
            'iso_week' => $week,
            'participantes' => $participantes,
            'puntos' => $puntos,
            'notas' => $notas,
            'created_by' => LC_Helpers::get_current_user_email(),
        ];

        // Añadir campos específicos del ajedrez
        if($actividad === 'ajedrez') {
            $data['clase_a'] = $clase_a;
            $data['clase_b'] = $clase_b;
            $data['ganador'] = $ganador;
        }

        $formats = ['%s', '%d', '%d', '%s', '%d', '%s', '%s'];
        if($actividad === 'ajedrez') {
            $formats = ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s'];
        }

        if($exists) {
            // Actualizar registro existente
            $result = $wpdb->update(
                "{$p}lc_biblioteca", 
                $data, 
                ['id' => $exists], 
                $formats, 
                ['%d']
            );
        } else {
            // Crear nuevo registro
            $result = $wpdb->insert("{$p}lc_biblioteca", $data, $formats);
        }

        if($result === false) {
            error_log('LC Biblioteca DB Error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB: ' . $wpdb->last_error], 500);
        }

        // Devolver lista actualizada
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/biblioteca');
        $r->set_param('actividad', $actividad);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }
}
