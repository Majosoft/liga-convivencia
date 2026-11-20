<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Informes de IA para el administrador (1.2).
 *
 * Cada semana y cada mes, Claude redacta un informe de la Liga a partir de los
 * puntos de cada clase: resumen, qué ha cambiado respecto al periodo anterior,
 * qué clases conviene vigilar y propuestas concretas. Se ve en
 * Liga Convivencia > Informes IA, solo para administradores.
 *
 * Privacidad: a la API solo se envían DATOS AGREGADOS POR CLASE (puntos por
 * competición, número de partes y de gomets). Nunca nombres de alumnos ni de
 * profesores: la lista de campos enviados es explícita (ver datos_periodo()).
 *
 * Funcionamiento:
 * - Una tarea diaria de WP-Cron comprueba si falta el informe de la semana
 *   pasada o del mes pasado, y lo genera (uno por ejecución).
 * - El botón "Generar ahora" programa la misma tarea para ya, en vez de llamar
 *   a la API dentro de la petición del admin: así el tiempo de respuesta del
 *   modelo no choca con el límite de ejecución de PHP del hosting.
 * - La llamada usa la API de mensajes de Anthropic por HTTP (wp_remote_post):
 *   el plugin se instala copiando una carpeta, sin Composer, y el SDK oficial
 *   de PHP exige Composer y PHP 8.1+.
 */
class LC_Informes_IA {

    const OPT_KEY    = 'lc_ia_api_key';     // clave de la API (si no está en wp-config.php)
    const OPT_ACTIVO = 'lc_ia_activo';      // generación automática semanal y mensual
    const CRON       = 'lc_informes_ia';    // tarea diaria
    const CRON_YA    = 'lc_informes_ia_ya'; // tarea puntual de "Generar ahora"
    const LOCK       = 'lc_ia_lock';
    const PAGE       = 'liga-creus-informes';

    const MODELO     = 'claude-opus-5-5';
    const API_URL    = 'https://api.anthropic.com/v1/messages';
    const API_VERSION = '2026-10-21';
    // Precio por millón de tokens (entrada / salida), para mostrar el coste.
    const PRECIO_IN  = 4.0;
    const PRECIO_OUT = 20.0;

    // ------------------------------------------------------------------ arranque

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu'], 20);
        add_action('admin_init', [__CLASS__, 'maybe_handle_action']);
        add_action(self::CRON, [__CLASS__, 'comprobar_pendientes']);
        add_action(self::CRON_YA, [__CLASS__, 'generar'], 10, 3);
        if (get_option(self::OPT_ACTIVO) && !wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON);
        }
    }

    public static function desactivar() {
        wp_clear_scheduled_hook(self::CRON);
    }

    public static function tabla() {
        global $wpdb;
        return $wpdb->prefix . 'lc_informes';
    }

    public static function create_tables($wpdb, $cc) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $t = self::tabla();
        // Un informe por tipo y periodo: regenerarlo sustituye al anterior.
        dbDelta("CREATE TABLE {$t} (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          tipo VARCHAR(10) NOT NULL,
          iso_year SMALLINT NOT NULL,
          num TINYINT NOT NULL,
          periodo VARCHAR(100) NOT NULL DEFAULT '',
          estado VARCHAR(12) NOT NULL DEFAULT 'generando',
          contenido MEDIUMTEXT NULL,
          error TEXT NULL,
          modelo VARCHAR(60) NOT NULL DEFAULT '',
          tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
          tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL,
          PRIMARY KEY (id),
          UNIQUE KEY tipo_periodo (tipo, iso_year, num)
        ) $cc;");
    }

    // ------------------------------------------------------------------ clave

    /**
     * La clave se puede definir en wp-config.php (LC_ANTHROPIC_API_KEY), que es
     * lo recomendable: así no queda en la base de datos.
     */
    public static function api_key() {
        if (defined('LC_ANTHROPIC_API_KEY') && LC_ANTHROPIC_API_KEY) return LC_ANTHROPIC_API_KEY;
        return (string) get_option(self::OPT_KEY, '');
    }

    // ------------------------------------------------------------------ periodos

    /** Semana ISO anterior a la actual (o a $hoy). */
    public static function semana_anterior($hoy = null) {
        $d = new \DateTime($hoy ?: 'now', wp_timezone());
        $d->modify('-7 days');
        return [(int) $d->format('o'), (int) $d->format('W')];
    }

    /** Mes anterior al actual (o a $hoy). */
    public static function mes_anterior($hoy = null) {
        $d = new \DateTime($hoy ?: 'now', wp_timezone());
        $d->modify('first day of this month')->modify('-1 day');
        return [(int) $d->format('Y'), (int) $d->format('n')];
    }

    private static function periodo_previo($tipo, $year, $num) {
        if ($tipo === 'semanal') {
            $d = (new \DateTime())->setISODate($year, $num, 1)->modify('-7 days');
            return [(int) $d->format('o'), (int) $d->format('W')];
        }
        $d = new \DateTime(sprintf('%04d-%02d-01', $year, $num));
        $d->modify('-1 month');
        return [(int) $d->format('Y'), (int) $d->format('n')];
    }

    const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                   'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function etiqueta($tipo, $year, $num) {
        if ($tipo === 'mensual') return ucfirst(self::MESES[$num]) . ' de ' . $year;
        $lunes = (new \DateTime())->setISODate($year, $num, 1);
        $viernes = (new \DateTime())->setISODate($year, $num, 5);
        return sprintf('Semana %d · %s – %s', $num,
            $lunes->format('j') . ' ' . self::MESES[(int) $lunes->format('n')],
            $viernes->format('j') . ' ' . self::MESES[(int) $viernes->format('n')] . ' ' . $viernes->format('Y'));
    }

    // ------------------------------------------------------------------ datos

    /** Clasificación de un periodo, reutilizando el mismo cálculo que la web pública. */
    private static function clasificacion($params) {
        $req = new \WP_REST_Request('GET', '/liga-creus/v1/clasificacion');
        foreach ($params as $k => $v) $req->set_param($k, $v);
        $res = LC_REST_Clasificacion::get_clasificacion($req);
        return $res instanceof \WP_REST_Response ? $res->get_data() : (array) $res;
    }

    /**
     * Solo campos agregados por clase. Lista blanca explícita: aunque la
     * clasificación devuelva más cosas, aquí no pasa nada con nombres.
     */
    private static function filas_por_clase($clasif) {
        $campos = ['clase', 'total', 'semaforo', 'deportes', 'biblioteca', 'extraordinarias',
                   'tiempo_aprendizaje', 'partes', 'partes_positivos', 'partes_leves', 'partes_graves'];
        $out = [];
        foreach ((array) ($clasif['clasificacion'] ?? []) as $fila) {
            $out[] = array_intersect_key($fila, array_flip($campos));
        }
        usort($out, function($a, $b) { return ($b['total'] ?? 0) <=> ($a['total'] ?? 0); });
        return $out;
    }

    /** Gomets verdes / amarillos / rojos por clase entre dos fechas. */
    private static function gomets_por_clase($desde, $hasta) {
        global $wpdb; $p = $wpdb->prefix;
        $filas = $wpdb->get_results($wpdb->prepare(
            "SELECT g.color, h.clases FROM {$p}lc_gomets g JOIN {$p}lc_horario h ON h.id = g.sesion_id
             WHERE g.fecha BETWEEN %s AND %s", $desde, $hasta), ARRAY_A);
        $cuenta = [];
        foreach ((array) $filas as $f) {
            foreach (array_filter(array_map('trim', explode(',', $f['clases']))) as $clase) {
                if (!isset($cuenta[$clase])) $cuenta[$clase] = ['clase' => $clase, 'verde' => 0, 'amarillo' => 0, 'rojo' => 0];
                if (isset($cuenta[$clase][$f['color']])) $cuenta[$clase][$f['color']]++;
            }
        }
        ksort($cuenta);
        return array_values($cuenta);
    }

    public static function datos_periodo($tipo, $year, $num) {
        $params = $tipo === 'semanal'
            ? ['periodo' => 'semanal', 'year' => $year, 'week' => $num]
            : ['periodo' => 'mensual', 'year' => $year, 'month' => $num];
        [$py, $pn] = self::periodo_previo($tipo, $year, $num);
        $params_prev = $tipo === 'semanal'
            ? ['periodo' => 'semanal', 'year' => $py, 'week' => $pn]
            : ['periodo' => 'mensual', 'year' => $py, 'month' => $pn];

        $actual = self::clasificacion($params);
        if ($tipo === 'semanal') {
            $desde = (new \DateTime())->setISODate($year, $num, 1)->format('Y-m-d');
            $hasta = (new \DateTime())->setISODate($year, $num, 5)->format('Y-m-d');
        } else {
            [$desde, $hasta] = $actual['semanas_mes'] ?? [sprintf('%04d-%02d-01', $year, $num), date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $num)))];
        }

        return [
            'tipo' => $tipo,
            'periodo' => self::etiqueta($tipo, $year, $num),
            'fechas' => ['desde' => $desde, 'hasta' => $hasta],
            'puntuacion' => [
                'gomets_semaforo' => LC_Competiciones::get_semaforo(),
                'partes' => LC_Competiciones::get_partes(),
            ],
            'clases_periodo' => self::filas_por_clase($actual),
            'clases_periodo_anterior' => self::filas_por_clase(self::clasificacion($params_prev)),
            'clasificacion_temporada' => self::filas_por_clase(self::clasificacion(['periodo' => 'general'])),
            'gomets_periodo' => self::gomets_por_clase($desde, $hasta),
        ];
    }

    // ------------------------------------------------------------------ prompt

    private static function system_prompt($tipo) {
        $siguiente = $tipo === 'semanal' ? 'la próxima semana' : 'el próximo mes';
        $palabras = $tipo === 'semanal' ? 400 : 700;
        return <<<TXT
Eres el asistente de la coordinación de convivencia de un instituto de secundaria. El centro organiza la "Liga Convivencia": las clases compiten durante el curso y suman o restan puntos en varias competiciones. Tu informe lo lee el administrador de la Liga (un docente), que lo usará para decidir qué reforzar y con qué clases hablar.

Recibirás un JSON con:
- clases_periodo: puntos de cada clase en el periodo del informe, por competición. "semaforo" sale de los gomets que pone el profesorado en cada hora de clase (verde suma, amarillo y rojo restan; los valores exactos están en "puntuacion"). "partes" es el saldo de partes positivos, leves y graves, también desglosados. "tiempo_aprendizaje" es mensual (premia usar menos el ticket de baño), así que en un informe semanal puede valer 0 sin que signifique nada.
- clases_periodo_anterior: lo mismo para el periodo anterior, para comparar.
- clasificacion_temporada: la clasificación acumulada de la temporada.
- gomets_periodo: cuántos gomets verdes, amarillos y rojos ha recibido cada clase.

Reglas:
- Usa solo estos datos. No inventes causas ni hechos: si propones una explicación, preséntala como hipótesis que el profesorado puede comprobar.
- Si casi todo está a 0 o faltan datos, dilo al principio: probablemente aún no se han registrado, y es más útil avisar que analizar ceros.
- No hay datos de alumnos individuales ni del profesorado; no los menciones.
- Tono profesional, claro y constructivo; español de España. Reconoce lo que va bien, no solo lo que va mal.
- Menciona cifras concretas cuando apoyen una conclusión, sin repetir la tabla entera.

Formato: Markdown sencillo, sin tablas, sin título general, como máximo unas {$palabras} palabras, con estas secciones en este orden:
## Resumen
## Clasificación y cambios
## Lo que va bien
## Clases a vigilar
## Propuestas para {$siguiente}
En "Propuestas", 2 o 3 acciones concretas y realistas para un centro educativo, cada una ligada a algo que se ve en los datos.
TXT;
    }

    // ------------------------------------------------------------------ API

    /**
     * Llama a la API de mensajes. Devuelve ['texto', 'modelo', 'tokens_in',
     * 'tokens_out'] o un WP_Error.
     */
    private static function llamar_claude($system, $usuario) {
        $key = self::api_key();
        if ($key === '') return new \WP_Error('lc_ia_sin_clave', 'Falta la clave de la API de Anthropic.');

        $resp = wp_remote_post(self::API_URL, [
            'timeout' => 240,
            'headers' => [
                'content-type'      => 'application/json',
                'x-api-key'         => $key,
                'anthropic-version' => self::API_VERSION,
                // Si los filtros de seguridad del modelo rechazan la petición,
                // la API la repite sola con el modelo de respaldo recomendado.
                'anthropic-beta'    => 'server-side-fallback-2026-07-01',
            ],
            'body' => wp_json_encode([
                'model'         => self::MODELO,
                'max_tokens'    => 16000,
                'output_config' => ['effort' => 'medium'],
                'fallbacks'     => 'default',
                'system'        => $system,
                'messages'      => [['role' => 'user', 'content' => $usuario]],
            ]),
        ]);
        if (is_wp_error($resp)) return $resp;

        $codigo = wp_remote_retrieve_response_code($resp);
        $json = json_decode(wp_remote_retrieve_body($resp), true);
        if ($codigo !== 200 || !is_array($json)) {
            $msg = $json['error']['message'] ?? ('HTTP ' . $codigo);
            return new \WP_Error('lc_ia_http', 'La API respondió con un error: ' . $msg);
        }
        if (($json['stop_reason'] ?? '') === 'refusal') {
            return new \WP_Error('lc_ia_refusal', 'El modelo no ha generado el informe (rechazo de seguridad).');
        }

        $texto = '';
        foreach ((array) ($json['content'] ?? []) as $bloque) {
            if (($bloque['type'] ?? '') === 'text') $texto .= $bloque['text'];
        }
        if (trim($texto) === '') return new \WP_Error('lc_ia_vacio', 'La respuesta del modelo llegó vacía.');
        if (($json['stop_reason'] ?? '') === 'max_tokens') {
            $texto .= "\n\n*(El informe se ha cortado por longitud.)*";
        }
        return [
            'texto'      => $texto,
            'modelo'     => (string) ($json['model'] ?? self::MODELO),
            'tokens_in'  => (int) ($json['usage']['input_tokens'] ?? 0),
            'tokens_out' => (int) ($json['usage']['output_tokens'] ?? 0),
        ];
    }

    // ------------------------------------------------------------------ generación

    public static function existe($tipo, $year, $num) {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::tabla() . " WHERE tipo = %s AND iso_year = %d AND num = %d AND estado = 'ok'",
            $tipo, $year, $num));
    }

    /** Tarea diaria: genera el que falte (primero el semanal). */
    public static function comprobar_pendientes() {
        if (!get_option(self::OPT_ACTIVO) || self::api_key() === '') return;
        [$y, $w] = self::semana_anterior();
        if (!self::existe('semanal', $y, $w)) { self::generar('semanal', $y, $w); return; }
        [$y, $m] = self::mes_anterior();
        if (!self::existe('mensual', $y, $m)) self::generar('mensual', $y, $m);
    }

    public static function generar($tipo, $year, $num) {
        global $wpdb;
        if (!in_array($tipo, ['semanal', 'mensual'], true)) return;
        if (get_transient(self::LOCK)) return;           // ya hay uno en marcha
        set_transient(self::LOCK, 1, 5 * MINUTE_IN_SECONDS);
        if (function_exists('set_time_limit')) @set_time_limit(300);

        $t = self::tabla();
        $wpdb->replace($t, [
            'tipo' => $tipo, 'iso_year' => $year, 'num' => $num,
            'periodo' => self::etiqueta($tipo, $year, $num),
            'estado' => 'generando', 'contenido' => null, 'error' => null,
            'created_at' => current_time('mysql'),
        ]);

        $datos = self::datos_periodo($tipo, $year, $num);
        $usuario = "Informe " . $tipo . " de la Liga Convivencia: " . $datos['periodo'] . ".\n\nDatos:\n"
            . wp_json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $r = self::llamar_claude(self::system_prompt($tipo), $usuario);

        $donde = ['tipo' => $tipo, 'iso_year' => $year, 'num' => $num];
        if (is_wp_error($r)) {
            $wpdb->update($t, ['estado' => 'error', 'error' => $r->get_error_message()], $donde);
        } else {
            $wpdb->update($t, [
                'estado' => 'ok', 'contenido' => $r['texto'], 'modelo' => $r['modelo'],
                'tokens_in' => $r['tokens_in'], 'tokens_out' => $r['tokens_out'],
                'created_at' => current_time('mysql'),
            ], $donde);
        }
        delete_transient(self::LOCK);
    }

    // ------------------------------------------------------------------ Markdown

    /**
     * Markdown sencillo -> HTML. Todo se escapa ANTES de dar formato, así que el
     * texto del modelo nunca puede inyectar HTML en el admin.
     */
    public static function markdown_a_html($md) {
        $html = ''; $lista = null; $parrafo = [];
        $inline = function($s) {
            $s = esc_html($s);
            $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
            return preg_replace('/(?<![\*\w])\*(?!\s)(.+?)(?<!\s)\*(?![\*\w])/u', '<em>$1</em>', $s);
        };
        $cerrar = function() use (&$html, &$lista, &$parrafo) {
            if ($parrafo) { $html .= '<p>' . implode(' ', $parrafo) . '</p>'; $parrafo = []; }
            if ($lista) { $html .= "</{$lista}>"; $lista = null; }
        };
        foreach (preg_split('/\r?\n/', (string) $md) as $linea) {
            $l = trim($linea);
            if ($l === '') { $cerrar(); continue; }
            if (preg_match('/^(#{1,4})\s+(.*)$/u', $l, $m)) {
                $cerrar();
                $nivel = min(4, strlen($m[1]) + 1);
                $html .= "<h{$nivel}>" . $inline($m[2]) . "</h{$nivel}>";
            } elseif (preg_match('/^(?:[-*]|(\d+)[.)])\s+(.*)$/u', $l, $m)) {
                $tipo = $m[1] !== '' ? 'ol' : 'ul';
                if ($parrafo) { $html .= '<p>' . implode(' ', $parrafo) . '</p>'; $parrafo = []; }
                if ($lista !== $tipo) { if ($lista) $html .= "</{$lista}>"; $html .= "<{$tipo}>"; $lista = $tipo; }
                $html .= '<li>' . $inline($m[2]) . '</li>';
            } else {
                if ($lista) { $html .= "</{$lista}>"; $lista = null; }
                $parrafo[] = $inline($l);
            }
        }
        $cerrar();
        return $html;
    }

    // ------------------------------------------------------------------ admin

    public static function menu() {
        add_submenu_page('liga-creus', 'Informes IA', 'Informes IA', 'manage_options', self::PAGE, [__CLASS__, 'render']);
    }

    private static function url($args = []) {
        return add_query_arg(array_merge(['page' => self::PAGE], $args), admin_url('admin.php'));
    }

    public static function maybe_handle_action() {
        $action = $_POST['lc_ia_action'] ?? '';
        if (!in_array($action, ['guardar', 'generar'], true)) return;
        if (!current_user_can('manage_options')) return;
        check_admin_referer('lc_informes_ia');

        if ($action === 'guardar') {
            $key = trim(sanitize_text_field(wp_unslash($_POST['api_key'] ?? '')));
            if ($key !== '') update_option(self::OPT_KEY, $key, false);   // vacío = conservar la actual
            if (!empty($_POST['borrar_clave'])) delete_option(self::OPT_KEY);
            $activo = !empty($_POST['activo']);
            update_option(self::OPT_ACTIVO, $activo ? 1 : 0);
            if (!$activo) self::desactivar();
            wp_safe_redirect(self::url(['lc_msg' => 'guardado'])); exit;
        }

        $tipo = ($_POST['tipo'] ?? '') === 'mensual' ? 'mensual' : 'semanal';
        if (self::api_key() === '') { wp_safe_redirect(self::url(['lc_msg' => 'sin_clave'])); exit; }
        [$y, $n] = $tipo === 'semanal' ? self::semana_anterior() : self::mes_anterior();
        wp_schedule_single_event(time(), self::CRON_YA, [$tipo, $y, $n]);
        spawn_cron();
        wp_safe_redirect(self::url(['tab' => $tipo, 'lc_msg' => 'generando'])); exit;
    }

    private static function informes($tipo) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::tabla() . " WHERE tipo = %s ORDER BY iso_year DESC, num DESC LIMIT 60", $tipo), ARRAY_A) ?: [];
    }

    public static function render() {
        if (!current_user_can('manage_options')) return;
        $tab = ($_GET['tab'] ?? '') === 'mensual' ? 'mensual' : 'semanal';
        $avisos = [
            'guardado'  => ['success', 'Ajustes guardados.'],
            'generando' => ['info', 'Generando el informe. Tarda alrededor de un minuto: recarga la página en un momento.'],
            'sin_clave' => ['error', 'Añade primero la clave de la API de Anthropic.'],
        ];
        $msg = sanitize_key($_GET['lc_msg'] ?? '');

        echo '<div class="wrap"><h1>Informes IA</h1>';
        if (isset($avisos[$msg])) {
            echo '<div class="notice notice-' . esc_attr($avisos[$msg][0]) . '"><p>' . esc_html($avisos[$msg][1]) . '</p></div>';
        }
        echo '<p>Cada lunes se prepara el informe de la semana anterior y, a principios de mes, el del mes anterior. '
            . 'Los redacta <strong>Claude</strong> (Anthropic) a partir de los puntos de cada clase. '
            . '<strong>Solo se envían datos agregados por clase</strong>: ningún nombre de alumno ni de profesor sale del sitio.</p>';

        // Pestañas
        echo '<h2 class="nav-tab-wrapper">';
        foreach (['semanal' => 'Semanal', 'mensual' => 'Mensual'] as $k => $txt) {
            echo '<a class="nav-tab' . ($tab === $k ? ' nav-tab-active' : '') . '" href="' . esc_url(self::url(['tab' => $k])) . '">' . esc_html($txt) . '</a>';
        }
        echo '</h2>';

        $lista = self::informes($tab);
        $sel = isset($_GET['informe']) ? (int) $_GET['informe'] : 0;
        $actual = null;
        foreach ($lista as $inf) { if (!$actual || (int) $inf['id'] === $sel) $actual = $inf; if ((int) $inf['id'] === $sel) break; }

        echo '<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin-top:16px">';

        // Informe
        echo '<div class="card" style="max-width:820px;flex:1 1 520px;padding:8px 24px 16px">';
        if (!$actual) {
            echo '<p>Todavía no hay ningún informe ' . esc_html($tab) . '.</p>';
        } else {
            echo '<h2>' . esc_html($actual['periodo']) . '</h2>';
            if ($actual['estado'] === 'generando') {
                echo '<p><em>Generando… recarga la página en un minuto.</em></p>';
            } elseif ($actual['estado'] === 'error') {
                echo '<div class="notice notice-error inline"><p>No se pudo generar: ' . esc_html($actual['error']) . '</p></div>';
            } else {
                echo '<div class="lc-informe-ia">' . self::markdown_a_html($actual['contenido']) . '</div>';
                $coste = ($actual['tokens_in'] * self::PRECIO_IN + $actual['tokens_out'] * self::PRECIO_OUT) / 1e6;
                echo '<p class="description" style="margin-top:16px">Generado el ' . esc_html(mysql2date('j/m/Y H:i', $actual['created_at']))
                    . ' · ' . esc_html($actual['modelo'])
                    . ' · ' . number_format_i18n($actual['tokens_in'] + $actual['tokens_out']) . ' tokens'
                    . ' · coste aprox. ' . esc_html(number_format_i18n($coste, 3)) . ' $</p>';
                echo '<p class="description">Texto generado por IA a partir de los datos registrados: revísalo antes de compartirlo.</p>';
            }
        }
        echo '</div>';

        // Lateral: generar, histórico y ajustes
        echo '<div style="flex:0 1 300px">';
        echo '<div class="card" style="padding:8px 16px 16px"><h3>Generar ahora</h3>';
        echo '<form method="post">';
        wp_nonce_field('lc_informes_ia');
        echo '<input type="hidden" name="lc_ia_action" value="generar"><input type="hidden" name="tipo" value="' . esc_attr($tab) . '">';
        $etq = $tab === 'semanal' ? self::etiqueta('semanal', ...self::semana_anterior()) : self::etiqueta('mensual', ...self::mes_anterior());
        echo '<p class="description">Crea (o rehace) el informe de: <strong>' . esc_html($etq) . '</strong></p>';
        submit_button('Generar informe ' . $tab, 'primary', 'submit', false);
        echo '</form></div>';

        if (count($lista) > 1) {
            echo '<div class="card" style="padding:8px 16px 16px"><h3>Anteriores</h3><ul>';
            foreach ($lista as $inf) {
                $activo = $actual && $inf['id'] === $actual['id'];
                echo '<li>' . ($activo ? '<strong>' : '<a href="' . esc_url(self::url(['tab' => $tab, 'informe' => $inf['id']])) . '">')
                    . esc_html($inf['periodo']) . ($activo ? '</strong>' : '</a>')
                    . ($inf['estado'] !== 'ok' ? ' <em>(' . esc_html($inf['estado']) . ')</em>' : '') . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<div class="card" style="padding:8px 16px 16px"><h3>Ajustes</h3><form method="post">';
        wp_nonce_field('lc_informes_ia');
        echo '<input type="hidden" name="lc_ia_action" value="guardar">';
        if (defined('LC_ANTHROPIC_API_KEY') && LC_ANTHROPIC_API_KEY) {
            echo '<p>Clave de la API: <strong>definida en wp-config.php</strong>.</p>';
        } else {
            $hay = get_option(self::OPT_KEY, '') !== '';
            echo '<p><label for="lc-ia-key">Clave de la API de Anthropic</label><br>'
                . '<input type="password" id="lc-ia-key" name="api_key" autocomplete="off" style="width:100%" placeholder="'
                . esc_attr($hay ? '•••••••• (guardada; escribe otra para cambiarla)' : 'sk-ant-...') . '"></p>';
            if ($hay) echo '<p><label><input type="checkbox" name="borrar_clave" value="1"> Borrar la clave guardada</label></p>';
            echo '<p class="description">Más seguro: define <code>LC_ANTHROPIC_API_KEY</code> en wp-config.php y no se guardará en la base de datos.</p>';
        }
        echo '<p><label><input type="checkbox" name="activo" value="1"' . checked((bool) get_option(self::OPT_ACTIVO), true, false)
            . '> Generar automáticamente cada semana y cada mes</label></p>';
        submit_button('Guardar ajustes', 'secondary', 'submit', false);
        echo '</form></div>';

        echo '</div></div>';
        echo '<style>.lc-informe-ia h3{margin:1.4em 0 .4em}.lc-informe-ia p,.lc-informe-ia li{font-size:14px;line-height:1.6}.lc-informe-ia ul,.lc-informe-ia ol{margin-left:1.4em}.lc-informe-ia ul{list-style:disc}</style>';
        echo '</div>';
    }
}
