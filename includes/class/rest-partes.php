<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_Partes {
    
    public static function register(){
        // PARTES: obtener registro por clase/semana
        register_rest_route('liga-creus/v1', '/partes', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required'=>true, 'type'=>'string'],
                'year'  => ['required'=>true, 'type'=>'integer'],
                'week'  => ['required'=>true, 'type'=>'integer'],
            ],
        ]);

        // PARTES: crear/actualizar registro por clase/semana
        register_rest_route('liga-creus/v1', '/partes', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase'  => ['required'=>true, 'type'=>'string'],
                'year'   => ['required'=>true, 'type'=>'integer'],
                'week'   => ['required'=>true, 'type'=>'integer'],
                'partes' => ['required'=>false, 'type'=>'integer'],
                'notas'  => ['required'=>false, 'type'=>'string'],
            ],
        ]);
    }

    /**
     * Obtiene datos de partes para una clase y semana específica
     */
    public static function get(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $clase = sanitize_text_field($req['clase']);
        $year  = intval($req['year']);
        $week  = intval($req['week']);

        // Fila concreta
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}lc_partes WHERE clase=%s AND iso_year=%d AND iso_week=%d",
                $clase, $year, $week),
            ARRAY_A
        );

        // Último registro (para mostrar el acumulado si quieres)
        $latest = $wpdb->get_row(
            $wpdb->prepare("SELECT partes, iso_year, iso_week
                            FROM {$p}lc_partes
                            WHERE clase=%s
                            ORDER BY iso_year DESC, iso_week DESC
                            LIMIT 1", $clase)
        );
        $current = $latest ? intval($latest->partes) : 0;

        // Semana anterior (por si quieres pre-rellenar)
        $prev = $wpdb->get_row(
            $wpdb->prepare("SELECT partes FROM {$p}lc_partes
                            WHERE clase=%s AND (iso_year < %d OR (iso_year=%d AND iso_week < %d))
                            ORDER BY iso_year DESC, iso_week DESC
                            LIMIT 1", $clase, $year, $year, $week)
        );
        $prev_val = $prev ? intval($prev->partes) : 0;

        return ['ok'=>true, 'data'=>$row, 'current'=>$current, 'prev'=>$prev_val];
    }

    /**
     * Crea o actualiza datos de partes
     */
    public static function upsert(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $clase  = sanitize_text_field($req['clase']);
        $year   = intval($req['year']);
        $week   = intval($req['week']);
        $partes = intval($req->get_param('partes'));
        $notas  = sanitize_textarea_field($req->get_param('notas'));

        $data = [
            'clase'     => $clase,
            'iso_year'  => $year,
            'iso_week'  => $week,
            'partes'    => $partes,
            'notas'     => $notas,
            'created_by'=> LC_Helpers::get_current_user_email(),
        ];
        $formats = ['%s','%d','%d','%d','%s','%s'];

        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$p}lc_partes WHERE clase=%s AND iso_year=%d AND iso_week=%d LIMIT 1",
                $clase, $year, $week)
        );

        $ok = $exists
            ? $wpdb->update("{$p}lc_partes", $data, ['id'=>$exists], $formats, ['%d'])
            : $wpdb->insert("{$p}lc_partes", $data, $formats);

        if ($ok === false){
            error_log('LC partes_upsert error: '.$wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB'], 500);
        }

        // Devuelve la fila guardada para refrescar UI
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}lc_partes WHERE clase=%s AND iso_year=%d AND iso_week=%d",
                $clase, $year, $week),
            ARRAY_A
        );

        return ['ok'=>true, 'data'=>$row];
    }
}