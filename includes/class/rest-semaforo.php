<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_Semaforo {
    
    /**
     * Puntos que aporta una semana de gomets, con la puntuación configurada en
     * Competición > Semáforo. Los valores ya vienen con signo, así que es una
     * suma: no hay restas cableadas aquí.
     */
    private static function calculateTotal($data) {
        $verdes = intval($data->gomets_verdes ?? $data['gomets_verdes'] ?? 0);
        $amarillos = intval($data->gomets_amarillos ?? $data['gomets_amarillos'] ?? 0);
        $rojos = intval($data->gomets_rojos ?? $data['gomets_rojos'] ?? 0);

        $pts = LC_Competiciones::get_semaforo();
        return ($verdes * $pts['verde']) + ($amarillos * $pts['amarillo']) + ($rojos * $pts['rojo']);
    }

    /**
     * Rehace el acumulado de TODAS las clases y semanas con la puntuación
     * vigente. Necesario al cambiar los puntos de los gomets: la columna "total"
     * guarda un acumulado calculado en el momento de guardar, así que sin esto
     * las semanas antiguas conservarían la puntuación vieja y el total dejaría de
     * corresponderse con los gomets registrados.
     */
    public static function recalcular_totales(){
        global $wpdb; $p = $wpdb->prefix;

        $rows = $wpdb->get_results(
            "SELECT id, clase, gomets_verdes, gomets_amarillos, gomets_rojos
             FROM {$p}lc_semaforo
             ORDER BY clase ASC, iso_year ASC, iso_week ASC"
        );
        if (!$rows) return;

        $acumulado = [];
        foreach ($rows as $r) {
            $nuevo = ($acumulado[$r->clase] ?? 0) + self::calculateTotal([
                'gomets_verdes'    => $r->gomets_verdes,
                'gomets_amarillos' => $r->gomets_amarillos,
                'gomets_rojos'     => $r->gomets_rojos,
            ]);
            $wpdb->update("{$p}lc_semaforo", ['total' => $nuevo], ['id' => $r->id], ['%d'], ['%d']);
            $acumulado[$r->clase] = $nuevo;
        }
    }
    
    public static function register(){
        register_rest_route('liga-creus/v1', '/semaforo', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required'=>true, 'type'=>'string'],
                'year'  => ['required'=>true, 'type'=>'integer'],
                'week'  => ['required'=>true, 'type'=>'integer'],
            ],
        ]);
        
        register_rest_route('liga-creus/v1', '/semaforo', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'clase' => ['required'=>true, 'type'=>'string'],
                'year'  => ['required'=>true, 'type'=>'integer'],
                'week'  => ['required'=>true, 'type'=>'integer'],
                'gomets_verdes' => ['required'=>false, 'type'=>'integer'],
                'gomets_amarillos' => ['required'=>false, 'type'=>'integer'],
                'gomets_rojos' => ['required'=>false, 'type'=>'integer'],
                'notas' => ['required'=>false, 'type'=>'string'],
            ],
        ]);
    }

    /**
     * Obtiene datos del semáforo para una clase y semana específica
     */
    public static function get(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $clase = sanitize_text_field($req['clase']);
        $year  = intval($req['year']);
        $week  = intval($req['week']);

        // Fila solicitada (si existe)
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$p}lc_semaforo WHERE clase=%s AND iso_year=%d AND iso_week=%d",
            $clase, $year, $week), ARRAY_A
        );

        // Total actual = total acumulado ya guardado en la última semana de la clase
        $latest_total = $wpdb->get_var(
            $wpdb->prepare("SELECT total
                            FROM {$p}lc_semaforo
                            WHERE clase=%s
                            ORDER BY iso_year DESC, iso_week DESC
                            LIMIT 1", $clase)
        );
        $current_total = $latest_total !== null ? intval($latest_total) : 0;

        // Total acumulado de la semana anterior a la solicitada (para autollenar Base si no hay fila)
        $prev_total_db = $wpdb->get_var(
            $wpdb->prepare("SELECT total
                            FROM {$p}lc_semaforo
                            WHERE clase=%s AND (iso_year < %d OR (iso_year = %d AND iso_week < %d))
                            ORDER BY iso_year DESC, iso_week DESC
                            LIMIT 1", $clase, $year, $year, $week)
        );
        $prev_total = $prev_total_db !== null ? intval($prev_total_db) : 0;

        return ['ok'=>true, 'data'=>$row, 'current_total'=>$current_total, 'prev_total'=>$prev_total];
    }

    /**
     * Crea o actualiza datos del semáforo con lógica de cascada (formulario
     * manual del panel).
     */
    public static function upsert(\WP_REST_Request $req){
        $res = self::guardar_semana(
            sanitize_text_field($req['clase']), intval($req['year']), intval($req['week']),
            intval($req->get_param('gomets_verdes')), intval($req->get_param('gomets_amarillos')), intval($req->get_param('gomets_rojos')),
            sanitize_textarea_field($req->get_param('notas')), LC_Helpers::get_current_user_email()
        );
        if (is_string($res)) return new \WP_REST_Response(['ok'=>false,'error'=>$res], 500);
        return ['ok'=>true, 'current_total'=>$res];
    }

    /**
     * Guarda los gomets de una clase en una semana y rehace el acumulado
     * ("total") de esa semana y de todas las posteriores. La usan el formulario
     * manual y, desde la 1.0, el recuento automático de los gomets por hora
     * (LC_Horario::recalcular_semana). $notas null = conservar las que hubiera.
     * Devuelve el acumulado final o un mensaje de error.
     */
    public static function guardar_semana($clase, $year, $week, $verdes, $amarillos, $rojos, $notas, $created_by){
        global $wpdb; $p = $wpdb->prefix;

        if ($notas === null) {
            $notas = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT notas FROM {$p}lc_semaforo WHERE clase=%s AND iso_year=%d AND iso_week=%d", $clase, $year, $week));
        }

        // Total acumulado de la semana anterior (0 si esta es la primera semana de la clase)
        $prev_total_db = $wpdb->get_var(
            $wpdb->prepare("SELECT total
                            FROM {$p}lc_semaforo
                            WHERE clase=%s AND (iso_year < %d OR (iso_year = %d AND iso_week < %d))
                            ORDER BY iso_year DESC, iso_week DESC
                            LIMIT 1", $clase, $year, $year, $week)
        );
        $prev_total = $prev_total_db !== null ? intval($prev_total_db) : 0;

        // Nuevo total = acumulado de la semana anterior + delta de gomets de esta semana
        $new_total = $prev_total + self::calculateTotal([
            'gomets_verdes' => $verdes,
            'gomets_amarillos' => $amarillos,
            'gomets_rojos' => $rojos
        ]);

        // Guardar/actualizar la semana actual
        $data = [
            'clase'=>$clase, 'iso_year'=>$year, 'iso_week'=>$week,
            'gomets_verdes'=>$verdes, 'gomets_amarillos'=>$amarillos, 'gomets_rojos'=>$rojos,
            'total'=>$new_total, 'notas'=>$notas,
            'created_by'=> $created_by,
        ];
        $res = $wpdb->replace("{$p}lc_semaforo", $data, ['%s','%d','%d','%d','%d','%d','%d','%s','%s']);
        if ($res === false) {
            error_log('LC Semaforo DB Error: ' . $wpdb->last_error);
            return 'DB: ' . $wpdb->last_error;
        }

        // LÓGICA DE CASCADA: Recalcular todas las semanas posteriores
        $semanas_posteriores = $wpdb->get_results(
            $wpdb->prepare("SELECT id, gomets_verdes, gomets_amarillos, gomets_rojos
                            FROM {$p}lc_semaforo
                            WHERE clase=%s 
                            AND (iso_year > %d OR (iso_year = %d AND iso_week > %d))
                            ORDER BY iso_year ASC, iso_week ASC", 
                            $clase, $year, $year, $week)
        );

        $current_total = $new_total;
        foreach ($semanas_posteriores as $semana) {
            $current_total += self::calculateTotal([
                'gomets_verdes' => intval($semana->gomets_verdes),
                'gomets_amarillos' => intval($semana->gomets_amarillos),
                'gomets_rojos' => intval($semana->gomets_rojos)
            ]);
            $wpdb->update("{$p}lc_semaforo", ['total' => $current_total], ['id' => $semana->id], ['%d'], ['%d']);
        }

        return $current_total;
    }
}
