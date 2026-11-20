<?php
/**
 * Datos de demostración para WordPress Playground (ver blueprint.json).
 *
 * Simula un centro con dos cursos para que TODOS los filtros de la
 * clasificación tengan datos:
 * - Curso anterior, cerrado, con sus tres trimestres  -> filtro "Temporada".
 * - Curso actual, desde septiembre hasta hoy (incluida esta semana)
 *   -> "General", "Semanal", "Mensual" y "Trimestral" (1er trimestre).
 * - Cada competición en todos sus modos: deportes por enfrentamiento entre
 *   clases, alumno contra alumno y por marca; biblioteca por participantes y
 *   alumno contra alumno -> pestañas por deporte/actividad y ranking de alumnos.
 *
 * El curso actual se registra con gomets reales por hora (como en el centro);
 * el anterior, con el recuento semanal del Semáforo, para que la demo arranque
 * rápido.
 *
 * Solo se ejecuta en modo demo (constante LC_DEMO, que define el blueprint):
 * en una instalación real este archivo no hace nada.
 */
if (!defined('ABSPATH') || !defined('LC_DEMO') || !LC_DEMO) return;

function lc_demo_seed() {
    global $wpdb; $p = $wpdb->prefix;
    mt_srand(2026); // datos variados pero repetibles
    $quien = 'direccion@demo.edu';

    // ------------------------------------------------------------ calendario
    $tz = wp_timezone();
    $hoy = new DateTime('today', $tz);
    $curso = (int) $hoy->format('n') >= 9 ? (int) $hoy->format('Y') : (int) $hoy->format('Y') - 1;
    $lunes_de = function($fecha) use ($tz) { return (new DateTime($fecha, $tz))->modify('monday this week'); };

    $cursos = [];
    foreach ([$curso - 1, $curso] as $y) {
        $label = $y . '-' . substr((string) ($y + 1), 2);
        $cursos[$y] = [
            'label' => $label,
            'inicio' => $lunes_de("$y-09-15")->format('Y-m-d'),
            'fin' => ($y + 1) . '-06-19',
            'trimestres' => [
                ['label' => '1er trimestre', 'inicio' => $lunes_de("$y-09-15")->format('Y-m-d'), 'fin' => "$y-12-19", 'temporada' => $label],
                ['label' => '2º trimestre',  'inicio' => ($y + 1) . '-01-08', 'fin' => ($y + 1) . '-03-27', 'temporada' => $label],
                ['label' => '3er trimestre', 'inicio' => ($y + 1) . '-04-07', 'fin' => ($y + 1) . '-06-19', 'temporada' => $label],
            ],
        ];
    }
    // El curso actual está en marcha: sin fecha de fin.
    update_option(LC_Competiciones::OPT_TEMPORADAS, [
        ['label' => $cursos[$curso - 1]['label'], 'inicio' => $cursos[$curso - 1]['inicio'], 'fin' => $cursos[$curso - 1]['fin']],
        ['label' => $cursos[$curso]['label'], 'inicio' => $cursos[$curso]['inicio'], 'fin' => ''],
    ]);
    update_option(LC_Competiciones::OPT_TRIMESTRES, array_merge($cursos[$curso - 1]['trimestres'], $cursos[$curso]['trimestres']));

    // Semanas lectivas (lunes) de cada curso, sin Navidad ni Semana Santa.
    $semanas_de = function($inicio, $fin) use ($tz) {
        $out = [];
        for ($d = new DateTime($inicio, $tz); $d <= new DateTime($fin, $tz); $d->modify('+7 days')) {
            $md = $d->format('m-d');
            if ($md >= '12-22' || $md <= '01-07') continue;              // Navidad
            if ($d->format('m') === '04' && $d->format('d') <= '06') continue; // Semana Santa (aprox.)
            $out[] = clone $d;
        }
        return $out;
    };
    $sem_anterior = $semanas_de($cursos[$curso - 1]['inicio'], $cursos[$curso - 1]['fin']);
    $sem_actual = $semanas_de($cursos[$curso]['inicio'], $hoy->format('Y-m-d'));

    // ------------------------------------------------------------ competiciones
    // Un deporte y una actividad de cada modo, para que se vean todos.
    update_option(LC_Competiciones::OPT_DEPORTES, [
        ['value' => 'futbol',     'label' => 'Fútbol',     'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'baloncesto', 'label' => 'Baloncesto', 'modo' => 'enfrentamiento', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'pinpon',     'label' => 'Pin Pon',    'modo' => 'alumnos',        'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
        ['value' => 'carrera',    'label' => 'Carrera 60 m', 'modo' => 'individual',   'orden' => 'asc', 'puntos_por_posicion' => [5, 3, 2, 1], 'activo' => true],
    ]);
    $niveles = [['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1], ['label' => 'Insuficiente', 'puntos' => 0]];
    update_option(LC_Competiciones::OPT_ACTIVIDADES, [
        ['value' => 'podcast',    'label' => 'Podcast',    'modo' => 'participantes', 'activo' => true, 'niveles' => $niveles],
        ['value' => 'traduccion', 'label' => 'Traducción', 'modo' => 'participantes', 'activo' => true, 'niveles' => $niveles],
        ['value' => 'mitos',      'label' => 'Mitos',      'modo' => 'participantes', 'activo' => true, 'niveles' => $niveles],
        ['value' => 'ajedrez',    'label' => 'Ajedrez',    'modo' => 'alumnos', 'pts_victoria' => 3, 'pts_empate' => 1, 'pts_derrota' => 0, 'activo' => true],
    ]);

    $clases = LC_Competiciones::get_clases();
    $nc = count($clases);
    $al_azar = function($lista) { return $lista[mt_rand(0, count($lista) - 1)]; };
    // Cada clase tiene su "carácter", para que la clasificación no salga plana.
    $perfil = [];
    foreach ($clases as $i => $c) {
        $perfil[$c] = ['verde' => 0.55 + 0.05 * (($i * 3) % 7), 'rojo' => 0.03 + 0.02 * (($i * 5) % 4), 'deporte' => (($i * 7) % 5) / 4];
    }

    // ------------------------------------------------------------ accesos
    LC_Auth::add_user($quien);
    $profes = LC_Horario::get_profesores();
    if (count($profes) >= 2) {
        $profes[0]['email'] = $quien;            // acceso completo y además da clase
        $profes[1]['email'] = 'profe@demo.edu';  // solo gomets
        LC_Horario::save_profesores($profes);
    }

    // Alumnado: solo nombre de pila, como en el centro.
    $nombres = ['Lucía', 'Hugo', 'Martina', 'Leo', 'Sofía', 'Mateo', 'Valeria', 'Daniel', 'Julia', 'Álex',
                'Paula', 'Marco', 'Emma', 'Iker', 'Carla', 'Bruno', 'Noa', 'Pablo', 'Vega', 'Adrián',
                'Irene', 'Gael', 'Lola', 'Nico', 'Alba', 'Teo', 'Aitana', 'Unai', 'Sara', 'Marcos', 'Elsa', 'Jorge'];
    $alumnos = [];
    foreach ($clases as $i => $c) {
        for ($j = 0; $j < 4; $j++) {
            $nombre = $nombres[($i * 4 + $j) % count($nombres)];
            $alumnos[$c][] = ['id' => LC_Alumnos::find_or_create($nombre, $c), 'nombre' => $nombre];
        }
    }

    // ------------------------------------------------------------ semana a semana
    $notas_pos = ['Ayuda a un compañero', 'Mediación en un conflicto', 'Gran actitud en clase'];
    $notas_neg = ['Interrumpe la clase', 'Uso del móvil', 'Llega tarde'];
    $gana = function($a, $b) use ($perfil) {
        // La clase "más deportista" tiene algo más de probabilidad de ganar.
        $r = mt_rand() / mt_getrandmax() + ($perfil[$a]['deporte'] - $perfil[$b]['deporte']) * 0.3;
        return $r > 0.6 ? 'a' : ($r < 0.35 ? 'b' : 'empate');
    };

    $registrar_semana = function(DateTime $lunes) use ($p, $wpdb, $clases, $nc, $alumnos, $nombres, $al_azar, $gana, $notas_pos, $notas_neg, $quien) {
        $y = (int) $lunes->format('o'); $w = (int) $lunes->format('W');
        $dos = function() use ($clases) { $c = $clases; shuffle($c); return [$c[0], $c[1]]; };

        // Deportes entre clases.
        foreach (['futbol' => 4, 'baloncesto' => 40] as $dep => $max) {
            [$a, $b] = $dos();
            $res = $gana($a, $b);
            $ga = mt_rand(0, $max); $gb = mt_rand(0, $max);
            if ($res === 'a' && $ga <= $gb) $ga = $gb + 1;
            if ($res === 'b' && $gb <= $ga) $gb = $ga + 1;
            if ($res === 'empate') $gb = $ga;
            $wpdb->insert("{$p}lc_deportes", [
                'deporte' => $dep, 'iso_year' => $y, 'iso_week' => $w, 'equipo_a' => $a, 'equipo_b' => $b,
                'marcador_a' => $ga, 'marcador_b' => $gb,
                'pts_a' => $ga > $gb ? 3 : ($ga === $gb ? 1 : 0), 'pts_b' => $gb > $ga ? 3 : ($ga === $gb ? 1 : 0),
                'created_by' => $quien,
            ]);
        }
        // Pin Pon: alumno contra alumno.
        [$a, $b] = $dos();
        $res = $gana($a, $b);
        $wpdb->insert("{$p}lc_deportes", [
            'deporte' => 'pinpon', 'iso_year' => $y, 'iso_week' => $w, 'equipo_a' => $a, 'equipo_b' => $b,
            'marcador_a' => $res === 'a' ? 3 : mt_rand(0, 2), 'marcador_b' => $res === 'b' ? 3 : mt_rand(0, 2),
            'pts_a' => $res === 'a' ? 3 : ($res === 'empate' ? 1 : 0), 'pts_b' => $res === 'b' ? 3 : ($res === 'empate' ? 1 : 0),
            'alumno_a' => $al_azar($alumnos[$a])['id'], 'alumno_b' => $al_azar($alumnos[$b])['id'],
            'created_by' => $quien,
        ]);
        // Carrera: por marca, cada dos semanas.
        if ($w % 2 === 0) {
            $parts = [];
            foreach (array_slice((function($c) { shuffle($c); return $c; })($clases), 0, 5) as $c) {
                $parts[] = ['clase' => $c, 'nombre' => $al_azar($alumnos[$c])['nombre'], 'marca' => round(7.6 + mt_rand(0, 180) / 100, 2)];
            }
            usort($parts, function($x, $z) { return $x['marca'] <=> $z['marca']; });
            foreach ($parts as $i => &$pt) $pt['puntos'] = [5, 3, 2, 1][$i] ?? 0;
            unset($pt);
            $wpdb->insert("{$p}lc_deportes", [
                'deporte' => 'carrera', 'iso_year' => $y, 'iso_week' => $w, 'equipo_a' => '', 'equipo_b' => '',
                'participantes' => wp_json_encode($parts, JSON_UNESCAPED_UNICODE), 'created_by' => $quien,
            ]);
        }

        // Biblioteca: una actividad por participantes cada semana (rotando) y una partida de ajedrez.
        $act = ['podcast', 'traduccion', 'mitos'][$w % 3];
        $parts = [];
        for ($k = 0; $k < mt_rand(2, 4); $k++) {
            $c = $al_azar($clases);
            $parts[] = ['clase' => $c, 'nombre' => $al_azar($alumnos[$c])['nombre']];
        }
        $wpdb->insert("{$p}lc_biblioteca", [
            'actividad' => $act, 'iso_year' => $y, 'iso_week' => $w,
            'participantes' => wp_json_encode($parts, JSON_UNESCAPED_UNICODE),
            'puntos' => $al_azar([3, 3, 1, 0]), 'created_by' => $quien,
        ]);
        [$a, $b] = $dos();
        $res = $gana($a, $b);
        $wpdb->insert("{$p}lc_biblioteca", [
            'actividad' => 'ajedrez', 'iso_year' => $y, 'iso_week' => $w, 'participantes' => '',
            'clase_a' => $a, 'clase_b' => $b,
            'alumno_a' => $al_azar($alumnos[$a])['id'], 'alumno_b' => $al_azar($alumnos[$b])['id'],
            'ganador' => $res === 'a' ? 'clase-a' : ($res === 'b' ? 'clase-b' : 'empate'),
            'created_by' => $quien,
        ]);

        // Partes: varios positivos, algunos leves y, de vez en cuando, uno grave.
        for ($k = 0; $k < 5; $k++) {
            $c = $al_azar($clases);
            $r = mt_rand(1, 100);
            $tipo = $r <= 50 ? 'positivo' : ($r <= 94 ? 'leve' : 'grave');
            $wpdb->insert("{$p}lc_partes_alumnos", [
                'alumno_id' => $al_azar($alumnos[$c])['id'], 'clase' => $c,
                'iso_year' => $y, 'iso_week' => $w, 'tipo' => $tipo,
                'notas' => $tipo === 'positivo' ? $al_azar($notas_pos) : $al_azar($notas_neg),
                'created_by' => $quien,
            ]);
        }
    };

    foreach ($sem_anterior as $lunes) $registrar_semana($lunes);
    foreach ($sem_actual as $lunes) $registrar_semana($lunes);

    // Extraordinarias: dos por trimestre ya transcurrido.
    $extra = ['Recogida solidaria', 'Concurso de murales', 'Día de la Paz', 'Carrera solidaria', 'Feria de ciencias', 'Limpieza del patio'];
    // Curso anterior: cuatro repartidas por el curso. Actual: dos en lo que va.
    $planes = [
        [$sem_anterior, [0.15, 0.4, 0.6, 0.85], ['Recogida solidaria', 'Concurso de murales', 'Día de la Paz', 'Carrera solidaria']],
        [$sem_actual,   [0.3, 0.8],             ['Feria de ciencias', 'Limpieza del patio']],
    ];
    foreach ($planes as [$semanas, $momentos, $nombres_act]) {
        if (!$semanas) continue;
        foreach ($momentos as $k => $f) {
            $lunes = $semanas[(int) floor((count($semanas) - 1) * $f)];
            $orden = $clases; shuffle($orden);
            foreach ([10, 8, 5, 3] as $i => $pts) {
                $wpdb->replace("{$p}lc_extraordinarias", [
                    'nombre' => $nombres_act[$k],
                    'iso_year' => (int) $lunes->format('o'), 'iso_week' => (int) $lunes->format('W'),
                    'clase' => $orden[$i], 'puntos' => $pts, 'created_by' => $quien,
                ]);
            }
        }
    }

    // Tiempo de aprendizaje: cada mes lectivo de los dos cursos hasta hoy.
    for ($m = new DateTime("{$cursos[$curso - 1]['inicio']}", $tz); $m <= $hoy; $m->modify('first day of next month')) {
        if (in_array((int) $m->format('n'), [7, 8], true)) continue;
        foreach ($clases as $c) {
            $wpdb->replace("{$p}lc_tiempo_aprendizaje", [
                'clase' => $c, 'iso_year' => (int) $m->format('Y'), 'iso_month' => (int) $m->format('n'),
                'puntos' => mt_rand(2, 10), 'created_by' => $quien,
            ]);
        }
    }

    // ------------------------------------------------------------ Semáforo
    // Curso anterior: recuento semanal directo (más rápido para la demo).
    $pts = LC_Competiciones::get_semaforo();
    foreach ($sem_anterior as $lunes) {
        foreach ($clases as $c) {
            $v = 0; $a = 0; $r = 0;
            for ($k = 0; $k < 30; $k++) {
                $x = mt_rand() / mt_getrandmax();
                if ($x < $perfil[$c]['rojo']) $r++; elseif ($x < $perfil[$c]['rojo'] + $perfil[$c]['verde']) $v++; else $a++;
            }
            LC_REST_Semaforo::guardar_semana($c, (int) $lunes->format('o'), (int) $lunes->format('W'), $v, $a, $r, null, $quien);
        }
    }
    // Curso actual: un gomet por sesión y día, como lo pone el profesorado.
    $sesiones = $wpdb->get_results("SELECT id, dia, profesor, clases FROM {$p}lc_horario WHERE cuenta = 1 AND clases <> ''", ARRAY_A);
    $ahora = current_time('mysql');
    foreach ($sem_actual as $lunes) {
        foreach ($sesiones as $s) {
            $dia = (clone $lunes)->modify('+' . ((int) $s['dia'] - 1) . ' days');
            if ($dia > $hoy) continue; // esta semana, solo hasta hoy
            $clase = explode(',', $s['clases'])[0];
            $pf = $perfil[$clase] ?? ['verde' => 0.7, 'rojo' => 0.05];
            $x = mt_rand() / mt_getrandmax();
            $color = $x < $pf['rojo'] ? 'rojo' : ($x < $pf['rojo'] + $pf['verde'] ? 'verde' : 'amarillo');
            $wpdb->replace("{$p}lc_gomets", [
                'sesion_id' => $s['id'], 'fecha' => $dia->format('Y-m-d'), 'profesor' => $s['profesor'],
                'color' => $color, 'updated_at' => $ahora,
            ]);
        }
    }
    LC_Horario::recalcular_todo();

    // ------------------------------------------------------------ páginas
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
