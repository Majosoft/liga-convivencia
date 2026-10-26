<?php
/**
 * REST API para clasificación
 */

if (!defined('ABSPATH')) { exit; }

class LC_REST_Clasificacion {

    public static function register() {
        register_rest_route('liga-creus/v1', '/clasificacion', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_clasificacion'],
            'permission_callback' => '__return_true', // Público, sin autenticación
            'args' => [
                'periodo' => ['type' => 'string'],
                'year' => ['type' => 'integer'],
                'week' => ['type' => 'integer'],
                'month' => ['type' => 'integer'],
                'trimestre' => ['type' => 'string'],
                'temporada' => ['type' => 'string'],
            ],
        ]);

        // Widget del sitio ("Indicadores de Convivencia"). Vive en el tema, no en
        // el plugin, y consume HTML ya montado: por eso devuelve {ok, html} en vez
        // de datos. El contrato lo fijó la versión 0.3.x y hay que respetarlo para
        // no tener que tocar el tema.
        register_rest_route('liga-creus/v1', '/widget', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_widget'],
            'permission_callback' => '__return_true', // Público, sin autenticación
        ]);

        // Cualquier escritura del plugin invalida el HTML cacheado del widget. Un
        // solo gancho en vez de una llamada en cada endpoint: así no se olvida al
        // añadir módulos nuevos.
        add_filter('rest_request_after_callbacks', function($response, $handler, $request){
            if ($request->get_method() !== 'GET' && strpos($request->get_route(), '/liga-creus/v1') === 0) {
                delete_transient('lc_widget_html');
            }
            return $response;
        }, 10, 3);
    }

    /**
     * Devuelve el widget ya renderizado. Se cachea 5 minutos: se pinta en páginas
     * públicas y no merece rehacer la clasificación entera en cada visita.
     */
    public static function get_widget(\WP_REST_Request $req) {
        $cache_key = 'lc_widget_html';
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return ['ok' => true, 'html' => $cached, 'cached' => true, 'timestamp' => current_time('mysql')];
        }

        // Se reutiliza el endpoint principal en vez de recalcular por otro camino:
        // así el widget nunca puede mostrar números distintos a los de la página.
        $r = new \WP_REST_Request('GET', '/liga-creus/v1/clasificacion');
        $r->set_param('periodo', 'general');
        $datos = self::get_clasificacion($r);

        $html = self::generate_widget_html(
            $datos['clasificacion'] ?? [],
            $datos['temporada_activa'] ?? null
        );

        set_transient($cache_key, $html, 5 * MINUTE_IN_SECONDS);

        return ['ok' => true, 'html' => $html, 'cached' => false, 'timestamp' => current_time('mysql')];
    }

    /**
     * Página pública de la clasificación, buscada por su shortcode en lugar de
     * codificar la URL (la versión 0.3.x la tenía escrita a fuego y se rompía al
     * renombrar la página o cambiar de dominio).
     */
    private static function url_clasificacion() {
        global $wpdb;
        $cached = get_transient('lc_widget_url');
        if ($cached !== false) return $cached;

        $id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_status = 'publish'
               AND post_type IN ('page', 'post')
               AND post_content LIKE '%[liga_creus_clasificacion%'
             ORDER BY post_type = 'page' DESC, ID ASC
             LIMIT 1"
        );
        $url = $id ? get_permalink((int) $id) : '';
        set_transient('lc_widget_url', $url, HOUR_IN_SECONDS);
        return $url;
    }

    /**
     * HTML del widget, con los estilos EN LÍNEA a propósito: el widget se pinta en
     * páginas que no llevan ningún shortcode del plugin, así que ni tokens.css ni
     * clasificacion.css están cargados ahí y no hay clases a las que agarrarse.
     * Los valores son los mismos de assets/css/tokens.css.
     */
    private static function generate_widget_html($clasificacion, $temporada) {
        $F  = "'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif";
        $podio = ['#FFFBEB', '#F1F5F9', '#FFF1E6']; // warning-light, neutral-100, bronce
        $medalla = ['🥇', '🥈', '🥉'];

        $html  = '<div style="font-family:' . $F . ';max-width:420px;margin:0 auto;background:#FFFFFF;'
               . 'border:1px solid #E2E8F0;border-radius:12px;box-shadow:0 10px 24px rgba(15,23,42,.08);overflow:hidden;">';

        $html .= '<div style="padding:14px 16px;border-bottom:1px solid #E2E8F0;">';
        $html .= '<div style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#64748B;">Clasificación general</div>';
        if (!empty($temporada['label'])) {
            $html .= '<div style="font-size:14px;font-weight:700;color:#0F172A;margin-top:2px;">Temporada ' . esc_html($temporada['label']) . '</div>';
        }
        $html .= '</div>';

        if (empty($clasificacion)) {
            $html .= '<div style="padding:20px 16px;font-size:13px;color:#64748B;text-align:center;">Todavía no hay puntos registrados.</div>';
        }

        foreach (array_values($clasificacion) as $i => $fila) {
            $fondo = $podio[$i] ?? '#FFFFFF';
            $pos = $medalla[$i] ?? (string) ($i + 1);
            $escudo = !empty($fila['escudo_url'])
                ? '<img src="' . esc_url($fila['escudo_url']) . '" alt="" style="width:28px;height:28px;object-fit:contain;border-radius:6px;margin-right:10px;flex-shrink:0;" />'
                : '';

            $html .= '<div style="display:flex;align-items:center;gap:4px;padding:10px 16px;background:' . $fondo . ';'
                   . ($i + 1 < count($clasificacion) ? 'border-bottom:1px solid #E2E8F0;' : '') . '">';
            $html .= '<div style="width:28px;text-align:center;font-weight:700;font-size:14px;color:#0F172A;flex-shrink:0;">' . $pos . '</div>';
            $html .= '<div style="flex:1;display:flex;align-items:center;min-width:0;font-weight:600;font-size:14px;color:#0F172A;">'
                   . $escudo . '<span>' . esc_html($fila['clase']) . '</span></div>';
            $html .= '<div style="font-weight:800;font-size:16px;color:#059669;">' . (int) $fila['total'] . '</div>';
            $html .= '</div>';
        }

        $url = self::url_clasificacion();
        $html .= '<div style="padding:12px 16px;background:#F8FAFC;border-top:1px solid #E2E8F0;text-align:center;">';
        if ($url) {
            $html .= '<a href="' . esc_url($url) . '" style="display:inline-block;background:#4F46E5;color:#FFFFFF;text-decoration:none;'
                   . 'padding:8px 16px;border-radius:10px;font-size:13px;font-weight:600;">Ver clasificación completa</a>';
        }
        $html .= '<div style="font-size:11px;color:#64748B;margin-top:' . ($url ? '10px' : '0') . ';">Actualizado: '
               . esc_html(date_i18n('d/m/Y H:i')) . '</div>';
        $html .= '</div>';

        $html .= '</div>';
        return $html;
    }

    // Convierte una fecha (Y-m-d) a su año/semana ISO-8601, igual que el
    // algoritmo que helpers.js reimplementa a mano en el frontend.
    private static function date_to_iso_week($dateStr) {
        $ts = strtotime($dateStr);
        return ['year' => (int) date('o', $ts), 'week' => (int) date('W', $ts)];
    }

    private static function point_lt($a, $b) {
        return $a['year'] < $b['year'] || ($a['year'] === $b['year'] && $a['week'] < $b['week']);
    }

    private static function point_lte($a, $b) {
        return $a['year'] < $b['year'] || ($a['year'] === $b['year'] && $a['week'] <= $b['week']);
    }

    private static function date_to_yearmonth($dateStr) {
        $ts = strtotime($dateStr);
        return ['year' => (int) date('Y', $ts), 'month' => (int) date('n', $ts)];
    }

    // Traduce el período pedido a un rango ['from' => ['year','week']|null, 'to' => ...|null].
    // from=to=null significa "general": sin filtro, todo el histórico.
    //
    // Además devuelve m_from/m_to (['year','month']|null) para Tiempo de
    // Aprendizaje, que es mensual. Se sacan de las FECHAS reales y no de la
    // semana ISO: el lunes de la semana del día 1 suele caer en el mes anterior,
    // y "Mensual – Noviembre" acababa sumando también los puntos de octubre.
    private static function compute_range(\WP_REST_Request $req) {
        $r = self::compute_week_range($req);
        $periodo = $req->get_param('periodo') ?: 'general';

        $r['m_from'] = null;
        $r['m_to'] = null;
        if ($periodo === 'mensual' && $r['from']) {
            $r['m_from'] = $r['m_to'] = ['year' => (int) $req->get_param('year'), 'month' => (int) $req->get_param('month')];
        } elseif (isset($r['fechas'])) {
            $r['m_from'] = self::date_to_yearmonth($r['fechas'][0]);
            $r['m_to'] = $r['fechas'][1] ? self::date_to_yearmonth($r['fechas'][1]) : null;
        } else {
            // Semanal: el mes al que pertenece el lunes de esa semana.
            if ($r['from']) $r['m_from'] = self::week_to_yearmonth($r['from']['year'], $r['from']['week']);
            if ($r['to'])   $r['m_to']   = self::week_to_yearmonth($r['to']['year'], $r['to']['week']);
        }
        unset($r['fechas']);
        return $r;
    }

    private static function compute_week_range(\WP_REST_Request $req) {
        $periodo = $req->get_param('periodo') ?: 'general';

        if ($periodo === 'semanal') {
            $year = (int) $req->get_param('year');
            $week = (int) $req->get_param('week');
            if (!$year || !$week) return ['from' => null, 'to' => null];
            return ['from' => ['year' => $year, 'week' => $week], 'to' => ['year' => $year, 'week' => $week]];
        }

        if ($periodo === 'mensual') {
            $year = (int) $req->get_param('year');
            $month = (int) $req->get_param('month');
            if (!$year || !$month) return ['from' => null, 'to' => null];
            $from = self::date_to_iso_week(sprintf('%04d-%02d-01', $year, $month));
            $lastDay = date('t', strtotime(sprintf('%04d-%02d-01', $year, $month)));
            $to = self::date_to_iso_week(sprintf('%04d-%02d-%02d', $year, $month, $lastDay));
            return ['from' => $from, 'to' => $to];
        }

        if ($periodo === 'trimestral') {
            $label = $req->get_param('trimestre');
            $match = self::find_trimestre($label);
            if ($match) {
                return [
                    'from' => self::date_to_iso_week($match['inicio']),
                    'to' => self::date_to_iso_week($match['fin']),
                    'fechas' => [$match['inicio'], $match['fin']],
                ];
            }
            return ['from' => null, 'to' => null];
        }

        if ($periodo === 'temporada') {
            $label = $req->get_param('temporada');
            foreach (LC_Competiciones::get_temporadas() as $t) {
                if (($t['label'] ?? '') === $label) {
                    return [
                        'from' => self::date_to_iso_week($t['inicio']),
                        'to' => !empty($t['fin']) ? self::date_to_iso_week($t['fin']) : null,
                        'fechas' => [$t['inicio'], $t['fin'] ?? ''],
                    ];
                }
            }
            return ['from' => null, 'to' => null];
        }

        // "general" (o sin parámetro): la temporada activa (sin fecha de fin), si hay
        // alguna configurada. Sin temporadas configuradas, todo el histórico — el
        // comportamiento de siempre, sin cambios, para quien no use esta función.
        $activa = LC_Competiciones::get_temporada_activa();
        if ($activa) {
            return ['from' => self::date_to_iso_week($activa['inicio']), 'to' => null, 'fechas' => [$activa['inicio'], '']];
        }
        return ['from' => null, 'to' => null];
    }

    // Busca un trimestre por nombre, dando prioridad al de la temporada activa.
    // Dos trimestres pueden compartir nombre en temporadas distintas (ej. "1er
    // Trimestre" repetido cada curso) — sin esto, "el primero que aparezca en la
    // lista" sería siempre el más antiguo, rompiendo el filtro del curso nuevo.
    //
    // Los dos respaldos son imprescindibles: si no hay temporada en curso (todas
    // tienen fecha de fin, que es lo normal al definir un curso escolar completo)
    // no hay con qué desempatar, y sin ellos NINGÚN trimestre asignado a una
    // temporada se encontraría nunca: el filtro se quedaba sin efecto en silencio.
    private static function find_trimestre($label) {
        $activaLabel = ($a = LC_Competiciones::get_temporada_activa()) ? ($a['label'] ?? '') : null;
        $sin_asignar = null;
        $cualquiera = null;
        foreach (LC_Competiciones::get_trimestres() as $t) {
            if (($t['label'] ?? '') !== $label) continue;
            $t_temporada = $t['temporada'] ?? '';
            if ($activaLabel !== null && $t_temporada === $activaLabel) return $t;
            if ($t_temporada === '' && $sin_asignar === null) $sin_asignar = $t;
            if ($cualquiera === null) $cualquiera = $t;
        }
        return $sin_asignar ?: $cualquiera;
    }

    // Trimestres que se ofrecen en el desplegable público: los de la temporada
    // activa más los "sin asignar", para no mezclar cursos distintos.
    //
    // Ese filtro solo tiene sentido si hay una temporada en curso. Sin ella
    // —o si no deja pasar ninguno— se ofrecen todos: más vale enseñar de más
    // que dejar el desplegable vacío y que el filtro parezca roto.
    private static function trimestres_visibles() {
        $todos = array_values(LC_Competiciones::get_trimestres());
        $activa = LC_Competiciones::get_temporada_activa();
        if (!$activa) return $todos;

        $activaLabel = $activa['label'] ?? '';
        $visibles = array_values(array_filter($todos, function($t) use ($activaLabel) {
            $tt = $t['temporada'] ?? '';
            return $tt === '' || $tt === $activaLabel;
        }));
        return $visibles ?: $todos;
    }

    // Convierte una semana ISO a su año/mes, tomando el lunes de esa semana como referencia.
    // Necesario para filtrar lc_tiempo_aprendizaje (mensual) con el rango de semanas del sistema.
    private static function week_to_yearmonth(int $year, int $week): array {
        $dt = new \DateTime();
        $dt->setISODate($year, $week, 1); // lunes de la semana
        return ['year' => (int) $dt->format('Y'), 'month' => (int) $dt->format('n')];
    }

    // Fragmento SQL (con sus params) que acota una tabla con columnas iso_year/iso_week
    // al rango dado, usando el mismo patrón de comparación de tuplas que ya usan
    // rest-partes.php y la cascada de rest-semaforo.php. Sin rango, devuelve ''.
    private static function range_sql_and_params($range) {
        $sql = '';
        $params = [];
        if ($range['from']) {
            $sql .= " AND (iso_year > %d OR (iso_year = %d AND iso_week >= %d))";
            $params[] = $range['from']['year'];
            $params[] = $range['from']['year'];
            $params[] = $range['from']['week'];
        }
        if ($range['to']) {
            $sql .= " AND (iso_year < %d OR (iso_year = %d AND iso_week <= %d))";
            $params[] = $range['to']['year'];
            $params[] = $range['to']['year'];
            $params[] = $range['to']['week'];
        }
        return [$sql, $params];
    }

    // get_results() pasando por prepare() SOLO si hay parametros: wpdb::prepare()
    // rechaza una consulta sin ningun placeholder (dispara _doing_it_wrong y
    // devuelve null), que es justo lo que queda cuando el rango viene vacio
    // -- "General" sin temporadas configuradas, el caso por defecto. Sin esto,
    // esas columnas salian a 0 en silencio.
    private static function get_results_ranged($sql, $params, $output = OBJECT) {
        global $wpdb;
        return $params
            ? $wpdb->get_results($wpdb->prepare($sql, $params), $output)
            : $wpdb->get_results($sql, $output);
    }

    // Convierte un mapa alumno_id => puntos en un ranking ordenado y con los
    // datos del alumno resueltos. Un alumno borrado conserva sus puntos: el
    // registro sigue siendo válido, solo se pierde su nombre.
    private static function ranking_alumnos(array $puntos, array $mapa) {
        $out = [];
        foreach ($puntos as $id => $pts) {
            $a = $mapa[(int) $id] ?? null;
            $out[] = [
                'alumno_id' => (int) $id,
                'nombre'    => $a['nombre'] ?? 'Alumno eliminado',
                'clase'     => $a['clase'] ?? '',
                'puntos'    => round($pts, 2),
            ];
        }
        usort($out, function($x, $y) { return $y['puntos'] <=> $x['puntos']; });
        return $out;
    }

    public static function get_clasificacion(\WP_REST_Request $req) {
        global $wpdb; $p = $wpdb->prefix;

        $periodo = $req->get_param('periodo') ?: 'general';
        $range = self::compute_range($req);
        list($range_sql, $range_params) = self::range_sql_and_params($range);

        $clases = LC_Competiciones::get_clases();

        $deportes_list = LC_Competiciones::get_deportes();
        $actividades_list = LC_Competiciones::get_actividades();

        // Escudos asignados desde Ajustes > Liga Convivencia, resueltos a URL una sola vez.
        $escudos = [];
        foreach ($clases as $clase) {
            $escudos[$clase] = LC_Competiciones::get_escudo_url($clase);
        }

        // Puntos por alumno: por prueba (ranking dentro de cada deporte/actividad)
        // y acumulados (pestaña Alumnos de la Clasificación).
        $alumnos_map = LC_Alumnos::mapa();
        $puntos_alumno_global = [];

        $totales = [];
        foreach ($clases as $clase) {
            $totales[$clase] = [
                'clase' => $clase,
                'escudo_url' => $escudos[$clase],
                'semaforo' => 0,
                'deportes' => 0,
                'partes' => 0,
                'partes_positivos' => 0,
                'partes_leves' => 0,
                'partes_graves' => 0,
                'biblioteca' => 0,
                'extraordinarias' => 0,
                'tiempo_aprendizaje' => 0,
                'total' => 0,
            ];
        }

        // 1. SEMÁFORO: lc_semaforo.total es un ACUMULADO (cada semana suma sobre la
        // anterior), así que lo ganado EN el período es end - baseline:
        // - baseline = total de la última fila ANTERIOR al inicio del período (0 si no hay).
        // - end      = total de la última fila DENTRO del período (≤ fin).
        // En "general" (from=to=null) baseline se queda en 0 y end se actualiza con
        // cada fila en orden ASC, dando el último total histórico: idéntico al
        // comportamiento previo a esta función.
        $semaforo_data = $wpdb->get_results("
            SELECT clase, iso_year, iso_week, total
            FROM {$p}lc_semaforo
            ORDER BY iso_year ASC, iso_week ASC
        ");
        $baseline = array_fill_keys($clases, 0);
        $end = array_fill_keys($clases, 0);
        foreach ($semaforo_data as $row) {
            if (!isset($baseline[$row->clase])) continue;
            $point = ['year' => (int) $row->iso_year, 'week' => (int) $row->iso_week];
            if ($range['from'] !== null && self::point_lt($point, $range['from'])) {
                $baseline[$row->clase] = intval($row->total);
            }
            if ($range['to'] === null || self::point_lte($point, $range['to'])) {
                $end[$row->clase] = intval($row->total);
            }
        }
        foreach ($clases as $clase) {
            $totales[$clase]['semaforo'] = $end[$clase] - $baseline[$clase];
        }

        // 2. DEPORTES: ranking por cada deporte individual; el agregado "deportes"
        // de la tabla general es la suma de todos los rankings.
        $por_deporte = [];
        foreach ($deportes_list as $dep) {
            $puntos = array_fill_keys($clases, 0);
            $modo = $dep['modo'] ?? 'enfrentamiento';

            if ($modo === 'individual') {
                // Cada fila es un evento con un roster de participantes que ya
                // trae los puntos calculados por posición (ver rest-deportes.php).
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT participantes FROM {$p}lc_deportes WHERE deporte=%s{$range_sql}",
                    array_merge([$dep['value']], $range_params)
                ));
                foreach ($rows as $r) {
                    $participantes = json_decode($r->participantes, true);
                    if (is_array($participantes)) {
                        foreach ($participantes as $part) {
                            $clase = $part['clase'] ?? null;
                            if ($clase && isset($puntos[$clase])) {
                                $puntos[$clase] += floatval($part['puntos'] ?? 0);
                            }
                        }
                    }
                }
            } else {
                $rows_a = $wpdb->get_results($wpdb->prepare(
                    "SELECT equipo_a, SUM(pts_a) AS puntos FROM {$p}lc_deportes WHERE deporte=%s{$range_sql} GROUP BY equipo_a",
                    array_merge([$dep['value']], $range_params)
                ));
                foreach ($rows_a as $r) {
                    if (isset($puntos[$r->equipo_a])) $puntos[$r->equipo_a] += intval($r->puntos);
                }

                $rows_b = $wpdb->get_results($wpdb->prepare(
                    "SELECT equipo_b, SUM(pts_b) AS puntos FROM {$p}lc_deportes WHERE deporte=%s{$range_sql} GROUP BY equipo_b",
                    array_merge([$dep['value']], $range_params)
                ));
                foreach ($rows_b as $r) {
                    if (isset($puntos[$r->equipo_b])) $puntos[$r->equipo_b] += intval($r->puntos);
                }
            }

            foreach ($clases as $clase) {
                $totales[$clase]['deportes'] += $puntos[$clase];
            }

            $ranking = [];
            foreach ($clases as $clase) {
                $ranking[] = ['clase' => $clase, 'puntos' => $puntos[$clase], 'escudo_url' => $escudos[$clase]];
            }
            usort($ranking, function($a, $b) { return $b['puntos'] <=> $a['puntos']; });

            // Modo "alumno A vs alumno B": los puntos del enfrentamiento van a la
            // clase (ya contados arriba) y también al alumno que compitió.
            $ranking_alumnos = [];
            if ($modo === 'alumnos') {
                $puntos_alumno = [];
                $filas = $wpdb->get_results($wpdb->prepare(
                    "SELECT alumno_a, alumno_b, pts_a, pts_b FROM {$p}lc_deportes WHERE deporte=%s{$range_sql}",
                    array_merge([$dep['value']], $range_params)
                ));
                foreach ($filas as $f) {
                    foreach ([[(int)$f->alumno_a, (int)$f->pts_a], [(int)$f->alumno_b, (int)$f->pts_b]] as $par) {
                        if (!$par[0]) continue;
                        $puntos_alumno[$par[0]] = ($puntos_alumno[$par[0]] ?? 0) + $par[1];
                        $puntos_alumno_global[$par[0]] = ($puntos_alumno_global[$par[0]] ?? 0) + $par[1];
                    }
                }
                $ranking_alumnos = self::ranking_alumnos($puntos_alumno, $alumnos_map);
            }

            $por_deporte[] = [
                'value' => $dep['value'],
                'label' => $dep['label'],
                'modo' => $modo,
                'ranking' => $ranking,
                'ranking_alumnos' => $ranking_alumnos,
            ];
        }

        // 3. PARTES: cada fila de lc_partes_alumnos es un parte de un alumno. Se
        // cuentan por clase (para la tabla general) y por alumno (para su
        // ranking). Los puntos se aplican más abajo, con la configuración
        // vigente de Competición > Partes.
        $pts_partes_cfg = LC_Competiciones::get_partes();
        $clave_parte = [
            'positivo' => 'partes_positivos',
            'leve'     => 'partes_leves',
            'grave'    => 'partes_graves',
        ];
        $puntos_alumno_partes = [];

        $partes_data = self::get_results_ranged(
            "SELECT clase, alumno_id, tipo, COUNT(*) AS n
             FROM {$p}lc_partes_alumnos WHERE 1=1{$range_sql} GROUP BY clase, alumno_id, tipo",
            $range_params,
            ARRAY_A
        );
        foreach ($partes_data as $row) {
            $tipo = $row['tipo'];
            if (!isset($clave_parte[$tipo])) continue;
            $n = (int) $row['n'];

            if (isset($totales[$row['clase']])) {
                $totales[$row['clase']][$clave_parte[$tipo]] += $n;
            }

            $alumno_id = (int) $row['alumno_id'];
            if ($alumno_id) {
                $suma = $n * (int) $pts_partes_cfg[$tipo];
                $puntos_alumno_partes[$alumno_id] = ($puntos_alumno_partes[$alumno_id] ?? 0) + $suma;
                $puntos_alumno_global[$alumno_id] = ($puntos_alumno_global[$alumno_id] ?? 0) + $suma;
            }
        }

        // 4. BIBLIOTECA: ranking por cada actividad individual; el agregado "biblioteca"
        // de la tabla general es la suma de todos los rankings.
        $por_actividad = [];
        foreach ($actividades_list as $act) {
            $puntos = array_fill_keys($clases, 0);
            $puntos_alumno_act = [];
            $modo = $act['modo'] ?? 'participantes';

            if ($modo === 'enfrentamiento' || $modo === 'alumnos') {
                // No se guarda pts_a/pts_b (la tabla no tiene esas columnas): se
                // calculan aquí a partir de "ganador" con la configuración vigente.
                $pts_victoria = $act['pts_victoria'] ?? 3;
                $pts_empate   = $act['pts_empate'] ?? 1;
                $pts_derrota  = $act['pts_derrota'] ?? 0;

                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT clase_a, clase_b, alumno_a, alumno_b, ganador FROM {$p}lc_biblioteca WHERE actividad=%s{$range_sql}",
                    array_merge([$act['value']], $range_params)
                ));
                foreach ($rows as $r) {
                    if (!$r->clase_a || !$r->clase_b) continue;
                    if ($r->ganador === 'clase-a') {
                        $pa = $pts_victoria; $pb = $pts_derrota;
                    } elseif ($r->ganador === 'clase-b') {
                        $pa = $pts_derrota; $pb = $pts_victoria;
                    } else {
                        $pa = $pts_empate; $pb = $pts_empate;
                    }
                    if (isset($puntos[$r->clase_a])) $puntos[$r->clase_a] += $pa;
                    if (isset($puntos[$r->clase_b])) $puntos[$r->clase_b] += $pb;

                    if ($modo === 'alumnos') {
                        foreach ([[(int)$r->alumno_a, $pa], [(int)$r->alumno_b, $pb]] as $par) {
                            if (!$par[0]) continue;
                            $puntos_alumno_act[$par[0]] = ($puntos_alumno_act[$par[0]] ?? 0) + $par[1];
                            $puntos_alumno_global[$par[0]] = ($puntos_alumno_global[$par[0]] ?? 0) + $par[1];
                        }
                    }
                }
            } else {
                // Los participantes reparten los puntos de su actividad entre sus clases.
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT participantes, puntos FROM {$p}lc_biblioteca WHERE actividad=%s{$range_sql}",
                    array_merge([$act['value']], $range_params)
                ));
                foreach ($rows as $r) {
                    $participantes = json_decode($r->participantes, true);
                    if (is_array($participantes) && count($participantes) > 0) {
                        $reparto = intval($r->puntos) / count($participantes);
                        foreach ($participantes as $part) {
                            $clase = $part['clase'] ?? null;
                            if ($clase && isset($puntos[$clase])) {
                                $puntos[$clase] += $reparto;
                            }
                        }
                    }
                }
            }

            foreach ($clases as $clase) {
                $totales[$clase]['biblioteca'] += $puntos[$clase];
            }

            $ranking = [];
            foreach ($clases as $clase) {
                $ranking[] = ['clase' => $clase, 'puntos' => round($puntos[$clase], 2), 'escudo_url' => $escudos[$clase]];
            }
            usort($ranking, function($a, $b) { return $b['puntos'] <=> $a['puntos']; });

            $por_actividad[] = [
                'value' => $act['value'],
                'label' => $act['label'],
                'modo' => $modo,
                'ranking' => $ranking,
                'ranking_alumnos' => $modo === 'alumnos' ? self::ranking_alumnos($puntos_alumno_act, $alumnos_map) : [],
            ];
        }

        // 5. EXTRAORDINARIAS: suma directa de puntos por clase.
        $ext_data = self::get_results_ranged(
            "SELECT clase, SUM(puntos) as total_puntos
             FROM {$p}lc_extraordinarias WHERE 1=1{$range_sql} GROUP BY clase",
            $range_params
        );
        foreach ($ext_data as $row) {
            if (isset($totales[$row->clase])) {
                $totales[$row->clase]['extraordinarias'] = intval($row->total_puntos);
            }
        }

        // 6. TIEMPO DE APRENDIZAJE (mensual): acotado por meses (ver compute_range).
        // Sin tope superior (temporada en curso, que es lo que usa "General") no
        // se filtra por arriba: antes se tomaba el inicio también como fin y solo
        // contaba el primer mes de la temporada.
        $ta_sql = '';
        $ta_params = [];
        if ($range['m_from']) {
            $from_ym = $range['m_from'];
            $ta_sql .= " AND (iso_year > %d OR (iso_year = %d AND iso_month >= %d))";
            array_push($ta_params, $from_ym['year'], $from_ym['year'], $from_ym['month']);
        }
        if ($range['m_to']) {
            $to_ym = $range['m_to'];
            $ta_sql .= " AND (iso_year < %d OR (iso_year = %d AND iso_month <= %d))";
            array_push($ta_params, $to_ym['year'], $to_ym['year'], $to_ym['month']);
        }
        $ta_data = self::get_results_ranged(
            "SELECT clase, SUM(puntos) as p FROM {$p}lc_tiempo_aprendizaje WHERE 1=1{$ta_sql} GROUP BY clase",
            $ta_params,
            ARRAY_A
        );
        foreach ($ta_data as $row) {
            if (isset($totales[$row['clase']])) {
                $totales[$row['clase']]['tiempo_aprendizaje'] = (int) $row['p'];
            }
        }

        // Totales finales y orden
        $pts_partes = $pts_partes_cfg; // ya leída al contar los partes
        foreach ($totales as &$t) {
            $t['deportes'] = round($t['deportes'], 2);
            $t['biblioteca'] = round($t['biblioteca'], 2);
            $t['partes'] = ($t['partes_positivos'] * $pts_partes['positivo'])
                         + ($t['partes_leves'] * $pts_partes['leve'])
                         + ($t['partes_graves'] * $pts_partes['grave']);
            $t['total'] = $t['semaforo'] + $t['deportes'] + $t['partes'] + $t['extraordinarias'] + $t['biblioteca'] + $t['tiempo_aprendizaje'];
        }
        unset($t);

        uasort($totales, function($a, $b) { return $b['total'] <=> $a['total']; });

        return [
            'ok' => true,
            'periodo' => $periodo,
            'alumnos' => self::ranking_alumnos($puntos_alumno_global, $alumnos_map),
            'alumnos_partes' => self::ranking_alumnos($puntos_alumno_partes, $alumnos_map),
            'trimestres' => self::trimestres_visibles(),
            'temporadas' => LC_Competiciones::get_temporadas(),
            'temporada_activa' => LC_Competiciones::get_temporada_activa(),
            'clasificacion' => array_values($totales),
            'por_deporte' => $por_deporte,
            'por_actividad' => $por_actividad,
            'timestamp' => current_time('mysql'),
        ];
    }
}
