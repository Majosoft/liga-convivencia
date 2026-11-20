<?php
/**
 * Pruebas de los informes de IA (LC_Informes_IA) sin WordPress ni red: se
 * simulan las funciones de WP y la respuesta de la API.
 *
 *   php tests/informes-ia-test.php
 */

define('ABSPATH', __DIR__);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);

$GLOBALS['opciones'] = [];
$GLOBALS['peticion'] = null;
$GLOBALS['respuesta'] = null;

function get_option($k, $d = false) { return $GLOBALS['opciones'][$k] ?? $d; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function wp_timezone() { return new DateTimeZone('Europe/Madrid'); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function is_wp_error($x) { return $x instanceof WP_Error; }
function wp_remote_post($url, $args) { $GLOBALS['peticion'] = ['url' => $url] + $args; return $GLOBALS['respuesta']; }
function wp_remote_retrieve_response_code($r) { return $r['code']; }
function wp_remote_retrieve_body($r) { return $r['body']; }
class WP_Error {
    private $m;
    public function __construct($c, $m) { $this->m = $m; }
    public function get_error_message() { return $this->m; }
}

require __DIR__ . '/../includes/class/informes-ia.php';

$fallos = 0; $total = 0;
function ok($cond, $desc) {
    global $fallos, $total; $total++;
    if (!$cond) $fallos++;
    fwrite(STDOUT, ($cond ? "  ✓ " : "  ✗ ") . $desc . "\n");
}
function privado($metodo, ...$args) {
    $m = new ReflectionMethod('LC_Informes_IA', $metodo);
    $m->setAccessible(true);
    return $m->invoke(null, ...$args);
}
function respuesta($code, $json) { $GLOBALS['respuesta'] = ['code' => $code, 'body' => json_encode($json)]; }

fwrite(STDOUT, "Periodos\n");
ok(LC_Informes_IA::semana_anterior('2026-11-23') === [2026, 47], 'lunes 23/11/2026 → semana 47');
ok(LC_Informes_IA::semana_anterior('2027-01-04') === [2026, 53], 'cambio de año ISO: 4/1/2027 → semana 53 de 2026');
ok(LC_Informes_IA::mes_anterior('2026-12-01') === [2026, 11], '1/12/2026 → noviembre');
ok(LC_Informes_IA::mes_anterior('2027-01-15') === [2026, 12], 'enero → diciembre del año anterior');
ok(privado('periodo_previo', 'semanal', 2027, 1) === [2026, 53], 'semana previa a la 1 de 2027 es la 53 de 2026');
ok(privado('periodo_previo', 'mensual', 2027, 1) === [2026, 12], 'mes previo a enero es diciembre');
ok(LC_Informes_IA::etiqueta('mensual', 2026, 11) === 'Noviembre de 2026', 'etiqueta mensual');
ok(LC_Informes_IA::etiqueta('semanal', 2026, 47) === 'Semana 47 · 16 noviembre – 20 noviembre 2026', 'etiqueta semanal');

fwrite(STDOUT, "Privacidad\n");
$filas = privado('filas_por_clase', ['clasificacion' => [
    ['clase' => '1ºA', 'total' => 5, 'semaforo' => 3, 'escudo_url' => 'http://x/e.png', 'nombre' => 'Lucía', 'alumno' => 'Pablo'],
    ['clase' => '1ºB', 'total' => 9, 'semaforo' => 1],
]]);
ok($filas[0]['clase'] === '1ºB', 'ordena las clases por total');
ok(!isset($filas[1]['nombre']) && !isset($filas[1]['alumno']) && !isset($filas[1]['escudo_url']), 'solo pasan los campos agregados de la lista blanca');

fwrite(STDOUT, "Markdown seguro\n");
$html = LC_Informes_IA::markdown_a_html("## Resumen\nTexto con **negrita** y <script>alert(1)</script>\n\n- uno\n- <img src=x onerror=alert(1)>\n\n1. primero");
ok(strpos($html, '<script>') === false && strpos($html, '<img') === false, 'el HTML del modelo se escapa');
ok(strpos($html, '<h3>Resumen</h3>') !== false, '## se convierte en encabezado');
ok(strpos($html, '<strong>negrita</strong>') !== false, '**negrita**');
ok(substr_count($html, '<li>') === 3 && strpos($html, '<ol>') !== false, 'listas con viñetas y numeradas');

fwrite(STDOUT, "Llamada a la API\n");
$r = privado('llamar_claude', 'sistema', 'datos');
ok($r instanceof WP_Error, 'sin clave no se llama a la API');
ok($GLOBALS['peticion'] === null, '…y no sale ninguna petición');

$GLOBALS['opciones']['lc_ia_api_key'] = 'sk-ant-prueba';
respuesta(200, ['model' => 'claude-opus-5-5', 'stop_reason' => 'end_turn',
    'content' => [['type' => 'thinking', 'thinking' => ''], ['type' => 'text', 'text' => '## Resumen\nTodo bien.']],
    'usage' => ['input_tokens' => 3000, 'output_tokens' => 900]]);
$r = privado('llamar_claude', 'sistema', 'datos');
$p = $GLOBALS['peticion'];
$body = json_decode($p['body'], true);
ok($p['url'] === 'https://api.anthropic.com/v1/messages', 'endpoint de mensajes');
ok($p['headers']['x-api-key'] === 'sk-ant-prueba' && $p['headers']['anthropic-version'] === '2026-10-21', 'cabeceras de clave y versión');
ok($body['model'] === 'claude-opus-5-5' && $body['fallbacks'] === 'default', 'modelo y respaldo por defecto');
ok($p['headers']['anthropic-beta'] === 'server-side-fallback-2026-07-01', 'cabecera beta del respaldo');
ok(!isset($body['temperature']) && !isset($body['thinking']), 'sin parámetros que la API rechaza');
ok(is_array($r) && $r['texto'] === '## Resumen\nTodo bien.', 'toma solo los bloques de texto');
ok($r['tokens_in'] === 3000 && $r['tokens_out'] === 900, 'guarda los tokens para el coste');

respuesta(200, ['stop_reason' => 'refusal', 'content' => []]);
ok(privado('llamar_claude', 's', 'd') instanceof WP_Error, 'un rechazo se informa como error');
respuesta(401, ['type' => 'error', 'error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']]);
$r = privado('llamar_claude', 's', 'd');
ok($r instanceof WP_Error && strpos($r->get_error_message(), 'invalid x-api-key') !== false, 'un error HTTP muestra el mensaje de la API');

fwrite(STDOUT, "\n" . ($total - $fallos) . "/{$total} pruebas correctas\n");
exit($fallos ? 1 : 0);
