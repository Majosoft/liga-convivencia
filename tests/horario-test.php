<?php
/**
 * Pruebas del horario y del Semáforo sobre una base de datos real (SQLite):
 * reparto de cada sesión entre las clases, gomet de menor valor, apoyos,
 * validaciones de los gomets, acumulado semanal con cascada y recálculo al
 * cambiar la puntuación.
 *
 *   php tests/horario-test.php
 */
require __DIR__ . '/bootstrap.php';

// Horario de prueba (lunes = día 1):
// - Hora 1: 1ºA y 1ºB con su propia sesión; además un apoyo C1 y el profesor APOYO.
// - Hora 2: optativas mezcladas de 1º (n > 0): 1ºB no tiene sesión propia,
//   así que sus alumnos están en ellas.
// - Hora 3: religión de 2º (RE y atención educativa mezcladas) y 2ºB con
//   sesión propia: en religión cubren a todas las clases del curso.
// - Hora 4: un grupo que no es de la Liga (bachillerato).
const CSV = "profesor,dia,hora,materia,aula,grupo,n
P1,1,1,MAT,A1,1ºA,
P2,1,1,LEN,A2,1ºB,
P8,1,1,C1,A1,1ºA,
APOYO,1,1,MAT,A1,1ºB,
P3,1,2,FR,A3,1ºA,1
P4,1,2,TEC,A4,1ºA,1
P5,1,3,RE,A5,2ºA,1
P6,1,3,AE,A6,2ºA,1
P7,1,3,MAT,A7,2ºB,
P9,1,4,MAT,A1,BACH1,";

const LUNES = '2026-11-16'; // semana ISO 47 de 2026

function sesion($prof, $hora = null) {
    global $wpdb;
    $sql = "SELECT * FROM wp_lc_horario WHERE profesor = " . $wpdb->pdo->quote($prof) . ($hora ? " AND hora = " . (int) $hora : '');
    return $wpdb->get_row($sql, ARRAY_A);
}
function semana($clase, $w = 47, $y = 2026) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM wp_lc_semaforo WHERE clase=%s AND iso_year=%d AND iso_week=%d", $clase, $y, $w), ARRAY_A);
}

// ======================================================================
seccion('Importación y reparto de sesiones');
lc_reset();
ok(LC_Horario::importar(CSV) === 10, 'importa las 10 sesiones');
ok(sesion('P1')['clases'] === '1ºA', 'una sesión propia cubre su clase');
ok(sesion('P2')['clases'] === '1ºB', 'cada grupo, la suya');
ok(sesion('P3')['clases'] === '1ºA,1ºB' && sesion('P4')['clases'] === '1ºA,1ºB',
   'optativas mezcladas: cubren también a la clase del curso sin sesión propia');
ok(sesion('P5')['clases'] === '2ºA,2ºB' && sesion('P6')['clases'] === '2ºA,2ºB',
   'religión: las sesiones mezcladas cubren a todas las clases del curso');
ok(sesion('P7')['clases'] === '2ºB', 'la sesión propia de 2ºB se mantiene');
ok((int) sesion('P8')['cuenta'] === 0, 'el apoyo C1 no pone gomet');
ok((int) sesion('APOYO')['cuenta'] === 0, 'el profesor APOYO no pone gomet');
ok(sesion('P9')['clases'] === '', 'un grupo que no es de la Liga no cubre ninguna clase');

ok(is_string(LC_Horario::importar("profesor,dia,hora\nP1,1,1")), 'rechaza un CSV sin las columnas necesarias');
ok(strpos((string) LC_Horario::importar("profesor,dia,hora,materia,aula,grupo,n\nP1,6,1,MAT,A1,1ºA,"), 'Fila 2') !== false,
   'rechaza un día fuera de 1-5 indicando la fila');

$cob = LC_Horario::comprobar_cobertura();
ok($cob['1ºA']['cubiertas'] === 2 && count($cob['1ºA']['huecos']) === 28, 'cobertura: 1ºA cubre 2 de sus 30 horas');
ok(in_array('Lu 3ª', $cob['1ºA']['huecos'], true), 'cobertura: indica los huecos (lunes 3ª)');

// ======================================================================
seccion('Gomets y recuento semanal');
ok(LC_Horario::poner_gomet('P1', sesion('P1')['id'], LUNES, 'verde') === true, 'P1 pone un verde en su clase');
ok(LC_Horario::poner_gomet('P2', sesion('P2')['id'], LUNES, 'amarillo') === true, 'P2 pone un amarillo');
LC_Horario::poner_gomet('P3', sesion('P3')['id'], LUNES, 'verde');
LC_Horario::poner_gomet('P4', sesion('P4')['id'], LUNES, 'rojo');

$a = semana('1ºA'); $b = semana('1ºB');
ok($a && (int) $a['gomets_verdes'] === 1 && (int) $a['gomets_rojos'] === 1,
   '1ºA: verde en 1ª y, en la 2ª mezclada (verde y rojo), cuenta el de menor valor: rojo');
ok((int) $a['total'] === 1 - 3, '1ºA: total = +1 −3 = −2');
ok($b && (int) $b['gomets_amarillos'] === 1 && (int) $b['gomets_rojos'] === 1 && (int) $b['gomets_verdes'] === 0,
   '1ºB: amarillo propio y rojo de la optativa mezclada');

ok(LC_Horario::poner_gomet('P1', sesion('P2')['id'], LUNES, 'verde') === 'Esa clase no es tuya.', 'no se puede poner gomet en la clase de otro');
ok(LC_Horario::poner_gomet('P8', sesion('P8')['id'], LUNES, 'verde') === 'Esa clase no es tuya.', 'un apoyo no puede poner gomet');
ok(LC_Horario::poner_gomet('P1', sesion('P1')['id'], '2026-11-17', 'verde') === 'Esa clase no es de ese día.', 'la fecha tiene que ser del día de la sesión');

LC_Horario::poner_gomet('P1', sesion('P1')['id'], LUNES, 'rojo');
ok((int) semana('1ºA')['gomets_rojos'] === 2 && (int) semana('1ºA')['gomets_verdes'] === 0, 'cambiar el gomet lo sustituye (un gomet por sesión y día)');
LC_Horario::poner_gomet('P1', sesion('P1')['id'], LUNES, '');
ok((int) semana('1ºA')['gomets_rojos'] === 1, 'quitar el gomet rehace el recuento');

$dia = LC_Horario::sesiones_del_dia('P3', LUNES);
ok(count($dia) === 1 && $dia[0]['color'] === 'verde', 'sesiones del día del profesor, con su gomet');
ok(LC_Horario::sesiones_del_dia('P3', '2026-11-21') === [], 'en fin de semana no hay sesiones');

seccion('Validación de fechas de los gomets');
$hoy = new DateTime('today', wp_timezone());
$lectivo = clone $hoy; while ((int) $lectivo->format('N') > 5) $lectivo->modify('-1 day');
ok(privado('LC_REST_Gomets', 'validar_fecha', $lectivo->format('Y-m-d')) === $lectivo->format('Y-m-d'), 'un día lectivo pasado o de hoy es válido');
ok(privado('LC_REST_Gomets', 'validar_fecha', (clone $hoy)->modify('+3 days')->format('Y-m-d')) !== (clone $hoy)->modify('+3 days')->format('Y-m-d'), 'no se aceptan días futuros');
$sabado = (clone $hoy)->modify('last saturday')->format('Y-m-d');
ok(privado('LC_REST_Gomets', 'validar_fecha', $sabado) === 'Ese día no hay clase.', 'no se aceptan fines de semana');
ok(privado('LC_REST_Gomets', 'validar_fecha', (clone $hoy)->modify('-400 days')->format('Y-m-d')) !== (clone $hoy)->modify('-400 days')->format('Y-m-d'), 'no se aceptan fechas de hace más de un año');
ok(privado('LC_REST_Gomets', 'validar_fecha', '2026-02-30') === 'Fecha no válida.', 'rechaza fechas imposibles');

seccion('Reimportar el horario');
LC_Horario::importar(CSV);
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_gomets") === 3, 'reimportar conserva los gomets de las sesiones que siguen');
LC_Horario::importar(str_replace("P4,1,2,TEC,A4,1ºA,1\n", '', CSV));
ok((int) $wpdb->get_var("SELECT COUNT(*) FROM wp_lc_gomets") === 2, 'los gomets de una sesión que desaparece se borran');
ok((int) semana('1ºB')['gomets_rojos'] === 0, 'y el recuento se rehace sin ellos');

// ======================================================================
seccion('Acumulado del Semáforo');
lc_reset();
LC_REST_Semaforo::guardar_semana('3ºA', 2026, 45, 10, 0, 0, '', 'test');
LC_REST_Semaforo::guardar_semana('3ºA', 2026, 46, 5, 0, 0, '', 'test');
ok((int) semana('3ºA', 45)['total'] === 10 && (int) semana('3ºA', 46)['total'] === 15, 'el total es acumulado: 10, luego 10 + 5');

LC_REST_Semaforo::guardar_semana('3ºA', 2026, 45, 0, 2, 0, '', 'test');
ok((int) semana('3ºA', 45)['total'] === -2, 'editar una semana pasada cambia su total');
ok((int) semana('3ºA', 46)['total'] === 3, '…y rehace en cascada las posteriores (−2 + 5)');

LC_REST_Semaforo::guardar_semana('3ºA', 2027, 1, 1, 0, 0, '', 'test');
ok((int) semana('3ºA', 1, 2027)['total'] === 4, 'el acumulado sigue a través del cambio de año');
LC_REST_Semaforo::guardar_semana('3ºB', 2026, 46, 3, 0, 0, '', 'test');
ok((int) semana('3ºB', 46)['total'] === 3, 'cada clase tiene su propio acumulado');

update_option(LC_Competiciones::OPT_SEMAFORO, ['verde' => 2, 'amarillo' => -1, 'rojo' => -3]);
ok((int) semana('3ºA', 45)['total'] === -2 && (int) semana('3ºA', 46)['total'] === 8 && (int) semana('3ºA', 1, 2027)['total'] === 10,
   'cambiar la puntuación (verde = 2) recalcula todas las semanas');

seccion('Registro semanal manual');
$r = LC_REST_Semaforo::upsert(req(['clase' => '1ºA', 'year' => 2026, 'week' => 47, 'gomets_verdes' => 31]));
ok(estado($r) === 400, 'rechaza más de 30 gomets de un color');
$r = LC_REST_Semaforo::upsert(req(['clase' => '1ºA', 'year' => 2026, 'week' => 47, 'gomets_rojos' => -1]));
ok(estado($r) === 400, 'rechaza recuentos negativos');
$r = LC_REST_Semaforo::upsert(req(['clase' => '1ºA', 'year' => 2026, 'week' => 47, 'gomets_verdes' => 20, 'gomets_amarillos' => 5, 'gomets_rojos' => 1]));
ok(datos($r)['ok'] === true && (int) datos($r)['current_total'] === 20 * 2 - 5 - 3, 'guarda un recuento válido con la puntuación vigente');

resumen();
