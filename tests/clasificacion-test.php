<?php
/**
 * Pruebas de la clasificación sobre una base de datos real (SQLite) con datos
 * conocidos y resultados calculados a mano: periodos (semana, mes por su
 * miércoles, trimestre, temporada), las seis competiciones, redondeo, orden,
 * ranking de alumnos solo con méritos y privacidad de los partes.
 *
 *   php tests/clasificacion-test.php
 */
require __DIR__ . '/bootstrap.php';

function clasif($params) { return datos(LC_REST_Clasificacion::get_clasificacion(req($params))); }
function fila($c, $clase) { foreach ($c['clasificacion'] as $f) if ($f['clase'] === $clase) return $f; return null; }
function rango($params) { return privado('LC_REST_Clasificacion', 'compute_range', req($params)); }
function sem($y, $w) { return ['year' => $y, 'week' => $w]; }

// ======================================================================
seccion('Periodos: cada semana pertenece a un solo mes');
// Noviembre de 2026: el 1 es domingo. La semana 44 (26 oct – 1 nov) tiene su
// miércoles en octubre; la 49 (30 nov – 6 dic), en diciembre.
$nov = rango(['periodo' => 'mensual', 'year' => 2026, 'month' => 11]);
ok($nov['from'] === sem(2026, 45) && $nov['to'] === sem(2026, 48), 'noviembre 2026 = semanas 45 a 48');
$oct = rango(['periodo' => 'mensual', 'year' => 2026, 'month' => 10]);
ok($oct['from'] === sem(2026, 41) && $oct['to'] === sem(2026, 44), 'octubre 2026 = semanas 41 a 44 (la 40 es de septiembre)');
$dic = rango(['periodo' => 'mensual', 'year' => 2026, 'month' => 12]);
ok($dic['to'] === sem(2026, 53), 'diciembre 2026 incluye la semana 53 (28 dic – 3 ene)');
$ene = rango(['periodo' => 'mensual', 'year' => 2027, 'month' => 1]);
ok($ene['from'] === sem(2027, 1), 'enero 2027 empieza en la semana 1 de 2027');
ok($nov['m_from'] === ['year' => 2026, 'month' => 11] && $nov['m_to'] === ['year' => 2026, 'month' => 11],
   'Tiempo de Aprendizaje en "Mensual" usa el mes completo');

// Propiedad: de enero 2026 a diciembre 2027 los meses se reparten las semanas
// sin huecos ni solapes (la siguiente empieza justo después de la anterior).
$sig = function($p) { $d = (new DateTime())->setISODate($p['year'], $p['week'], 1)->modify('+7 days'); return sem((int) $d->format('o'), (int) $d->format('W')); };
$contiguo = true; $prev = null;
for ($y = 2026; $y <= 2027; $y++) for ($m = 1; $m <= 12; $m++) {
    $r = rango(['periodo' => 'mensual', 'year' => $y, 'month' => $m]);
    if ($prev && $r['from'] !== $sig($prev)) { $contiguo = false; fwrite(STDOUT, "    hueco o solape antes de $y-$m\n"); }
    $prev = $r['to'];
}
ok($contiguo, '24 meses seguidos: todas las semanas, cada una en un solo mes');
ok(rango(['periodo' => 'mensual', 'year' => 2026, 'month' => 13])['from'] === null, 'un mes no válido no filtra nada');

ok(rango(['periodo' => 'semanal', 'year' => 2026, 'week' => 47])['from'] === sem(2026, 47), 'semanal: una semana');
ok(rango(['periodo' => 'semanal', 'year' => 2026, 'week' => 49])['m_from'] === ['year' => 2026, 'month' => 11],
   'semanal: Tiempo de Aprendizaje toma el mes del lunes (30 nov → noviembre)');

lc_reset();
update_option(LC_Competiciones::OPT_TEMPORADAS, [
    ['label' => '2025-26', 'inicio' => '2025-09-15', 'fin' => '2026-06-19'],
    ['label' => '2026-27', 'inicio' => '2026-09-14', 'fin' => ''],
]);
update_option(LC_Competiciones::OPT_TRIMESTRES, [
    ['label' => '1er trimestre', 'inicio' => '2025-09-15', 'fin' => '2025-12-19', 'temporada' => '2025-26'],
    ['label' => '1er trimestre', 'inicio' => '2026-09-14', 'fin' => '2026-12-18', 'temporada' => '2026-27'],
]);
ok(rango(['periodo' => 'trimestral', 'trimestre' => '1er trimestre'])['from'] === sem(2026, 38),
   'trimestral: con nombres repetidos, gana el trimestre de la temporada en curso');
$t = rango(['periodo' => 'temporada', 'temporada' => '2025-26']);
ok($t['from'] === sem(2025, 38) && $t['to'] === sem(2026, 25), 'temporada cerrada: de su inicio a su fin');
$g = rango(['periodo' => 'general']);
ok($g['from'] === sem(2026, 38) && $g['to'] === null, 'general: la temporada en curso, sin fin');

// ======================================================================
// Datos conocidos. Clases por defecto: 1ºA, 1ºB, 2ºA, 2ºB, 3ºA, 3ºB, 4ºA, 4ºB.
function datos_conocidos() {
    global $wpdb;
    update_option(LC_Competiciones::OPT_DEPORTES, [
        ['value' => 'futbol', 'label' => 'Fútbol',  'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'pinpon', 'label' => 'Pin Pon', 'modo' => 'alumnos',        'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ]);
    update_option(LC_Competiciones::OPT_ACTIVIDADES, [
        ['value' => 'podcast', 'label' => 'Podcast', 'modo' => 'participantes', 'activo' => true, 'niveles' => [['label' => 'Bien', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1]]],
        ['value' => 'ajedrez', 'label' => 'Ajedrez', 'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ]);
    $GLOBALS['lucia'] = LC_Alumnos::find_or_create('Lucía', '1ºA');
    $GLOBALS['hugo'] = LC_Alumnos::find_or_create('Hugo', '1ºB');

    // Semáforo de 1ºA (acumulado): 2 en la semana 10, 5 en la 44, 8 en la 45, 6 en la 47 y 20 en la 49.
    foreach ([[10, 2], [44, 5], [45, 8], [47, 6], [49, 20]] as [$w, $total]) {
        $wpdb->insert('wp_lc_semaforo', ['clase' => '1ºA', 'iso_year' => 2026, 'iso_week' => $w, 'total' => $total]);
    }
    // Deportes: 1ºA gana a 1ºB al fútbol en la 45 (3–0); empata con 2ºA en la 49.
    $wpdb->insert('wp_lc_deportes', ['deporte' => 'futbol', 'iso_year' => 2026, 'iso_week' => 45, 'equipo_a' => '1ºA', 'equipo_b' => '1ºB', 'pts_a' => 3, 'pts_b' => 0]);
    $wpdb->insert('wp_lc_deportes', ['deporte' => 'futbol', 'iso_year' => 2026, 'iso_week' => 49, 'equipo_a' => '1ºA', 'equipo_b' => '2ºA', 'pts_a' => 1, 'pts_b' => 1]);
    // Pin Pon (alumnos), semana 47: Lucía (1ºA) gana a Hugo (1ºB).
    $wpdb->insert('wp_lc_deportes', ['deporte' => 'pinpon', 'iso_year' => 2026, 'iso_week' => 47, 'equipo_a' => '1ºA', 'equipo_b' => '1ºB',
                                     'pts_a' => 3, 'pts_b' => 0, 'alumno_a' => $GLOBALS['lucia'], 'alumno_b' => $GLOBALS['hugo']]);
    // Biblioteca, semana 46: un podcast de 3 puntos entre 1ºA y 1ºB (1,5 cada una) y otro de 1 punto de 1ºA.
    $wpdb->insert('wp_lc_biblioteca', ['actividad' => 'podcast', 'iso_year' => 2026, 'iso_week' => 46, 'puntos' => 3,
                                       'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Ana'], ['clase' => '1ºB', 'nombre' => 'Leo']])]);
    $wpdb->insert('wp_lc_biblioteca', ['actividad' => 'podcast', 'iso_year' => 2026, 'iso_week' => 46, 'puntos' => 1,
                                       'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Ana']])]);
    // Partes, semana 47: positivo de Lucía (+3) y grave de Hugo (−10).
    $wpdb->insert('wp_lc_partes_alumnos', ['alumno_id' => $GLOBALS['lucia'], 'clase' => '1ºA', 'iso_year' => 2026, 'iso_week' => 47, 'tipo' => 'positivo']);
    $wpdb->insert('wp_lc_partes_alumnos', ['alumno_id' => $GLOBALS['hugo'], 'clase' => '1ºB', 'iso_year' => 2026, 'iso_week' => 47, 'tipo' => 'grave']);
    // Extraordinarias, semana 48: 2ºA 10 y 1ºA 5.
    $wpdb->insert('wp_lc_extraordinarias', ['nombre' => 'Murales', 'iso_year' => 2026, 'iso_week' => 48, 'clase' => '2ºA', 'puntos' => 10]);
    $wpdb->insert('wp_lc_extraordinarias', ['nombre' => 'Murales', 'iso_year' => 2026, 'iso_week' => 48, 'clase' => '1ºA', 'puntos' => 5]);
    // Tiempo de Aprendizaje de 1ºA: octubre 4, noviembre 6, diciembre 9.
    foreach ([[10, 4], [11, 6], [12, 9]] as [$m, $pts]) {
        $wpdb->insert('wp_lc_tiempo_aprendizaje', ['clase' => '1ºA', 'iso_year' => 2026, 'iso_month' => $m, 'puntos' => $pts]);
    }
}

// ======================================================================
seccion('Clasificación mensual (noviembre 2026), calculada a mano');
lc_reset();
datos_conocidos();
$c = clasif(['periodo' => 'mensual', 'year' => 2026, 'month' => 11]);
$a = fila($c, '1ºA'); $b = fila($c, '1ºB'); $dos = fila($c, '2ºA');
ok($a['semaforo'] === 1, 'Semáforo 1ºA: acumulado al final (6) − antes de empezar (5) = 1');
ok($a['deportes'] === 6, 'Deportes 1ºA: fútbol 3 (sem. 45) + pin pon 3 (sem. 47); el empate de la 49 es de diciembre');
ok($a['biblioteca'] === 3 && $b['biblioteca'] === 2, 'Biblioteca: 1,5 + 1 = 2,5 → 3 para 1ºA; 1,5 → 2 para 1ºB');
ok($a['partes'] === 3 && $b['partes'] === -10, 'Partes: +3 y −10');
ok($a['extraordinarias'] === 5 && $dos['extraordinarias'] === 10, 'Extraordinarias: 5 y 10');
ok($a['tiempo_aprendizaje'] === 6, 'Tiempo de Aprendizaje: solo el de noviembre (6)');
ok($a['total'] === 24, 'Total 1ºA = 1 + 6 + 3 + 3 + 5 + 6 = 24');
ok($b['total'] === -8, 'Total 1ºB = 2 − 10 = −8');
$cuadra = true;
foreach ($c['clasificacion'] as $f) {
    if ($f['total'] !== $f['semaforo'] + $f['deportes'] + $f['partes'] + $f['extraordinarias'] + $f['biblioteca'] + $f['tiempo_aprendizaje']) $cuadra = false;
    foreach (['semaforo', 'deportes', 'biblioteca', 'total'] as $k) if (!is_int($f[$k])) $cuadra = false;
}
ok($cuadra, 'en todas las clases el total es la suma de sus columnas, y todo son enteros');
ok(array_column($c['clasificacion'], 'clase')[0] === '1ºA' && array_column($c['clasificacion'], 'clase')[1] === '2ºA'
   && end($c['clasificacion'])['clase'] === '1ºB', 'orden: 1ºA (24), 2ºA (10)… y 1ºB (−8) el último');
ok(count($c['clasificacion']) === 8, 'aparecen las 8 clases, también las que no tienen puntos');
ok($c['semanas_mes'] === ['2026-11-02', '2026-11-27'], 'indica qué semanas cuenta el mes (2 al 27 de noviembre)');

seccion('Otros periodos con los mismos datos');
$c = clasif(['periodo' => 'semanal', 'year' => 2026, 'week' => 46]);
ok(fila($c, '1ºA')['semaforo'] === 0 && fila($c, '1ºA')['biblioteca'] === 3, 'semana 46: sin gomets nuevos (0) y la biblioteca de esa semana');
$c = clasif(['periodo' => 'semanal', 'year' => 2026, 'week' => 45]);
ok(fila($c, '1ºA')['semaforo'] === 3, 'semana 45: 8 − 5 = 3');
$c = clasif(['periodo' => 'general']);
ok(fila($c, '1ºA')['semaforo'] === 20 && fila($c, '1ºA')['tiempo_aprendizaje'] === 19, 'general sin temporadas: todo el histórico (semáforo 20, T. A. 4 + 6 + 9)');
ok(fila($c, '1ºA')['deportes'] === 7, 'general: también el empate de la semana 49 (3 + 3 + 1)');

update_option(LC_Competiciones::OPT_TEMPORADAS, [
    ['label' => '2025-26', 'inicio' => '2025-09-15', 'fin' => '2026-06-19'],
    ['label' => '2026-27', 'inicio' => '2026-09-14', 'fin' => ''],
]);
$c = clasif(['periodo' => 'general']);
ok(fila($c, '1ºA')['semaforo'] === 18, 'general con temporada en curso: 20 − 2 de la temporada anterior = 18');
ok($c['temporada_activa']['label'] === '2026-27', 'informa de la temporada activa');
$c = clasif(['periodo' => 'temporada', 'temporada' => '2025-26']);
ok(fila($c, '1ºA')['semaforo'] === 2 && fila($c, '1ºA')['total'] === 2, 'temporada cerrada 2025-26: solo lo suyo (semáforo 2)');

seccion('Rankings por competición');
$c = clasif(['periodo' => 'mensual', 'year' => 2026, 'month' => 11]);
$pod = array_values(array_filter($c['por_actividad'], function($x) { return $x['value'] === 'podcast'; }))[0];
ok($pod['ranking'][0]['clase'] === '1ºA' && $pod['ranking'][0]['puntos'] === 3, 'Podcast: 1ºA primera con 3 (2,5 redondeado)');
$pp = array_values(array_filter($c['por_deporte'], function($x) { return $x['value'] === 'pinpon'; }))[0];
ok($pp['ranking_alumnos'][0]['nombre'] === 'Lucía' && $pp['ranking_alumnos'][0]['puntos'] === 3, 'Pin Pon: ranking de alumnos con Lucía (3)');

seccion('Ranking público de alumnos y privacidad');
$al = array_column($c['alumnos'], 'puntos', 'nombre');
ok($al === ['Lucía' => 3], 'solo méritos: Lucía 3 (pin pon); su parte positivo (+3) no se suma');
ok(!isset($al['Hugo']), 'quien no suma no aparece (Hugo: 0 y un parte grave)');
ok($c['alumnos_partes'] === [], 'sin sesión, el ranking de partes por alumno va vacío');

LC_Auth::add_user('coordinacion@centro.edu');
$_COOKIE[LC_Auth::COOKIE] = LC_Auth::mint_token('coordinacion@centro.edu');
$res = LC_REST_Clasificacion::get_clasificacion(req(['periodo' => 'mensual', 'year' => 2026, 'month' => 11]));
$p = array_column(datos($res)['alumnos_partes'], 'puntos', 'nombre');
ok($p === ['Lucía' => 3, 'Hugo' => -10], 'con sesión de acceso completo: partes por alumno');
ok(($res->get_headers()['Cache-Control'] ?? '') === 'private, no-store', '…y la respuesta no se puede guardar en caché');
$_COOKIE = [];

LC_Alumnos::delete($GLOBALS['lucia']);
$c = clasif(['periodo' => 'mensual', 'year' => 2026, 'month' => 11]);
ok($c['alumnos'] === [], 'un alumno borrado desaparece del ranking público');
ok(fila($c, '1ºA')['total'] === 24, '…pero los puntos de su clase se conservan');

seccion('Widget');
$w = datos(LC_REST_Clasificacion::get_widget(req()));
ok($w['ok'] === true && strpos($w['html'], '1ºA') !== false, 'el widget genera su HTML (antes daba error fatal)');
ok(get_transient('lc_widget_html') === $w['html'], 'y se guarda en caché');

resumen();
