<?php
/**
 * Pruebas de la autenticación (LC_Auth) sin WordPress: se simulan las pocas
 * funciones de WP que usa la clase (opciones, transients, wp_mail...).
 *
 *   php tests/auth-test.php
 */

define('ABSPATH', __DIR__);
define('DAY_IN_SECONDS', 86400);
define('MINUTE_IN_SECONDS', 60);
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '');

// ---------------------------------------------------------------- simulación WP
$GLOBALS['opciones'] = [];
$GLOBALS['transients'] = [];
$GLOBALS['correos'] = [];
$GLOBALS['ssl'] = true;
$GLOBALS['entorno'] = 'production';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';

function get_option($k, $d = false) { return $GLOBALS['opciones'][$k] ?? $d; }
function update_option($k, $v, $autoload = null) { $GLOBALS['opciones'][$k] = $v; return true; }
function get_transient($k) { return $GLOBALS['transients'][$k] ?? false; }
function set_transient($k, $v, $ttl = 0) { $GLOBALS['transients'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['transients'][$k]); return true; }
function sanitize_email($e) { return filter_var(trim((string) $e), FILTER_SANITIZE_EMAIL); }
function is_email($e) { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_unslash($s) { return $s; }
function current_time($t) { return date('Y-m-d H:i:s'); }
function wp_json_encode($d) { return json_encode($d); }
function wp_salt($s) { return 'sal-de-prueba'; }
function is_ssl() { return $GLOBALS['ssl']; }
function wp_get_environment_type() { return $GLOBALS['entorno']; }
function get_bloginfo($k) { return 'Centro de prueba'; }
function wp_specialchars_decode($s) { return $s; }
function wp_mail($to, $asunto, $cuerpo) { $GLOBALS['correos'][] = compact('to', 'asunto', 'cuerpo'); return true; }

class WP_REST_Request {
    private $h = [];
    public function get_header($k) { return $this->h[$k] ?? null; }
    public function set_header($k, $v) { $this->h[$k] = $v; }
}
class LC_Horario { public static function profesor_por_email($e) { return $e === 'semaforo@centro.edu' ? ['iniciales' => 'X'] : null; } }

// setcookie() no puede enviar cabeceras en CLI una vez hay salida: se ignora.
ob_start();
require __DIR__ . '/../includes/class/auth.php';

// ---------------------------------------------------------------- utilidades
$fallos = 0; $total = 0;
function ok($cond, $desc) {
    global $fallos, $total; $total++;
    if (!$cond) $fallos++;
    fwrite(STDOUT, ($cond ? "  ✓ " : "  ✗ ") . $desc . "\n");
}
function ultimo_codigo() {
    $c = end($GLOBALS['correos']);
    return $c && preg_match('/\b(\d{6})\b/', $c['cuerpo'], $m) ? $m[1] : null;
}
function reiniciar() {
    $GLOBALS['transients'] = []; $GLOBALS['correos'] = [];
    $GLOBALS['opciones'] = ['lc_usuarios' => [['email' => 'profe@centro.edu']]];
}
function con_cookie($tok) { $_COOKIE[LC_Auth::COOKIE] = $tok; return new WP_REST_Request(); }

// ---------------------------------------------------------------- pruebas
fwrite(STDOUT, "Código de acceso\n");
reiniciar();
LC_Auth::solicitar_codigo('desconocido@centro.edu');
ok(count($GLOBALS['correos']) === 0, 'un email sin acceso no recibe código');

LC_Auth::solicitar_codigo('Profe@Centro.edu');
$cod = ultimo_codigo();
ok($cod !== null, 'un email autorizado recibe un código de 6 dígitos');
$guardado = json_encode($GLOBALS['transients']);
ok(strpos($guardado, $cod) === false, 'el código no se guarda en claro');

LC_Auth::solicitar_codigo('profe@centro.edu');
ok(count($GLOBALS['correos']) === 1, 'no se reenvía otro código antes de un minuto');

ok(!LC_Auth::verificar_codigo('profe@centro.edu', '000000') || $cod === '000000', 'un código erróneo no vale');
ok(LC_Auth::verificar_codigo('profe@centro.edu', $cod), 'el código correcto vale');
ok(!LC_Auth::verificar_codigo('profe@centro.edu', $cod), 'el código es de un solo uso');

fwrite(STDOUT, "Fuerza bruta\n");
reiniciar();
LC_Auth::solicitar_codigo('profe@centro.edu');
$cod = ultimo_codigo();
$malo = $cod === '111111' ? '222222' : '111111';
for ($i = 0; $i < LC_Auth::CODIGO_INTENTOS; $i++) LC_Auth::verificar_codigo('profe@centro.edu', $malo);
ok(!LC_Auth::verificar_codigo('profe@centro.edu', $cod), 'tras 5 fallos el código se anula aunque luego se acierte');

reiniciar();
$permitidas = 0;
for ($i = 0; $i < LC_Auth::IP_MAX + 5; $i++) if (LC_Auth::ip_permitida()) $permitidas++;
ok($permitidas === LC_Auth::IP_MAX, 'límite de ' . LC_Auth::IP_MAX . ' peticiones por IP');

fwrite(STDOUT, "Sesión\n");
reiniciar();
$tok = LC_Auth::mint_token('profe@centro.edu');
ok(LC_Auth::only_allowed(con_cookie($tok)), 'la cookie de sesión da acceso');

$r = new WP_REST_Request(); unset($_COOKIE[LC_Auth::COOKIE]);
$r->set_header('authorization', 'Bearer ' . $tok);
ok(!LC_Auth::only_allowed($r), 'el token en cabecera Authorization ya no se acepta');

$manipulado = rtrim(strtr(base64_encode('{"e":"profe@centro.edu","t":' . time() . ',"v":0}|' . str_repeat('a', 64)), '+/', '-_'), '=');
ok(!LC_Auth::only_allowed(con_cookie($manipulado)), 'un token con firma falsa no vale');

LC_Auth::revocar_sesiones('profe@centro.edu');
ok(!LC_Auth::only_allowed(con_cookie($tok)), 'tras cerrar sesión el token copiado deja de valer');
$nuevo = LC_Auth::mint_token('profe@centro.edu');
ok(LC_Auth::only_allowed(con_cookie($nuevo)), 'una sesión nueva sí vale');

$GLOBALS['opciones']['lc_usuarios'] = [];
ok(!LC_Auth::only_allowed(con_cookie($nuevo)), 'dar de baja al usuario corta su sesión al momento');

fwrite(STDOUT, "HTTPS\n");
$GLOBALS['ssl'] = false; $GLOBALS['entorno'] = 'production';
ok(LC_Auth::https_requerido(), 'en producción sin HTTPS se bloquea el login');
$GLOBALS['entorno'] = 'local';
ok(!LC_Auth::https_requerido(), 'en local se permite sin HTTPS');

ob_end_clean();
fwrite(STDOUT, "\n" . ($total - $fallos) . "/{$total} pruebas correctas\n");
exit($fallos ? 1 : 0);
