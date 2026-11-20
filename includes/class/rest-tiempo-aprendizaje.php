<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_TiempoAprendizaje {

    public static function register(){
        register_rest_route('liga-creus/v1', '/tiempo-aprendizaje', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_record'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required' => true,  'type' => 'string'],
                'year'  => ['required' => true,  'type' => 'integer'],
                'month' => ['required' => true,  'type' => 'integer'],
            ],
        ]);

        register_rest_route('liga-creus/v1', '/tiempo-aprendizaje', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert_or_delete'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase'  => ['required' => true,  'type' => 'string'],
                'year'   => ['required' => true,  'type' => 'integer'],
                'month'  => ['required' => true,  'type' => 'integer'],
                'puntos' => ['required' => false, 'type' => 'integer'],
                'borrar' => ['required' => false, 'type' => 'boolean'],
            ],
        ]);
    }

    /**
     * Obtiene el registro de una clase en un mes concreto.
     */
    public static function get_record(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $clase = sanitize_text_field($req['clase']);
        $year  = (int)$req['year'];
        $month = (int)$req['month'];

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT puntos FROM {$p}lc_tiempo_aprendizaje
             WHERE clase=%s AND iso_year=%d AND iso_month=%d",
            $clase, $year, $month
        ), ARRAY_A);

        if ($row) {
            return ['ok' => true, 'exists' => true, 'puntos' => (int)$row['puntos']];
        }
        return ['ok' => true, 'exists' => false, 'puntos' => 0];
    }

    /**
     * Guarda los puntos (upsert) o elimina el registro del mes (borrar:true).
     */
    public static function upsert_or_delete(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $clase  = sanitize_text_field($req->get_param('clase'));
        $year   = (int)$req->get_param('year');
        $month  = (int)$req->get_param('month');
        $borrar = (bool)$req->get_param('borrar');

        if (!in_array($clase, LC_Competiciones::get_clases(), true)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Clase no válida'], 400);
        }
        if ($month < 1 || $month > 12) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Mes inválido'], 400);
        }

        if ($borrar) {
            $wpdb->delete("{$p}lc_tiempo_aprendizaje", [
                'clase'     => $clase,
                'iso_year'  => $year,
                'iso_month' => $month,
            ], ['%s', '%d', '%d']);

            return ['ok' => true, 'exists' => false, 'puntos' => 0];
        }

        $puntos = max(0, (int)$req->get_param('puntos'));
        $created_by = LC_Helpers::get_current_user_email();

        // INSERT … ON DUPLICATE KEY UPDATE: más eficiente que SELECT+INSERT/UPDATE
        $result = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$p}lc_tiempo_aprendizaje (clase, iso_year, iso_month, puntos, created_by)
             VALUES (%s, %d, %d, %d, %s)
             ON DUPLICATE KEY UPDATE puntos = VALUES(puntos), created_by = VALUES(created_by)",
            $clase, $year, $month, $puntos, $created_by
        ));

        if ($result === false) {
            error_log('LC TiempoAprendizaje DB Error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok' => false, 'error' => 'DB: ' . $wpdb->last_error], 500);
        }

        return ['ok' => true, 'exists' => true, 'puntos' => $puntos];
    }
}
