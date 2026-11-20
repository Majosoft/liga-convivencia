<?php
/**
 * Datos de demostración para WordPress Playground (ver blueprint.json).
 *
 * Simula ocho semanas de Liga: gomets de todo el profesorado ficticio,
 * partidos, partes, biblioteca, actividades extraordinarias y tiempo de
 * aprendizaje, más los usuarios y páginas para probarlo todo.
 *
 * Solo se ejecuta en modo demo (constante LC_DEMO, que define el blueprint):
 * en una instalación real este archivo no hace nada.
 */
if (!defined('ABSPATH') || !defined('LC_DEMO') || !LC_DEMO) return;

function lc_demo_seed() {
    global $wpdb; $p = $wpdb->prefix;
    mt_srand(2026); // datos variados pero repetibles

    $clases = LC_Competiciones::get_clases();
    // Cada clase tiene su "carácter": probabilidad de verde y de rojo.
    $perfil = [];
    foreach ($clases as $i => $c) {
        $perfil[$c] = ['verde' => 0.55 + 0.05 * (($i * 3) % 7), 'rojo' => 0.03 + 0.02 * (($i * 5) % 4)];
    }

    $hoy = new DateTime('now', wp_timezone());
    $lunes_actual = (clone $hoy)->modify('monday this week');
    $semanas = [];
    for ($k = 8; $k >= 1; $k--) $semanas[] = (clone $lunes_actual)->modify("-{$k} weeks");

    // Temporada en curso desde la primera semana simulada.
    update_option(LC_Competiciones::OPT_TEMPORADAS, [[
        'label' => 'Curso demo', 'inicio' => $semanas[0]->format('Y-m-d'), 'fin' => '',
    ]]);

    // Accesos: una persona con acceso completo (además da clase) y un profesor
    // que solo pone gomets.
    LC_Auth::add_user('direccion@demo.edu');
    $profes = LC_Horario::get_profesores();
    if (count($profes) >= 2) {
        $profes[0]['email'] = 'direccion@demo.edu';
        $profes[1]['email'] = 'profe@demo.edu';
        LC_Horario::save_profesores($profes);
    }

    // Alumnado: solo nombre de pila, como en el centro.
    $nombres = ['Lucía', 'Hugo', 'Martina', 'Leo', 'Sofía', 'Mateo', 'Valeria', 'Daniel', 'Julia', 'Álex',
                'Paula', 'Marco', 'Emma', 'Iker', 'Carla', 'Bruno', 'Noa', 'Pablo', 'Vega', 'Adrián'];
    $alumnos = [];
    foreach ($clases as $i => $c) {
        for ($j = 0; $j < 4; $j++) {
            $alumnos[$c][] = LC_Alumnos::find_or_create($nombres[($i * 4 + $j) % count($nombres)], $c);
        }
    }

    // Gomets: uno por sesión con gomet y día lectivo.
    $sesiones = $wpdb->get_results("SELECT id, dia, profesor, clases FROM {$p}lc_horario WHERE cuenta = 1 AND clases <> ''", ARRAY_A);
    $ahora = current_time('mysql');
    foreach ($semanas as $lunes) {
        foreach ($sesiones as $s) {
            $fecha = (clone $lunes)->modify('+' . ((int) $s['dia'] - 1) . ' days')->format('Y-m-d');
            $clase = explode(',', $s['clases'])[0];
            $pf = $perfil[$clase] ?? ['verde' => 0.7, 'rojo' => 0.05];
            $r = mt_rand() / mt_getrandmax();
            $color = $r < $pf['rojo'] ? 'rojo' : ($r < $pf['rojo'] + $pf['verde'] ? 'verde' : 'amarillo');
            $wpdb->replace("{$p}lc_gomets", [
                'sesion_id' => $s['id'], 'fecha' => $fecha, 'profesor' => $s['profesor'],
                'color' => $color, 'updated_at' => $ahora,
            ]);
        }
    }
    LC_Horario::recalcular_todo();

    $notas_pos = ['Ayuda a un compañero', 'Mediación en un conflicto', 'Gran actitud en clase'];
    $notas_neg = ['Interrumpe la clase', 'Uso del móvil', 'Llega tarde'];

    foreach ($semanas as $n => $lunes) {
        $y = (int) $lunes->format('o'); $w = (int) $lunes->format('W');

        // Deportes: dos partidos por semana.
        foreach (['futbol', 'baloncesto'] as $dep) {
            $par = $clases; shuffle($par);
            $ga = mt_rand(0, $dep === 'futbol' ? 4 : 40); $gb = mt_rand(0, $dep === 'futbol' ? 4 : 40);
            $wpdb->insert("{$p}lc_deportes", [
                'deporte' => $dep, 'iso_year' => $y, 'iso_week' => $w,
                'equipo_a' => $par[0], 'equipo_b' => $par[1],
                'marcador_a' => $ga, 'marcador_b' => $gb,
                'pts_a' => $ga > $gb ? 3 : ($ga === $gb ? 1 : 0),
                'pts_b' => $gb > $ga ? 3 : ($ga === $gb ? 1 : 0),
                'created_by' => 'direccion@demo.edu',
            ]);
        }

        // Partes: varios positivos, algunos leves y, de vez en cuando, uno grave.
        for ($k = 0; $k < 6; $k++) {
            $c = $clases[mt_rand(0, count($clases) - 1)];
            $r = mt_rand(1, 100);
            $tipo = $r <= 50 ? 'positivo' : ($r <= 93 ? 'leve' : 'grave');
            $wpdb->insert("{$p}lc_partes_alumnos", [
                'alumno_id' => $alumnos[$c][mt_rand(0, 3)], 'clase' => $c,
                'iso_year' => $y, 'iso_week' => $w, 'tipo' => $tipo,
                'notas' => $tipo === 'positivo' ? $notas_pos[mt_rand(0, 2)] : $notas_neg[mt_rand(0, 2)],
                'created_by' => 'direccion@demo.edu',
            ]);
        }

        // Biblioteca: un podcast cada dos semanas.
        if ($n % 2 === 0) {
            $c1 = $clases[mt_rand(0, count($clases) - 1)]; $c2 = $clases[mt_rand(0, count($clases) - 1)];
            $wpdb->insert("{$p}lc_biblioteca", [
                'actividad' => 'podcast', 'iso_year' => $y, 'iso_week' => $w,
                'participantes' => wp_json_encode([
                    ['clase' => $c1, 'nombre' => $nombres[mt_rand(0, 19)]],
                    ['clase' => $c2, 'nombre' => $nombres[mt_rand(0, 19)]],
                ], JSON_UNESCAPED_UNICODE),
                'puntos' => [3, 1, 3][mt_rand(0, 2)], 'created_by' => 'direccion@demo.edu',
            ]);
        }
    }

    // Extraordinarias: dos actividades con su reparto de puntos.
    foreach ([['Recogida solidaria', 2], ['Concurso de murales', 5]] as [$nombre, $idx]) {
        $lunes = $semanas[$idx];
        $orden = $clases; shuffle($orden);
        foreach ([10, 8, 5, 3] as $i => $pts) {
            $wpdb->replace("{$p}lc_extraordinarias", [
                'nombre' => $nombre, 'iso_year' => (int) $lunes->format('o'), 'iso_week' => (int) $lunes->format('W'),
                'clase' => $orden[$i], 'puntos' => $pts, 'created_by' => 'direccion@demo.edu',
            ]);
        }
    }

    // Tiempo de aprendizaje: los dos meses anteriores.
    foreach ([1, 2] as $atras) {
        $mes = (clone $hoy)->modify('first day of this month')->modify("-{$atras} months");
        foreach ($clases as $c) {
            $wpdb->replace("{$p}lc_tiempo_aprendizaje", [
                'clase' => $c, 'iso_year' => (int) $mes->format('Y'), 'iso_month' => (int) $mes->format('n'),
                'puntos' => mt_rand(2, 10), 'created_by' => 'direccion@demo.edu',
            ]);
        }
    }

    // Páginas del panel y de la clasificación.
    $paginas = [
        'clasificacion' => ['Clasificación', '[liga_creus_clasificacion]'],
        'panel'         => ['Panel del profesorado', '[lc_panel]'],
    ];
    foreach ($paginas as $slug => [$titulo, $contenido]) {
        if (!get_page_by_path($slug)) {
            wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug,
                            'post_title' => $titulo, 'post_content' => $contenido]);
        }
    }
    update_option('blogname', 'IES Demo · Liga Convivencia');
    update_option('permalink_structure', '/%postname%/');
    flush_rewrite_rules();
}
