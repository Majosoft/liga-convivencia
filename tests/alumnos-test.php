<?php
/**
 * Pruebas del borrado de alumnos (LC_Alumnos::delete) con una base de datos
 * simulada en memoria: comprueba que se eliminan sus datos personales y se
 * conservan los puntos de la clase.
 *
 *   php tests/alumnos-test.php
 */

define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');

function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
function wp_json_encode($d, $f = 0) { return json_encode($d, $f); }

class LC_Competiciones { public static function get_clases() { return ['1ºA', '1ºB']; } }

/** $wpdb mínimo: tablas en arrays; get_results devuelve las filas de la tabla del FROM. */
class FakeWpdb {
    public $prefix = 'wp_';
    public $t = [];
    public function prepare($sql, ...$args) { return [$sql, $args]; }
    public function esc_like($s) { return addcslashes($s, '_%\\'); }
    private function tabla($sql) { preg_match('/FROM\s+(\S+)/', is_array($sql) ? $sql[0] : $sql, $m); return $m[1]; }
    public function get_results($q, $o = null) { return array_values($this->t[$this->tabla($q)] ?? []); }
    public function get_row($q, $o = null) {
        [$sql, $args] = $q;
        foreach ($this->t[$this->tabla($q)] ?? [] as $r) if ((int) $r['id'] === (int) $args[0]) return $r;
        return null;
    }
    public function update($tabla, $datos, $donde) {
        foreach ($this->t[$tabla] as &$r) {
            $ok = true;
            foreach ($donde as $k => $v) if ((string) $r[$k] !== (string) $v) $ok = false;
            if ($ok) $r = array_merge($r, $datos);
        }
        return 1;
    }
    public function delete($tabla, $donde) {
        $this->t[$tabla] = array_filter($this->t[$tabla], function($r) use ($donde) {
            foreach ($donde as $k => $v) if ((string) $r[$k] !== (string) $v) return true;
            return false;
        });
        return 1;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();

require __DIR__ . '/../includes/class/alumnos.php';

$fallos = 0; $total = 0;
function ok($cond, $desc) {
    global $fallos, $total; $total++;
    if (!$cond) $fallos++;
    fwrite(STDOUT, ($cond ? "  ✓ " : "  ✗ ") . $desc . "\n");
}

$db = $GLOBALS['wpdb'];
$db->t['wp_lc_alumnos'] = [
    ['id' => 1, 'nombre' => 'Lucía', 'clase' => '1ºA', 'activo' => 1],
    ['id' => 2, 'nombre' => 'Lucía', 'clase' => '1ºB', 'activo' => 1],
];
$db->t['wp_lc_partes_alumnos'] = [
    ['id' => 10, 'alumno_id' => 1, 'clase' => '1ºA', 'tipo' => 'leve', 'notas' => 'Lucía usa el móvil en clase'],
    ['id' => 11, 'alumno_id' => 2, 'clase' => '1ºB', 'tipo' => 'positivo', 'notas' => 'Ayuda a un compañero'],
];
$db->t['wp_lc_biblioteca'] = [
    ['id' => 20, 'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Lucía'], ['clase' => '1ºB', 'nombre' => 'Lucía']], JSON_UNESCAPED_UNICODE)],
];
$db->t['wp_lc_deportes'] = [
    // Las pruebas por marca se guardan con los acentos escapados.
    ['id' => 30, 'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Lucía', 'marca' => 8.1, 'puntos' => 5]])],
];

fwrite(STDOUT, "Validación de clase\n");
ok(LC_Alumnos::clase_valida('1ºA'), 'una clase configurada es válida');
ok(!LC_Alumnos::clase_valida('9ºZ') && !LC_Alumnos::clase_valida(''), 'una clase inexistente o vacía no lo es');

fwrite(STDOUT, "Borrado con supresión de datos\n");
ok(LC_Alumnos::delete(1), 'borra el alumno');
ok(!isset(array_column($db->t['wp_lc_alumnos'], null, 'id')[1]), 'ya no está en la tabla de alumnos');

$partes = array_column($db->t['wp_lc_partes_alumnos'], null, 'id');
ok(isset($partes[10]) && $partes[10]['tipo'] === 'leve', 'su parte se conserva (los puntos son de la clase)');
ok($partes[10]['notas'] === null, '…pero pierde las notas');
ok($partes[11]['notas'] === 'Ayuda a un compañero', 'los partes de otro alumno no se tocan');

$bib = json_decode($db->t['wp_lc_biblioteca'][0]['participantes'], true);
ok($bib[0]['nombre'] === 'Alumno eliminado', 'su nombre desaparece de Biblioteca');
ok($bib[1]['nombre'] === 'Lucía', 'otra Lucía de otra clase se conserva');

$dep = json_decode($db->t['wp_lc_deportes'][0]['participantes'], true);
ok($dep[0]['nombre'] === 'Alumno eliminado' && $dep[0]['puntos'] === 5, 'también con acentos escapados en Deportes, y sin perder los puntos');

ok(!LC_Alumnos::delete(999), 'borrar un alumno que no existe no hace nada');

fwrite(STDOUT, "\n" . ($total - $fallos) . "/{$total} pruebas correctas\n");
exit($fallos ? 1 : 0);
