<?php
/**
 * Pruebas de las reglas de cada competición a través de su API REST, sobre
 * una base de datos real (SQLite): Deportes (los tres modos), Biblioteca,
 * Extraordinarias, Partes, Tiempo de Aprendizaje y Alumnos, incluido el borrado.
 *
 *   php tests/competiciones-test.php
 */
require __DIR__ . '/bootstrap.php';

function preparar() {
    lc_reset();
    update_option(LC_Competiciones::OPT_DEPORTES, [
        ['value' => 'futbol',  'label' => 'Fútbol',  'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'pinpon',  'label' => 'Pin Pon', 'modo' => 'alumnos',        'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'carrera', 'label' => 'Carrera', 'modo' => 'individual', 'orden' => 'asc',  'puntos_por_posicion' => [5, 3, 2, 1], 'activo' => true],
        ['value' => 'salto',   'label' => 'Salto',   'modo' => 'individual', 'orden' => 'desc', 'puntos_por_posicion' => [5, 3, 2, 1], 'activo' => true],
    ]);
    update_option(LC_Competiciones::OPT_ACTIVIDADES, [
        ['value' => 'podcast', 'label' => 'Podcast', 'modo' => 'participantes', 'activo' => true,
         'niveles' => [['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1]]],
        ['value' => 'ajedrez', 'label' => 'Ajedrez', 'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'damas',   'label' => 'Damas',   'modo' => 'alumnos', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ]);
}
function semana($extra) { return req(array_merge(['year' => 2026, 'week' => 47], $extra), 'POST'); }

// ======================================================================
seccion('Deportes · enfrentamiento entre clases');
preparar();
$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'futbol', 'equipo_a' => '1ºB', 'equipo_b' => '1ºA', 'marcador_a' => 3, 'marcador_b' => 1])));
$p = $r['partidos'][0];
ok($p['equipo_a'] === '1ºA' && $p['equipo_b'] === '1ºB', 'normaliza el orden de las clases (1ºB–1ºA → 1ºA–1ºB)');
ok((int) $p['marcador_a'] === 1 && (int) $p['marcador_b'] === 3, '…y gira el marcador con ellas');
ok((int) $p['pts_a'] === 0 && (int) $p['pts_b'] === 3, 'victoria 3, derrota 0 a quien corresponde');
ok($r['totales'] === ['1ºA' => 0, '1ºB' => 3], 'totales de la semana por clase');

$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'futbol', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB', 'marcador_a' => 2, 'marcador_b' => 2])));
ok(count($r['partidos']) === 1 && (int) $r['partidos'][0]['pts_a'] === 1 && (int) $r['partidos'][0]['pts_b'] === 1,
   'el mismo partido en la misma semana se actualiza, no se duplica (empate 1–1)');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'futbol', 'equipo_a' => '1ºA', 'equipo_b' => '1ºA']))) === 400, 'las dos clases deben ser distintas');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'futbol', 'equipo_a' => '1ºA']))) === 400, 'hacen falta las dos clases');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'curling', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB']))) === 400, 'un deporte que no existe se rechaza');
$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'futbol', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB', 'marcador_a' => -4, 'marcador_b' => 0])));
ok((int) $r['partidos'][0]['marcador_a'] === 0, 'un marcador negativo se queda en 0');

seccion('Deportes · alumno contra alumno');
$lucia = LC_Alumnos::find_or_create('Lucía', '1ºA');
$hugo = LC_Alumnos::find_or_create('Hugo', '1ºB');
$leo = LC_Alumnos::find_or_create('Leo', '1ºB');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'pinpon', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB']))) === 400, 'hay que elegir los dos alumnos');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'pinpon', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB', 'alumno_a' => $hugo, 'alumno_b' => $leo]))) === 400,
   'cada alumno tiene que ser de la clase por la que compite');
LC_REST_Deportes::upsert(semana(['deporte' => 'pinpon', 'equipo_a' => '1ºB', 'equipo_b' => '1ºA', 'alumno_a' => $hugo, 'alumno_b' => $lucia, 'marcador_a' => 3, 'marcador_b' => 1]));
$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'pinpon', 'equipo_a' => '1ºA', 'equipo_b' => '1ºB', 'alumno_a' => $lucia, 'alumno_b' => $leo, 'marcador_a' => 3, 'marcador_b' => 0])));
ok(count($r['partidos']) === 2, 'cada partida entre alumnos es un registro propio');
$p0 = $r['partidos'][0];
ok($p0['equipo_a'] === '1ºA' && (int) $p0['alumno_a'] === $lucia && (int) $p0['alumno_b'] === $hugo,
   'al normalizar el orden, cada alumno viaja con su clase');
ok($p0['alumno_a_nombre'] === 'Lucía' && $p0['alumno_b_nombre'] === 'Hugo', 'la lista resuelve el nombre de los alumnos');

seccion('Deportes · prueba individual por marca');
$part = json_encode([['clase' => '1ºA', 'nombre' => 'Lucía', 'marca' => 8.4], ['clase' => '1ºB', 'nombre' => 'Hugo', 'marca' => 7.9],
                     ['clase' => '2ºA', 'nombre' => 'Sara', 'marca' => 9.1], ['clase' => '2ºB', 'nombre' => 'Iker', 'marca' => 8.0],
                     ['clase' => '3ºA', 'nombre' => 'Noa', 'marca' => 9.5]]);
$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'carrera', 'participantes' => $part])));
$lista = json_decode($r['partidos'][0]['participantes'], true);
ok(array_column($lista, 'nombre') === ['Hugo', 'Iker', 'Lucía', 'Sara', 'Noa'], 'orden "menor gana" (tiempos): de menor a mayor marca');
ok(array_column($lista, 'puntos') === [5, 3, 2, 1, 0], 'puntos por posición 5, 3, 2, 1 y 0 a partir del quinto');
$r = datos(LC_REST_Deportes::upsert(semana(['deporte' => 'salto', 'participantes' => $part])));
ok(json_decode($r['partidos'][0]['participantes'], true)[0]['nombre'] === 'Noa', 'orden "mayor gana" (distancias): gana la mayor marca');
LC_REST_Deportes::upsert(semana(['deporte' => 'carrera', 'participantes' => $part]));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_deportes WHERE deporte = 'carrera'") === 1, 'un evento individual por deporte y semana (guardar otra vez lo sustituye)');
ok(estado(LC_REST_Deportes::upsert(semana(['deporte' => 'carrera', 'participantes' => '[]']))) === 400, 'sin participantes no se guarda');

seccion('Deportes · borrar');
$id = (int) $wpdb->get_var("SELECT id FROM wp_lc_deportes WHERE deporte = 'futbol'");
$r = datos(LC_REST_Deportes::borrar(req(['id' => $id], 'DELETE')));
ok($r['ok'] === true && $r['partidos'] === [], 'borra el partido y devuelve la lista de su semana');
ok(estado(LC_REST_Deportes::borrar(req(['id' => 9999], 'DELETE'))) === 404, 'borrar un id que no existe da 404');

// ======================================================================
seccion('Biblioteca');
preparar();
$r = LC_REST_Biblioteca::upsert(semana(['actividad' => 'podcast', 'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Ana']]), 'puntos' => 2]));
ok(estado($r) === 400, 'participantes: la valoración tiene que ser uno de los niveles configurados');
$r = datos(LC_REST_Biblioteca::upsert(semana(['actividad' => 'podcast', 'participantes' => json_encode([['clase' => '1ºA', 'nombre' => 'Ana']]), 'puntos' => 3])));
ok(count($r['actividades']) === 1 && (int) $r['actividades'][0]['puntos'] === 3, 'participantes: se guarda con su valoración');
ok(estado(LC_REST_Biblioteca::upsert(semana(['actividad' => 'podcast', 'participantes' => '', 'puntos' => 3]))) === 400, 'participantes: no se guarda sin participantes');

$r = datos(LC_REST_Biblioteca::upsert(semana(['actividad' => 'ajedrez', 'clase_a' => '2ºB', 'clase_b' => '2ºA', 'ganador' => 'clase-a'])));
ok($r['actividades'][0]['clase_a'] === '2ºA' && $r['actividades'][0]['ganador'] === 'clase-b',
   'enfrentamiento: normaliza el orden y gira el ganador con él');
LC_REST_Biblioteca::upsert(semana(['actividad' => 'ajedrez', 'clase_a' => '2ºA', 'clase_b' => '2ºB', 'ganador' => 'empate']));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_biblioteca WHERE actividad = 'ajedrez'") === 1, 'enfrentamiento: la misma pareja en la misma semana se actualiza');
$r = datos(LC_REST_Biblioteca::upsert(semana(['actividad' => 'ajedrez', 'clase_a' => '3ºA', 'clase_b' => '3ºB', 'ganador' => 'trampa'])));
ok(end($r['actividades'])['ganador'] === 'empate', 'enfrentamiento: un ganador no válido cuenta como empate');
ok(estado(LC_REST_Biblioteca::upsert(semana(['actividad' => 'ajedrez', 'clase_a' => '3ºA', 'clase_b' => '3ºA']))) === 400, 'enfrentamiento: las clases deben ser distintas');
ok(estado(LC_REST_Biblioteca::upsert(semana(['actividad' => 'origami', 'participantes' => 'x', 'puntos' => 3]))) === 400, 'una actividad que no existe se rechaza');

$a = LC_Alumnos::find_or_create('Vega', '4ºA'); $b = LC_Alumnos::find_or_create('Teo', '4ºB');
ok(estado(LC_REST_Biblioteca::upsert(semana(['actividad' => 'damas', 'clase_a' => '4ºA', 'clase_b' => '4ºB', 'alumno_a' => $b, 'alumno_b' => $a, 'ganador' => 'clase-a']))) === 400,
   'alumnos: cada alumno tiene que ser de su clase');
LC_REST_Biblioteca::upsert(semana(['actividad' => 'damas', 'clase_a' => '4ºA', 'clase_b' => '4ºB', 'alumno_a' => $a, 'alumno_b' => $b, 'ganador' => 'clase-a']));
LC_REST_Biblioteca::upsert(semana(['actividad' => 'damas', 'clase_a' => '4ºA', 'clase_b' => '4ºB', 'alumno_a' => $a, 'alumno_b' => $b, 'ganador' => 'clase-b']));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_biblioteca WHERE actividad = 'damas'") === 2, 'alumnos: cada partida es un registro propio');

$id = (int) $wpdb->get_var("SELECT id FROM wp_lc_biblioteca WHERE actividad = 'podcast'");
$r = datos(LC_REST_Biblioteca::borrar(req(['id' => $id], 'DELETE')));
ok($r['ok'] === true && $r['actividades'] === [], 'borrar quita el registro y devuelve la lista de la semana');

// ======================================================================
seccion('Actividades extraordinarias');
preparar();
$clases = function($l) { return json_encode(array_map(function($c) { return ['clase' => $c[0], 'puntos' => $c[1]]; }, $l)); };
$r = datos(LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Murales', 'clases' => $clases([['1ºA', 10], ['1ºB', 8]])])));
ok(count($r['actividades']) === 1 && count($r['actividades'][0]['clases']) === 2, 'guarda una actividad con sus clases');
ok(estado(LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Murales', 'clases' => $clases([['1ºA', 7]])]))) === 400, 'solo valen 10, 8, 5, 3 o 0 puntos');
ok(estado(LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Murales', 'clases' => $clases([['1ºA', 10], ['1ºA', 8]])]))) === 400, 'no se puede repetir una clase');
ok(estado(LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Murales', 'clases' => $clases([['9ºZ', 10]])]))) === 400, 'no se aceptan clases que no existen');
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_extraordinarias") === 2, 'un guardado rechazado no borra la versión anterior');
LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Murales', 'clases' => $clases([['2ºA', 5]])]));
ok($wpdb->get_col("SELECT clase FROM wp_lc_extraordinarias") === ['2ºA'], 'guardar otra vez sustituye todas sus clases');
LC_REST_Extraordinarias::upsert(semana(['nombre' => 'Concurso de murales', 'original_nombre' => 'Murales', 'clases' => $clases([['2ºA', 5]])]));
ok($wpdb->get_col("SELECT nombre FROM wp_lc_extraordinarias") === ['Concurso de murales'], 'renombrar no deja la actividad duplicada');
$r = datos(LC_REST_Extraordinarias::borrar(req(['nombre' => 'Concurso de murales', 'year' => 2026, 'week' => 47], 'DELETE')));
ok($r['actividades'] === [], 'borrar quita la actividad entera');
ok(estado(LC_REST_Extraordinarias::borrar(req(['nombre' => 'No existe', 'year' => 2026, 'week' => 47], 'DELETE'))) === 404, 'borrar una actividad que no existe da 404');

// ======================================================================
seccion('Partes');
preparar();
$al = LC_Alumnos::find_or_create('Pablo', '3ºB');
$r = datos(LC_REST_Partes::create(semana(['clase' => '1ºA', 'alumno_id' => $al, 'tipo' => 'grave', 'notas' => 'x'])));
ok($wpdb->get_var("SELECT clase FROM wp_lc_partes_alumnos") === '3ºB', 'la clase del parte es la del alumno, no la del formulario');
ok($r['resumen'] === ['positivo' => 0, 'leve' => 0, 'grave' => 1], 'la respuesta es la lista de la clase del parte (3ºB), con su parte grave');
$r = datos(LC_REST_Partes::list(req(['clase' => '3ºB', 'year' => 2026, 'week' => 47])));
ok($r['puntos'] === -10 && $r['partes'][0]['alumno'] === 'Pablo', 'grave: −10, con el nombre del alumno');
LC_REST_Partes::create(semana(['clase' => '3ºB', 'tipo' => 'positivo']));
$r = datos(LC_REST_Partes::list(req(['clase' => '3ºB', 'year' => 2026, 'week' => 47])));
ok($r['partes'][1]['alumno'] === 'Toda la clase' && $r['puntos'] === -7, 'parte a toda la clase (+3) y saldo −7');
ok(estado(LC_REST_Partes::create(semana(['clase' => '3ºB', 'tipo' => 'muy grave']))) === 400, 'tipo de parte no válido');
ok(estado(LC_REST_Partes::create(semana(['clase' => '9ºZ', 'tipo' => 'leve']))) === 400, 'a toda la clase: la clase tiene que existir');
ok(estado(LC_REST_Partes::create(semana(['clase' => '1ºA', 'alumno_id' => 999, 'tipo' => 'leve']))) === 400, 'un alumno que no existe se rechaza');
update_option(LC_Competiciones::OPT_PARTES, ['positivo' => 5, 'leve' => -1, 'grave' => -20]);
$r = datos(LC_REST_Partes::list(req(['clase' => '3ºB', 'year' => 2026, 'week' => 47])));
ok($r['puntos'] === -15, 'cambiar la puntuación de los partes afecta al histórico (−20 + 5)');
$r = datos(LC_REST_Partes::delete(req(['id' => (int) $r['partes'][0]['id']], 'DELETE')));
ok(count($r['partes']) === 1, 'borrar un parte');

// ======================================================================
seccion('Tiempo de Aprendizaje');
preparar();
$r = LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '1ºA', 'year' => 2026, 'month' => 11, 'puntos' => 7], 'POST'));
ok(datos($r)['puntos'] === 7, 'guarda los puntos del mes');
LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '1ºA', 'year' => 2026, 'month' => 11, 'puntos' => 9], 'POST'));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_tiempo_aprendizaje") === 1
   && datos(LC_REST_TiempoAprendizaje::get_record(req(['clase' => '1ºA', 'year' => 2026, 'month' => 11])))['puntos'] === 9,
   'un registro por clase y mes: guardar otra vez lo actualiza');
$r = LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '1ºA', 'year' => 2026, 'month' => 12, 'puntos' => -4], 'POST'));
ok(datos($r)['puntos'] === 0, 'puntos negativos se guardan como 0');
ok(estado(LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '1ºA', 'year' => 2026, 'month' => 13, 'puntos' => 1], 'POST'))) === 400, 'mes fuera de 1-12');
ok(estado(LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '9ºZ', 'year' => 2026, 'month' => 11, 'puntos' => 1], 'POST'))) === 400, 'clase que no existe');
LC_REST_TiempoAprendizaje::upsert_or_delete(req(['clase' => '1ºA', 'year' => 2026, 'month' => 11, 'borrar' => true], 'POST'));
ok(datos(LC_REST_TiempoAprendizaje::get_record(req(['clase' => '1ºA', 'year' => 2026, 'month' => 11])))['exists'] === false, 'borrar el mes');

// ======================================================================
seccion('Alumnos');
preparar();
$r = datos(LC_REST_Alumnos::create(req(['nombre' => 'Irene', 'clase' => '2ºA'], 'POST')));
$r2 = datos(LC_REST_Alumnos::create(req(['nombre' => 'Irene', 'clase' => '2ºA'], 'POST')));
ok($r['alumno']['id'] === $r2['alumno']['id'], 'dar de alta otra vez el mismo alumno reutiliza el existente');
ok(estado(LC_REST_Alumnos::create(req(['nombre' => 'Irene', 'clase' => '9ºZ'], 'POST'))) === 400, 'no se aceptan clases que no existen');
ok(estado(LC_REST_Alumnos::create(req(['nombre' => '  ', 'clase' => '2ºA'], 'POST'))) === 400, 'hace falta el nombre');
ok(LC_Alumnos::find_or_create('<b>Gael</b>', '2ºA') && $wpdb->get_var("SELECT nombre FROM wp_lc_alumnos WHERE nombre LIKE 'Gael%'") === 'Gael',
   'el nombre se guarda sin etiquetas HTML');
LC_Alumnos::update($r['alumno']['id'], 'Irene', '2ºA', false);
ok(count(datos(LC_REST_Alumnos::list(req(['clase' => '2ºA'])))['alumnos']) === 1, 'un alumno inactivo no se ofrece en los formularios');

resumen();
