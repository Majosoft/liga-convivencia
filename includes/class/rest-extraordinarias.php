<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_Extraordinarias {

    public static function register(){
        register_rest_route('liga-creus/v1', '/extraordinarias', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'year' => ['required' => true,  'type' => 'integer'],
                'week' => ['required' => true,  'type' => 'integer'],
            ],
        ]);

        register_rest_route('liga-creus/v1', '/extraordinarias', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'nombre'           => ['required' => true,  'type' => 'string'],
                'year'             => ['required' => true,  'type' => 'integer'],
                'week'             => ['required' => true,  'type' => 'integer'],
                'clases'           => ['required' => true,  'type' => 'string'],  // JSON
                'notas'            => ['required' => false, 'type' => 'string'],
                'original_nombre'  => ['required' => false, 'type' => 'string'],  // para renombrar
            ],
        ]);

        // Borrar una actividad entera (todas sus clases) de una semana.
        register_rest_route('liga-creus/v1', '/extraordinarias', [
            'methods'  => 'DELETE',
            'callback' => [__CLASS__, 'borrar'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'nombre' => ['required' => true, 'type' => 'string'],
                'year'   => ['required' => true, 'type' => 'integer'],
                'week'   => ['required' => true, 'type' => 'integer'],
            ],
        ]);
    }

    /**
     * Lista las actividades extraordinarias de una semana, agrupadas por nombre.
     */
    public static function list(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $year = (int)$req['year'];
        $week = (int)$req['week'];

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT nombre, clase, puntos, notas
             FROM {$p}lc_extraordinarias
             WHERE iso_year=%d AND iso_week=%d
             ORDER BY nombre ASC, puntos DESC",
            $year, $week
        ), ARRAY_A);

        return ['ok' => true, 'actividades' => self::group_by_nombre($rows)];
    }

    /**
     * Crea o reemplaza una actividad extraordinaria (delete + insert por nombre+semana).
     */
    public static function upsert(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $nombre          = sanitize_text_field($req->get_param('nombre'));
        $year            = (int)$req->get_param('year');
        $week            = (int)$req->get_param('week');
        $notas           = sanitize_textarea_field($req->get_param('notas'));
        $original_nombre = sanitize_text_field($req->get_param('original_nombre') ?: $nombre);

        if (empty($nombre)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'El nombre de la actividad es obligatorio'], 400);
        }

        $clases_json = $req->get_param('clases');
        $clases = json_decode($clases_json, true);
        if (!is_array($clases) || count($clases) === 0) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Añade al menos una clase participante'], 400);
        }

        $puntos_validos = [10, 8, 5, 3, 0];
        $rows_a_insertar = [];
        foreach ($clases as $c) {
            $clase  = sanitize_text_field($c['clase'] ?? '');
            $puntos = (int)($c['puntos'] ?? 0);
            if (empty($clase)) continue;
            if (!in_array($clase, LC_Competiciones::get_clases(), true)) {
                return new \WP_REST_Response(['ok' => false, 'error' => "La clase {$clase} no existe"], 400);
            }
            if (!in_array($puntos, $puntos_validos, true)) {
                return new \WP_REST_Response(['ok' => false, 'error' => "Puntuación no válida para la clase {$clase}"], 400);
            }
            // La tabla no admite la misma clase dos veces en una actividad. Hay que
            // rechazarlo ANTES de borrar la versión anterior: si no, el INSERT
            // fallaba a medias y la actividad quedaba borrada o incompleta.
            if (isset($rows_a_insertar[$clase])) {
                return new \WP_REST_Response(['ok' => false, 'error' => "La clase {$clase} está repetida"], 400);
            }
            $rows_a_insertar[$clase] = ['clase' => $clase, 'puntos' => $puntos];
        }

        if (empty($rows_a_insertar)) {
            return new \WP_REST_Response(['ok' => false, 'error' => 'Añade al menos una clase participante válida'], 400);
        }

        // Reemplaza la actividad: borra las filas antiguas (con nombre original) e inserta las nuevas
        $wpdb->delete("{$p}lc_extraordinarias", [
            'nombre'   => $original_nombre,
            'iso_year' => $year,
            'iso_week' => $week,
        ], ['%s', '%d', '%d']);
        // Al renombrar sobre el nombre de otra actividad de la misma semana (el
        // panel pide confirmación), esa también se sustituye: si se dejara, sus
        // filas chocarían con la clave única y el guardado fallaría a medias.
        if ($original_nombre !== $nombre) {
            $wpdb->delete("{$p}lc_extraordinarias", [
                'nombre'   => $nombre,
                'iso_year' => $year,
                'iso_week' => $week,
            ], ['%s', '%d', '%d']);
        }

        $created_by = LC_Helpers::get_current_user_email();
        foreach ($rows_a_insertar as $r) {
            $wpdb->insert("{$p}lc_extraordinarias", [
                'nombre'     => $nombre,
                'iso_year'   => $year,
                'iso_week'   => $week,
                'clase'      => $r['clase'],
                'puntos'     => $r['puntos'],
                'notas'      => $notas,
                'created_by' => $created_by,
            ], ['%s', '%d', '%d', '%s', '%d', '%s', '%s']);

            if ($wpdb->last_error) {
                error_log('LC Extraordinarias DB Error: ' . $wpdb->last_error);
                return new \WP_REST_Response(['ok' => false, 'error' => 'Error al guardar: ' . $wpdb->last_error], 500);
            }
        }

        return self::respond_list($year, $week);
    }

    /**
     * Agrupa filas planas por nombre de actividad.
     *
     * @param  array $rows  Filas crudas de la BD.
     * @return array        Array de actividades con clases anidadas.
     */
    private static function group_by_nombre(array $rows): array {
        $grouped = [];
        foreach ($rows as $r) {
            $nombre = $r['nombre'];
            if (!isset($grouped[$nombre])) {
                $grouped[$nombre] = [
                    'nombre' => $nombre,
                    'notas'  => $r['notas'] ?? '',
                    'clases' => [],
                ];
            }
            $grouped[$nombre]['clases'][] = [
                'clase'  => $r['clase'],
                'puntos' => (int)$r['puntos'],
            ];
        }
        return array_values($grouped);
    }

    private static function respond_list(int $year, int $week): array {
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/extraordinarias');
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }

    /** Borra una actividad extraordinaria de una semana y devuelve la lista actualizada. */
    public static function borrar(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $year = (int) $req->get_param('year');
        $week = (int) $req->get_param('week');
        $n = $wpdb->delete("{$p}lc_extraordinarias", [
            'nombre' => sanitize_text_field($req->get_param('nombre')), 'iso_year' => $year, 'iso_week' => $week,
        ], ['%s', '%d', '%d']);
        if (!$n) return new \WP_REST_Response(['ok'=>false,'error'=>'Actividad no encontrada'], 404);
        return self::respond_list($year, $week);
    }
}
