<?php
/**
 * Base común de las pruebas que necesitan base de datos.
 *
 * - Simula las funciones de WordPress que usa el plugin (opciones, transients,
 *   sanitización, peticiones REST…), sin cargar WordPress.
 * - $wpdb habla con una base de datos SQLite REAL en memoria, con las mismas
 *   tablas que crea el plugin: las consultas SQL del plugin se ejecutan de
 *   verdad. Las dos construcciones propias de MySQL que usa (FIND_IN_SET y
 *   ON DUPLICATE KEY UPDATE) se traducen o se registran como función.
 *
 * Uso en una prueba:
 *   require __DIR__ . '/bootstrap.php';
 *   lc_reset();                      // base de datos y opciones limpias
 *   ok($condicion, 'descripción');
 *   ...
 *   resumen();                       // imprime el total y sale con 0 o 1
 */

date_default_timezone_set('UTC'); // como WordPress

define('ABSPATH', __DIR__ . '/');
define('LC_PATH', dirname(__DIR__) . '/');
define('LC_URL', 'https://demo.test/wp-content/plugins/liga-convivencia/');
define('LC_VERSION', 'test');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('OBJECT', 'OBJECT');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');
define('COOKIEPATH', '/');
define('COOKIE_DOMAIN', '');

// ---------------------------------------------------------------- estado
$GLOBALS['lc_opciones'] = [];
$GLOBALS['lc_transients'] = [];
$GLOBALS['lc_correos'] = [];

// ---------------------------------------------------------------- funciones de WordPress
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['lc_opciones']) ? $GLOBALS['lc_opciones'][$k] : $d; }
function update_option($k, $v, $autoload = null) {
    $antes = $GLOBALS['lc_opciones'][$k] ?? null;
    $GLOBALS['lc_opciones'][$k] = $v;
    if ($antes !== $v) do_action('update_option_' . $k);
    return true;
}
function delete_option($k) { unset($GLOBALS['lc_opciones'][$k]); return true; }
function get_transient($k) { return $GLOBALS['lc_transients'][$k] ?? false; }
function set_transient($k, $v, $t = 0) { $GLOBALS['lc_transients'][$k] = $v; return true; }
function delete_transient($k) { unset($GLOBALS['lc_transients'][$k]); return true; }

$GLOBALS['lc_acciones'] = [];
function add_action($h, $cb, $p = 10, $n = 1) { $GLOBALS['lc_acciones'][$h][] = $cb; return true; }
function add_filter($h, $cb, $p = 10, $n = 1) { return true; }
function do_action($h, ...$args) { foreach ($GLOBALS['lc_acciones'][$h] ?? [] as $cb) call_user_func_array($cb, $args); }
function add_shortcode() { return true; }
function register_rest_route() { return true; }

function sanitize_text_field($s) { return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $s))); }
function sanitize_textarea_field($s) { return trim(strip_tags((string) $s)); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $s)); }
function sanitize_email($e) { return filter_var(trim((string) $e), FILTER_SANITIZE_EMAIL); }
function sanitize_title($s) { return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $s)), '-'); }
function is_email($e) { return (bool) filter_var($e, FILTER_VALIDATE_EMAIL); }
function wp_unslash($s) { return $s; }
function absint($n) { return abs((int) $n); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }
function current_time($t) { return gmdate('Y-m-d H:i:s'); }
function wp_timezone() { return new DateTimeZone('Europe/Madrid'); }
function wp_salt($s = '') { return 'sal-de-prueba'; }
function is_ssl() { return true; }
function wp_get_environment_type() { return 'production'; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return esc_html($s); }
function esc_url($s) { return (string) $s; }
function esc_js($s) { return addslashes((string) $s); }
function wp_get_attachment_image_url() { return false; }
function wp_get_current_user() { return new class { public $user_email = ''; public function exists() { return false; } }; }
function get_bloginfo($k = '') { return 'Centro de prueba'; }
function wp_specialchars_decode($s) { return $s; }
function wp_mail($to, $asunto, $cuerpo) { $GLOBALS['lc_correos'][] = compact('to', 'asunto', 'cuerpo'); return true; }
function date_i18n($f, $t = false) { return gmdate($f, $t ?: time()); }
function get_permalink($id) { return 'https://demo.test/clasificacion/'; }
function home_url($p = '') { return 'https://demo.test' . $p; }
function is_wp_error($x) { return $x instanceof WP_Error; }

class WP_Error {
    private $m;
    public function __construct($c = '', $m = '') { $this->m = $m; }
    public function get_error_message() { return $this->m; }
}

class WP_REST_Request implements ArrayAccess {
    private $params = [];
    private $method; private $route;
    public function __construct($method = 'GET', $route = '') { $this->method = $method; $this->route = $route; }
    public function get_param($k) { return $this->params[$k] ?? null; }
    public function set_param($k, $v) { $this->params[$k] = $v; }
    public function get_header($k) { return null; }
    public function get_method() { return $this->method; }
    public function get_route() { return $this->route; }
    public function offsetExists($k): bool { return isset($this->params[$k]); }
    public function offsetGet($k): mixed { return $this->params[$k] ?? null; }
    public function offsetSet($k, $v): void { $this->params[$k] = $v; }
    public function offsetUnset($k): void { unset($this->params[$k]); }
}

class WP_REST_Response {
    private $data; private $status; private $headers = [];
    public function __construct($data = null, $status = 200) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
    public function header($k, $v) { $this->headers[$k] = $v; }
    public function get_headers() { return $this->headers; }
}

/** Petición REST con parámetros. */
function req($params = [], $method = 'GET') {
    $r = new WP_REST_Request($method);
    foreach ($params as $k => $v) $r->set_param($k, $v);
    return $r;
}

/** Datos de una respuesta del plugin, venga como array o como WP_REST_Response. */
function datos($res) { return $res instanceof WP_REST_Response ? $res->get_data() : $res; }
function estado($res) { return $res instanceof WP_REST_Response ? $res->get_status() : 200; }

// ---------------------------------------------------------------- $wpdb sobre SQLite
class LC_Test_Wpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $posts = 'wp_posts';
    /** @var PDO */
    public $pdo;

    public function __construct() {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('FIND_IN_SET', function($aguja, $lista) {
            $i = array_search((string) $aguja, explode(',', (string) $lista), true);
            return $i === false ? 0 : $i + 1;
        }, 2);
    }

    public function esc_like($s) { return addcslashes($s, '_%\\'); }

    public function prepare($sql, ...$args) {
        if (count($args) === 1 && is_array($args[0])) $args = $args[0];
        $i = 0;
        return preg_replace_callback('/%%|%[sdf]/', function($m) use (&$i, $args) {
            if ($m[0] === '%%') return '%';
            $v = $args[$i++] ?? null;
            if ($m[0] === '%d') return (string) (int) $v;
            if ($m[0] === '%f') return (string) (float) $v;
            return $this->pdo->quote((string) $v);
        }, $sql);
    }

    /** Traduce lo poco de MySQL que usa el plugin. */
    private function traducir($sql) {
        $sql = preg_replace('/^\s*START TRANSACTION/i', 'BEGIN', $sql);
        if (preg_match('/ON DUPLICATE KEY UPDATE (.*)$/is', $sql, $m)) {
            $sets = preg_replace('/VALUES\((\w+)\)/i', 'excluded.$1', $m[1]);
            $sql = str_replace($m[0], 'ON CONFLICT DO UPDATE SET ' . $sets, $sql);
        }
        return $sql;
    }

    public function query($sql) {
        $this->last_error = '';
        try {
            $st = $this->pdo->query($this->traducir($sql));
            if (preg_match('/^\s*(SELECT|SHOW)/i', $sql)) return count($st->fetchAll());
            $this->insert_id = (int) $this->pdo->lastInsertId();
            return $st->rowCount();
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    public function get_results($sql, $output = OBJECT) {
        $this->last_error = '';
        try {
            $filas = $this->pdo->query($this->traducir($sql))->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return null;
        }
        if ($output === ARRAY_A) return $filas;
        if ($output === ARRAY_N) return array_map('array_values', $filas);
        return array_map(function($f) { return (object) $f; }, $filas);
    }
    public function get_row($sql, $output = OBJECT) { $r = $this->get_results($sql, $output); return $r ? $r[0] : null; }
    public function get_col($sql) { return array_map(function($f) { return reset($f); }, $this->get_results($sql, ARRAY_A) ?: []); }
    public function get_var($sql) { $r = $this->get_results($sql, ARRAY_A); return $r ? reset($r[0]) : null; }

    private function escribir($verbo, $tabla, $datos) {
        $cols = array_keys($datos);
        $sql = "$verbo INTO $tabla (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        $this->last_error = '';
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute(array_values($datos));
            $this->insert_id = (int) $this->pdo->lastInsertId();
            return $st->rowCount();
        } catch (PDOException $e) {
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    public function insert($tabla, $datos, $formatos = null) { return $this->escribir('INSERT', $tabla, $datos); }
    public function replace($tabla, $datos, $formatos = null) { return $this->escribir('REPLACE', $tabla, $datos); }

    public function update($tabla, $datos, $donde, $f = null, $fw = null) {
        $set = implode(',', array_map(function($c) { return "$c = ?"; }, array_keys($datos)));
        $w = implode(' AND ', array_map(function($c) { return "$c = ?"; }, array_keys($donde)));
        try {
            $st = $this->pdo->prepare("UPDATE $tabla SET $set WHERE $w");
            $st->execute(array_merge(array_values($datos), array_values($donde)));
            return $st->rowCount();
        } catch (PDOException $e) { $this->last_error = $e->getMessage(); return false; }
    }

    public function delete($tabla, $donde, $f = null) {
        $w = implode(' AND ', array_map(function($c) { return "$c = ?"; }, array_keys($donde)));
        try {
            $st = $this->pdo->prepare("DELETE FROM $tabla WHERE $w");
            $st->execute(array_values($donde));
            return $st->rowCount();
        } catch (PDOException $e) { $this->last_error = $e->getMessage(); return false; }
    }
}

/** Las tablas del plugin (activator.php y horario.php), en SQLite. */
function lc_esquema(PDO $pdo) {
    $id = 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    $pdo->exec("CREATE TABLE wp_lc_semaforo ($id, clase TEXT NOT NULL, iso_year INT NOT NULL, iso_week INT NOT NULL,
        gomets_verdes INT DEFAULT 0, gomets_amarillos INT DEFAULT 0, gomets_rojos INT DEFAULT 0, total INT DEFAULT 0,
        notas TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, created_by TEXT, UNIQUE(clase, iso_year, iso_week))");
    $pdo->exec("CREATE TABLE wp_lc_deportes ($id, deporte TEXT NOT NULL, iso_year INT NOT NULL, iso_week INT NOT NULL,
        equipo_a TEXT NOT NULL, equipo_b TEXT NOT NULL, marcador_a INT DEFAULT 0, marcador_b INT DEFAULT 0,
        pts_a INT DEFAULT 0, pts_b INT DEFAULT 0, alumno_a INT NOT NULL DEFAULT 0, alumno_b INT NOT NULL DEFAULT 0,
        participantes TEXT, notas TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, created_by TEXT)");
    $pdo->exec("CREATE TABLE wp_lc_biblioteca ($id, actividad TEXT NOT NULL, iso_year INT NOT NULL, iso_week INT NOT NULL,
        participantes TEXT NOT NULL DEFAULT '', puntos INT DEFAULT 0, clase_a TEXT, clase_b TEXT,
        alumno_a INT NOT NULL DEFAULT 0, alumno_b INT NOT NULL DEFAULT 0, ganador TEXT, notas TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, created_by TEXT)");
    $pdo->exec("CREATE TABLE wp_lc_extraordinarias ($id, nombre TEXT NOT NULL, iso_year INT NOT NULL, iso_week INT NOT NULL,
        clase TEXT NOT NULL, puntos INT NOT NULL DEFAULT 0, notas TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
        created_by TEXT, UNIQUE(nombre, iso_year, iso_week, clase))");
    $pdo->exec("CREATE TABLE wp_lc_tiempo_aprendizaje ($id, clase TEXT NOT NULL, iso_year INT NOT NULL, iso_month INT NOT NULL,
        puntos INT NOT NULL DEFAULT 0, created_by TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(clase, iso_year, iso_month))");
    $pdo->exec("CREATE TABLE wp_lc_alumnos ($id, nombre TEXT NOT NULL, clase TEXT NOT NULL, activo INT NOT NULL DEFAULT 1,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, created_by TEXT, UNIQUE(nombre, clase))");
    $pdo->exec("CREATE TABLE wp_lc_partes_alumnos ($id, alumno_id INT NOT NULL DEFAULT 0, clase TEXT NOT NULL,
        iso_year INT NOT NULL, iso_week INT NOT NULL, tipo TEXT NOT NULL, notas TEXT,
        created_at TEXT DEFAULT CURRENT_TIMESTAMP, created_by TEXT)");
    $pdo->exec("CREATE TABLE wp_lc_horario ($id, profesor TEXT NOT NULL, dia INT NOT NULL, hora INT NOT NULL,
        materia TEXT NOT NULL DEFAULT '', aula TEXT NOT NULL DEFAULT '', grupo TEXT NOT NULL, n INT NOT NULL DEFAULT 0,
        clases TEXT NOT NULL DEFAULT '', cuenta INT NOT NULL DEFAULT 1)");
    $pdo->exec("CREATE TABLE wp_lc_gomets ($id, sesion_id INT NOT NULL, fecha TEXT NOT NULL, profesor TEXT NOT NULL,
        color TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(sesion_id, fecha))");
}

/** Base de datos y estado limpios para cada bloque de pruebas. */
function lc_reset() {
    $GLOBALS['wpdb'] = new LC_Test_Wpdb();
    lc_esquema($GLOBALS['wpdb']->pdo);
    $GLOBALS['lc_opciones'] = [];
    $GLOBALS['lc_transients'] = [];
    $GLOBALS['lc_correos'] = [];
    $_COOKIE = [];
    // El hook que el plugin registra en init: cambiar los puntos del Semáforo rehace los totales.
    $GLOBALS['lc_acciones'] = [];
    add_action('update_option_lc_config_semaforo', ['LC_REST_Semaforo', 'recalcular_totales']);
}

// ---------------------------------------------------------------- plugin
foreach (['helpers.php', 'class/auth.php', 'class/competiciones.php', 'class/alumnos.php', 'class/horario.php',
          'class/rest-semaforo.php', 'class/rest-gomets.php', 'class/rest-deportes.php', 'class/rest-partes.php',
          'class/rest-biblioteca.php', 'class/rest-extraordinarias.php', 'class/rest-tiempo-aprendizaje.php',
          'class/rest-alumnos.php', 'class/rest-clasificacion.php'] as $f) {
    require_once LC_PATH . 'includes/' . $f;
}

/** Llama a un método privado o protegido (para probar piezas internas). */
function privado($clase, $metodo, ...$args) {
    $m = new ReflectionMethod($clase, $metodo);
    $m->setAccessible(true);
    return $m->invoke(null, ...$args);
}

// ---------------------------------------------------------------- resultados
$GLOBALS['lc_total'] = 0; $GLOBALS['lc_fallos'] = 0;
function ok($cond, $desc) {
    $GLOBALS['lc_total']++;
    if (!$cond) $GLOBALS['lc_fallos']++;
    fwrite(STDOUT, ($cond ? "  ✓ " : "  ✗ ") . $desc . "\n");
}
function seccion($t) { fwrite(STDOUT, "$t\n"); }
function resumen() {
    $t = $GLOBALS['lc_total']; $f = $GLOBALS['lc_fallos'];
    fwrite(STDOUT, "\n" . ($t - $f) . "/{$t} pruebas correctas\n");
    exit($f ? 1 : 0);
}
