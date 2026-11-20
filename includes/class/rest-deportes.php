<?php
if (!defined('ABSPATH')) { exit; }

/**
 * API de Deportes: enfrentamientos entre clases, partidos entre alumnos y
 * pruebas individuales por marca (puntos por posición).
 *
 * GET/POST/DELETE /deportes. Requiere acceso completo.
 * Documentación: docs/modulos/deportes.md
 */
class LC_REST_Deportes {

    public static function register(){
        // DEPORTES: listar partidos/eventos de una semana
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

        // DEPORTES: alta/actualización de un partido (enfrentamiento) o evento (individual)
        register_rest_route('liga-creus/v1', '/deportes', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'upsert'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => [
                'deporte'   => ['required'=>true, 'type'=>'string'],
                'year'      => ['required'=>true, 'type'=>'integer'],
                'week'      => ['required'=>true, 'type'=>'integer'],
                'equipo_a'  => ['required'=>false, 'type'=>'string'],
                'equipo_b'  => ['required'=>false, 'type'=>'string'],
                'marcador_a'=> ['required'=>false, 'type'=>'integer'],
                'marcador_b'=> ['required'=>false, 'type'=>'integer'],
                'participantes'=> ['required'=>false, 'type'=>'string'],
                'alumno_a'  => ['required'=>false, 'type'=>'integer'],
                'alumno_b'  => ['required'=>false, 'type'=>'integer'],
                'notas'     => ['required'=>false, 'type'=>'string'],
                'id'        => ['required'=>false, 'type'=>'integer'],
            ],
        ]);

        // Borrar un partido o evento registrado por error.
        register_rest_route('liga-creus/v1', '/deportes', [
            'methods'  => 'DELETE',
            'callback' => [__CLASS__, 'borrar'],
            'permission_callback' => ['LC_Auth', 'only_allowed'],
            'args' => ['id' => ['required' => true, 'type' => 'integer']],
        ]);
    }

    /**
     * Lista partidos/eventos de un deporte en una semana específica
     */
    public static function list(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $dep  = sanitize_text_field($req['deporte']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id, equipo_a, equipo_b, marcador_a, marcador_b, pts_a, pts_b, alumno_a, alumno_b, participantes, notas
                            FROM {$p}lc_deportes
                            WHERE deporte=%s AND iso_year=%d AND iso_week=%d
                            ORDER BY id ASC",
                            $dep, $year, $week), ARRAY_A
        );

        // Nombre de los alumnos resuelto aquí y no en el navegador: la lista se
        // pinta en cuanto llega y no hace falta una segunda petición por fila.
        $alumnos = LC_Alumnos::mapa();
        foreach ($rows as &$r) {
            $r['alumno_a_nombre'] = $alumnos[(int)$r['alumno_a']]['nombre'] ?? '';
            $r['alumno_b_nombre'] = $alumnos[(int)$r['alumno_b']]['nombre'] ?? '';
        }
        unset($r);

        // Totales por clase en esa semana (solo tiene sentido en modo enfrentamiento)
        $totales = [];
        foreach($rows as $r){
            if ($r['equipo_a'] === '' && $r['equipo_b'] === '') continue; // fila de modo individual
            $totales[$r['equipo_a']] = ($totales[$r['equipo_a']] ?? 0) + (int)$r['pts_a'];
            $totales[$r['equipo_b']] = ($totales[$r['equipo_b']] ?? 0) + (int)$r['pts_b'];
        }

        return ['ok'=>true, 'partidos'=>$rows, 'totales'=>$totales];
    }

    /**
     * Crea o actualiza un partido (modo enfrentamiento) o un evento individual
     */
    public static function upsert(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;

        $dep  = sanitize_text_field($req['deporte']);
        $year = (int)$req['year'];
        $week = (int)$req['week'];
        $notas = sanitize_textarea_field($req->get_param('notas'));

        $config = LC_Competiciones::get_deporte($dep);
        if (!$config) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Ese deporte no existe en la configuración'], 400);
        }
        $modo = $config['modo'] ?? 'enfrentamiento';

        if ($modo === 'individual') {
            return self::upsert_individual($req, $config, $dep, $year, $week, $notas);
        }
        // "alumnos" puntúa igual que un enfrentamiento entre clases: lo único que
        // añade es qué alumno de cada clase ha jugado, así que comparten camino.
        return self::upsert_enfrentamiento($req, $config, $dep, $year, $week, $notas, $modo);
    }

    private static function upsert_enfrentamiento(\WP_REST_Request $req, $config, $dep, $year, $week, $notas, $modo = 'enfrentamiento'){
        global $wpdb; $p = $wpdb->prefix;

        $a    = sanitize_text_field($req->get_param('equipo_a'));
        $b    = sanitize_text_field($req->get_param('equipo_b'));
        $ga   = max(0, (int)$req->get_param('marcador_a'));
        $gb   = max(0, (int)$req->get_param('marcador_b'));
        $participantes = sanitize_text_field($req->get_param('participantes'));
        $alumno_a = max(0, (int)$req->get_param('alumno_a'));
        $alumno_b = max(0, (int)$req->get_param('alumno_b'));
        $por_alumnos = ($modo === 'alumnos');

        if (!$a || !$b) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Selecciona las dos clases'], 400);
        }
        if ($a === $b) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Las clases deben ser distintas'], 400);
        }

        if ($por_alumnos) {
            if (!$alumno_a || !$alumno_b) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Selecciona el alumno de cada clase'], 400);
            }
            if ($alumno_a === $alumno_b) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Los alumnos deben ser distintos'], 400);
            }
            // El alumno tiene que ser de la clase por la que compite: si no, sus
            // puntos irían a una clase y los del enfrentamiento a otra.
            foreach ([[$alumno_a, $a], [$alumno_b, $b]] as $par) {
                $alumno = LC_Alumnos::get($par[0]);
                if (!$alumno || $alumno['clase'] !== $par[1]) {
                    return new \WP_REST_Response(['ok'=>false,'error'=>'Un alumno no pertenece a la clase indicada'], 400);
                }
            }
        }

        // Normaliza orden para evitar duplicados A/B vs B/A (marcador y alumno viajan con su clase)
        if (strnatcmp($a, $b) > 0) {
            [$a, $b] = [$b, $a];
            [$ga, $gb] = [$gb, $ga];
            [$alumno_a, $alumno_b] = [$alumno_b, $alumno_a];
        }

        $pts_victoria = $config['pts_victoria'] ?? 3;
        $pts_empate   = $config['pts_empate'] ?? 1;
        $pts_derrota  = $config['pts_derrota'] ?? 0;

        $pa = $ga > $gb ? $pts_victoria : ($ga === $gb ? $pts_empate : $pts_derrota);
        $pb = $gb > $ga ? $pts_victoria : ($ga === $gb ? $pts_empate : $pts_derrota);

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
            'alumno_a'  => $alumno_a,
            'alumno_b'  => $alumno_b,
            'participantes' => $participantes,
            'notas'     => $notas,
            'created_by'=> LC_Helpers::get_current_user_email(),
        ];

        $formats = ['%s','%d','%d','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s','%s'];
        $id = (int)$req->get_param('id');

        if ($id > 0) {
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_deportes WHERE id=%d AND deporte=%s AND iso_year=%d AND iso_week=%d",
                $id, $dep, $year, $week
            ));
            if (!$valid) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Registro no encontrado'], 404);
            }
            $ok = $wpdb->update("{$p}lc_deportes", $data, ['id'=>$id], $formats, ['%d']);
        } elseif ($por_alumnos) {
            // Cada enfrentamiento entre alumnos es un registro propio: dos clases
            // pueden jugar varias veces en la misma semana con alumnos distintos.
            $ok = $wpdb->insert("{$p}lc_deportes", $data, $formats);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_deportes
                 WHERE deporte=%s AND iso_year=%d AND iso_week=%d AND equipo_a=%s AND equipo_b=%s",
                $dep,$year,$week,$a,$b
            ));
            $ok = $exists
                ? $wpdb->update("{$p}lc_deportes", $data, ['id'=>$exists], $formats, ['%d'])
                : $wpdb->insert("{$p}lc_deportes", $data, $formats);
        }

        if ($ok === false) {
            error_log('LC deportes_upsert error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB'], 500);
        }

        $r = new \WP_REST_Request('GET', '/liga-creus/v1/deportes');
        $r->set_param('deporte', $dep);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }

    private static function upsert_individual(\WP_REST_Request $req, $config, $dep, $year, $week, $notas){
        global $wpdb; $p = $wpdb->prefix;

        $participantes = json_decode((string)$req->get_param('participantes'), true);
        if (!is_array($participantes) || count($participantes) === 0) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Añade al menos un participante con su marca'], 400);
        }

        $orden = ($config['orden'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $puntos_por_posicion = $config['puntos_por_posicion'] ?? [5, 3, 2, 1];

        // Normaliza y ordena por marca (asc = gana el menor tiempo, desc = gana la mayor marca)
        $rows = [];
        foreach ($participantes as $part) {
            $clase = sanitize_text_field($part['clase'] ?? '');
            $nombre = sanitize_text_field($part['nombre'] ?? '');
            $marca = isset($part['marca']) ? (float)$part['marca'] : null;
            if ($clase === '' || $nombre === '' || $marca === null) continue;
            $rows[] = ['clase'=>$clase, 'nombre'=>$nombre, 'marca'=>$marca];
        }
        if (empty($rows)) {
            return new \WP_REST_Response(['ok'=>false,'error'=>'Añade al menos un participante con su marca'], 400);
        }

        usort($rows, function($x, $y) use ($orden) {
            return $orden === 'asc' ? ($x['marca'] <=> $y['marca']) : ($y['marca'] <=> $x['marca']);
        });

        foreach ($rows as $i => &$r) {
            $r['puntos'] = $puntos_por_posicion[$i] ?? 0;
        }
        unset($r);

        $data = [
            'deporte'   => $dep,
            'iso_year'  => $year,
            'iso_week'  => $week,
            'equipo_a'  => '',
            'equipo_b'  => '',
            'marcador_a'=> 0,
            'marcador_b'=> 0,
            'pts_a'     => 0,
            'pts_b'     => 0,
            'alumno_a'  => 0,
            'alumno_b'  => 0,
            'participantes' => wp_json_encode($rows),
            'notas'     => $notas,
            'created_by'=> LC_Helpers::get_current_user_email(),
        ];

        $formats = ['%s','%d','%d','%s','%s','%d','%d','%d','%d','%d','%d','%s','%s','%s'];
        $id = (int)$req->get_param('id');

        if ($id > 0) {
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_deportes WHERE id=%d AND deporte=%s AND iso_year=%d AND iso_week=%d",
                $id, $dep, $year, $week
            ));
            if (!$valid) {
                return new \WP_REST_Response(['ok'=>false,'error'=>'Registro no encontrado'], 404);
            }
            $ok = $wpdb->update("{$p}lc_deportes", $data, ['id'=>$id], $formats, ['%d']);
        } else {
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$p}lc_deportes WHERE deporte=%s AND iso_year=%d AND iso_week=%d AND equipo_a='' AND equipo_b=''",
                $dep, $year, $week
            ));
            $ok = $exists
                ? $wpdb->update("{$p}lc_deportes", $data, ['id'=>$exists], $formats, ['%d'])
                : $wpdb->insert("{$p}lc_deportes", $data, $formats);
        }

        if ($ok === false) {
            error_log('LC deportes_upsert (individual) error: ' . $wpdb->last_error);
            return new \WP_REST_Response(['ok'=>false,'error'=>'DB'], 500);
        }

        $r = new \WP_REST_Request('GET', '/liga-creus/v1/deportes');
        $r->set_param('deporte', $dep);
        $r->set_param('year', $year);
        $r->set_param('week', $week);
        return self::list($r);
    }

    /** Borra un partido o evento y devuelve la lista actualizada de esa semana. */
    public static function borrar(\WP_REST_Request $req){
        global $wpdb; $p = $wpdb->prefix;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT deporte, iso_year, iso_week FROM {$p}lc_deportes WHERE id=%d", (int) $req->get_param('id')), ARRAY_A);
        if (!$row) return new \WP_REST_Response(['ok'=>false,'error'=>'Registro no encontrado'], 404);
        $wpdb->delete("{$p}lc_deportes", ['id' => (int) $req->get_param('id')], ['%d']);

        $r = new \WP_REST_Request('GET', '/liga-creus/v1/deportes');
        $r->set_param('deporte', $row['deporte']);
        $r->set_param('year', (int) $row['iso_year']);
        $r->set_param('week', (int) $row['iso_week']);
        return self::list($r);
    }
}
