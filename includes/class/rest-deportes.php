<?php
if (!defined('ABSPATH')) { exit; }

class LC_REST_Deportes {
    
    public static function register(){
        // DEPORTES: listar partidos de una semana
        register_rest_route('liga-creus/v1', '/deportes', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'list'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'deporte' => ['required'=>true, 'type'=>'string'],
                'year'    => ['required'=>true, 'type'=>'integer'],
                'week'    => ['required'=>true, 'type'=>'integer'],
            ],
        ]);

        // DEPORTES: alta/actualización de un partido
        register_rest_route('liga-creus/v1', '/deportes', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'deporte'   => ['required'=>true, 'type'=>'string'],
                'year'      => ['required'=>true, 'type'=>'integer'],
                'week'      => ['required'=>true, 'type'=>'integer'],
                'equipo_a'  => ['required'=>true, 'type'=>'string'],
                'equipo_b'  => ['required'=>true, 'type'=>'string'],
                'marcador_a'=> ['required'=>true, 'type'=>'integer'],
                'marcador_b'=> ['required'=>true, 'type'=>'integer'],
                'participantes'=> ['required'=>false, 'type'=>'string'],
                'notas'     => ['required'=>false, 'type'=>'string'],
            ],
        ]);
    }

    /**
     * Lista partidos de un deporte en una semana específica
     */
    public static function list(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $dep  = sanitize_text_field($req['deporte']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, equipo_a, equipo_b, marcador_a, marcador_b, pts_a, pts_b, participantes, notas
                            FROM {$p}lc_deportes
                            WHERE deporte=%s AND iso_year=%d AND iso_week=%d
                            ORDER BY id ASC",
                            $dep, $year, $week), ARRAY_A
        );

        // Totales por clase en esa semana (útil para vista rápida)
        $totales = [];
        foreach($rows as $r){
            $totales[$r['equipo_a']] = ($totales[$r['equipo_a']] ?? 0) + (int)$r['pts_a'];
            $totales[$r['equipo_b']] = ($totales[$r['equipo_b']] ?? 0) + (int)$r['pts_b'];
        }

        return ['ok'=>true, 'partidos'=>$rows, 'totales'=>$totales];
    }

    /**
     * Crea o actualiza un partido
     */
    public static function upsert(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $dep  = sanitize_text_field($req['deporte']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];
        $a    = sanitize_text_field($req['equipo_a']);
        $b    = sanitize_text_field($req['equipo_b']);
        $ga   = max(0, (int)$req['marcador_a']);
        $gb   = max(0, (int)$req['marcador_b']);
        $participantes = sanitize_text_field($req->get_param('participantes'));
        $notas= sanitize_textarea_field($req->get_param('notas'));

        if ($a === $b) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Equipos iguales'], 400);
        }

        // Normaliza orden para evitar duplicados A/B vs B/A (los marcadores viajan con su equipo)
        if (strnatcmp($a, $b) > 0) {
            [$a, $b] = [$b, $a];
            [$ga, $gb] = [$gb, $ga];
        }

        // Puntuación 3-1-0
        $pa = $ga > $gb ? 3 : ($ga === $gb ? 1 : 0);
        $pb = $gb > $ga ? 3 : ($ga === $gb ? 1 : 0);

        $data = [
            'deporte'   => $dep,
            'iso_year'  => $year,
            'iso_week'  => $week,
            'equipo_a'  => $a,
            'equipo_b'  => $b,
            'marcador_a'=> $ga,
            'marcador_b'=> $gb,
            'pts_a'     => $pa,
            'pts_b'     => $pb,
            'participantes' => $participantes,
            'notas'     => $notas,
            'created_by'=> LC_Helpers::get_current_user_email(),
        ];

        // ¿existe el partido?
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$p}lc_deportes
             WHERE deporte=%s AND iso_year=%d AND iso_week=%d AND equipo_a=%s AND equipo_b=%s",
            $dep,$year,$week,$a,$b
        ));

        $formats = ['%s','%d','%d','%s','%s','%d','%d','%d','%d','%s','%s','%s'];

        $ok = $exists
            ? $wpdb->update("{$p}lc_deportes", $data, ['id'=>$exists], $formats, ['%d'])
            : $wpdb->insert("{$p}lc_deportes", $data, $formats);

        if ($ok === false) {
            // Para depurar si vuelve a fallar:
            error_log('LC deportes_upsert error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB'], 500);
        }

        // Devolver lista actualizada
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/deportes');
        $r->set_param('deporte', $dep);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }
}