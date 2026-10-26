<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Liga Convivencia > Profesorado (1.0): la lista del Semáforo (qué email es de
 * qué profesor del horario) y el horario en sí. Igual que Usuarios y Alumnos,
 * sus formularios no pasan por la Settings API: cada acción lleva su nonce y se
 * gestiona en maybe_handle_action() (admin_init).
 */
class LC_Admin_Profesorado {
    const PAGE = 'liga-creus-profesorado';

    public static function init() {
        add_action('admin_init', [__CLASS__, 'maybe_handle_action']);
    }

    private static function url($tab, $args = []) {
        return add_query_arg(array_merge(['page' => self::PAGE, 'tab' => $tab], $args), admin_url('admin.php'));
    }

    private static function avisos($textos) {
        $msg = sanitize_key($_GET['lc_msg'] ?? '');
        if (isset($textos[$msg])) {
            echo '<div class="notice notice-success"><p>' . esc_html($textos[$msg]) . '</p></div>';
        }
        $error = sanitize_text_field(wp_unslash($_GET['lc_error'] ?? ''));
        if ($error !== '') echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
    }

    private static function form_abrir($accion, $tab, $extra = '') {
        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" ' . $extra . '>';
        wp_nonce_field('lc_profesorado_action');
        echo '<input type="hidden" name="lc_action" value="' . esc_attr($accion) . '" />';
        echo '<input type="hidden" name="page" value="' . self::PAGE . '" />';
        echo '<input type="hidden" name="tab" value="' . esc_attr($tab) . '" />';
    }

    // ------------------------------------------------------------ Profesores

    public static function render_profesores_tab() {
        global $wpdb;
        self::avisos([
            'guardado' => 'Profesorado guardado.',
            'creado' => 'Profesor añadido.',
            'eliminado' => 'Profesor eliminado.',
        ]);

        $clases_por_prof = [];
        foreach ($wpdb->get_results("SELECT profesor, COUNT(*) n FROM {$wpdb->prefix}lc_horario WHERE cuenta = 1 AND clases <> '' GROUP BY profesor") as $f) {
            $clases_por_prof[$f->profesor] = (int) $f->n;
        }

        echo '<div class="lc-admin-card">';
        echo '<p><strong>Lista del Semáforo.</strong> Cada profesor con email entra en el panel y ve <strong>solo sus clases</strong> del día que elija, '
            . 'según su horario, para poner un gomet por hora. <strong>No puede tocar el resto de competiciones</strong>: eso queda para '
            . 'las personas de <a href="' . esc_url(admin_url('admin.php?page=liga-creus-acceso&tab=usuarios')) . '">Acceso &gt; Usuarios</a>, '
            . 'que pueden hacerlo todo (y, si además están aquí, también poner sus gomets).</p>';
        echo '<p>Como en Usuarios, se entra solo con el email, sin contraseña: quien conozca el email de un profesor puede poner gomets en su nombre.</p>';

        self::form_abrir('profesores_guardar', 'profesores');
        echo '<table class="widefat lc-admin-table"><thead><tr>'
            . '<th style="width:90px">Iniciales</th><th>Nombre</th><th style="width:280px">Email</th>'
            . '<th style="width:120px">Horas con gomet</th><th style="width:110px"></th>'
            . '</tr></thead><tbody>';
        foreach (LC_Horario::get_profesores() as $i => $p) {
            $ini = $p['iniciales'];
            $horas = $clases_por_prof[$ini] ?? 0;
            echo '<tr class="lc-admin-row">';
            echo '<td><code>' . esc_html($ini) . '</code><input type="hidden" name="prof[' . $i . '][iniciales]" value="' . esc_attr($ini) . '" /></td>';
            echo '<td><input type="text" name="prof[' . $i . '][nombre]" value="' . esc_attr($p['nombre']) . '" style="width:100%" /></td>';
            echo '<td><input type="email" name="prof[' . $i . '][email]" value="' . esc_attr($p['email'] ?? '') . '" placeholder="sin acceso" style="width:100%" /></td>';
            echo '<td>' . ($horas ? esc_html($horas) . ' / semana' : '<span class="description">ninguna</span>') . '</td>';
            echo '<td><button type="submit" class="button lc-admin-remove-row" name="eliminar" value="' . esc_attr($ini) . '" '
                . 'onclick="return confirm(\'¿Quitar a ' . esc_js($p['nombre']) . ' de la lista? Su horario se queda, pero nadie podrá poner sus gomets.\');">Quitar</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button('Guardar emails y nombres');
        echo '</form></div>';

        echo '<div class="lc-admin-card" style="margin-top:20px">';
        echo '<h3 style="margin-top:0">+ Añadir profesor</h3>';
        echo '<p class="description">Las iniciales tienen que coincidir con las del horario (columna "profesor").</p>';
        self::form_abrir('profesor_add', 'profesores');
        echo '<input type="text" name="iniciales" placeholder="Iniciales (p. ej. ABCG)" required style="width:160px" /> ';
        echo '<input type="text" name="nombre" placeholder="Nombre y apellidos" required style="width:260px" /> ';
        echo '<input type="email" name="email" placeholder="email (opcional)" style="width:220px" /> ';
        echo '<button type="submit" class="button button-primary">Añadir</button>';
        echo '</form></div>';
    }

    // --------------------------------------------------------------- Horario

    public static function render_horario_tab() {
        global $wpdb;
        self::avisos(['importado' => 'Horario importado. Los gomets de las sesiones que siguen igual se han conservado.']);

        echo '<div class="lc-admin-card">';
        echo '<h3 style="margin-top:0">Comprobación: horas por clase</h3>';
        echo '<p>Cada clase tiene 6 horas diarias, 30 a la semana. Si a alguna le faltan, en esas horas no recibirá gomet: '
            . 'suele ser una celda mal copiada del horario.</p>';
        echo '<table class="widefat lc-admin-table" style="max-width:700px"><thead><tr><th>Clase</th><th>Horas</th><th>Sin sesión</th></tr></thead><tbody>';
        foreach (LC_Horario::comprobar_cobertura() as $clase => $c) {
            $ok = $c['cubiertas'] === 5 * LC_Horario::HORAS;
            echo '<tr><td>' . esc_html($clase) . '</td><td>' . ($ok ? '✅ ' : '⚠️ ') . esc_html($c['cubiertas']) . ' / ' . (5 * LC_Horario::HORAS) . '</td>'
                . '<td>' . esc_html(implode(', ', $c['huecos'])) . '</td></tr>';
        }
        echo '</tbody></table></div>';

        echo '<div class="lc-admin-card" style="margin-top:20px">';
        echo '<h3 style="margin-top:0">Horario de cada profesor</h3>';
        echo '<p>Las sesiones tachadas son apoyos (C1, C0, AL): no ponen gomet. "Cuenta para" es a qué clases de la Liga va el gomet; '
            . 'si sale más de una, sus alumnos están mezclados a esa hora (religión, optativas) y a cada clase le cuenta el gomet de menor valor.</p>';
        $nombres = [];
        foreach (LC_Horario::get_profesores() as $p) $nombres[$p['iniciales']] = $p['nombre'];
        $filas = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}lc_horario ORDER BY profesor, dia, hora");
        $por_prof = [];
        foreach ($filas as $f) $por_prof[$f->profesor][] = $f;
        foreach ($por_prof as $ini => $sesiones) {
            echo '<details style="margin:6px 0"><summary><strong>' . esc_html($ini) . '</strong> — ' . esc_html($nombres[$ini] ?? 'sin nombre en Profesores')
                . ' (' . count($sesiones) . ')</summary>';
            echo '<table class="widefat striped" style="margin:8px 0 12px;max-width:700px"><thead><tr><th>Día</th><th>Hora</th><th>Materia</th><th>Grupo</th><th>Cuenta para</th></tr></thead><tbody>';
            foreach ($sesiones as $s) {
                $estilo = $s->cuenta ? '' : ' style="text-decoration:line-through;opacity:.6"';
                echo '<tr' . $estilo . '><td>' . esc_html(LC_Horario::DIAS[$s->dia]) . '</td><td>' . esc_html($s->hora) . 'ª</td>'
                    . '<td>' . esc_html($s->materia) . '</td><td>' . esc_html($s->grupo . ($s->n ? " (+{$s->n})" : '')) . '</td>'
                    . '<td>' . esc_html($s->cuenta ? (str_replace(',', ', ', $s->clases) ?: '— (no es de la Liga)') : 'apoyo') . '</td></tr>';
            }
            echo '</tbody></table></details>';
        }
        echo '</div>';

        echo '<div class="lc-admin-card" style="margin-top:20px">';
        echo '<h3 style="margin-top:0">Importar horario</h3>';
        echo '<p>Sustituye el horario entero. Formato CSV con cabecera <code>profesor,dia,hora,materia,aula,grupo,n</code>: '
            . 'día 1 (lunes) a 5 (viernes), hora 1 a 6, y <code>n</code> el número que acompaña al grupo en el horario (vacío si no lleva). '
            . 'Los gomets ya puestos se conservan en las sesiones que no cambian.</p>';
        $csv = "profesor,dia,hora,materia,aula,grupo,n\n";
        foreach ($filas as $f) {
            $csv .= implode(',', [$f->profesor, $f->dia, $f->hora, $f->materia, $f->aula, $f->grupo, $f->n ?: '']) . "\n";
        }
        self::form_abrir('horario_importar', 'horario', 'onsubmit="return confirm(\'¿Sustituir el horario entero por este?\');"');
        echo '<textarea name="csv" rows="14" style="width:100%;font-family:monospace">' . esc_textarea($csv) . '</textarea>';
        submit_button('Importar horario');
        echo '</form></div>';
    }

    // ------------------------------------------------------------- acciones

    public static function maybe_handle_action() {
        $accion = $_POST['lc_action'] ?? '';
        if (!in_array($accion, ['profesores_guardar', 'profesor_add', 'horario_importar'], true)) return;
        if (!current_user_can('manage_options')) return;
        check_admin_referer('lc_profesorado_action');

        if ($accion === 'horario_importar') {
            $res = LC_Horario::importar(wp_unslash($_POST['csv'] ?? ''));
            $args = is_int($res) ? ['lc_msg' => 'importado'] : ['lc_error' => $res];
            wp_safe_redirect(self::url('horario', $args));
            exit;
        }

        $profes = LC_Horario::get_profesores();

        if ($accion === 'profesor_add') {
            $ini = strtoupper(sanitize_text_field(wp_unslash($_POST['iniciales'] ?? '')));
            $nombre = sanitize_text_field(wp_unslash($_POST['nombre'] ?? ''));
            $email = strtolower(sanitize_email(wp_unslash($_POST['email'] ?? '')));
            $error = self::validar_alta($profes, $ini, $nombre, $email);
            if ($error) { wp_safe_redirect(self::url('profesores', ['lc_error' => $error])); exit; }
            $profes[] = ['iniciales' => $ini, 'nombre' => $nombre, 'email' => $email];
            LC_Horario::save_profesores($profes);
            wp_safe_redirect(self::url('profesores', ['lc_msg' => 'creado']));
            exit;
        }

        // profesores_guardar (o "Quitar", que va en el mismo formulario).
        $quitar = strtoupper(sanitize_text_field(wp_unslash($_POST['eliminar'] ?? '')));
        $nuevos = [];
        $vistos = [];
        foreach ((array) ($_POST['prof'] ?? []) as $fila) {
            $ini = strtoupper(sanitize_text_field(wp_unslash($fila['iniciales'] ?? '')));
            if ($ini === '' || $ini === $quitar) continue;
            $email = strtolower(sanitize_email(wp_unslash($fila['email'] ?? '')));
            $crudo = trim(wp_unslash($fila['email'] ?? ''));
            if ($crudo !== '' && !is_email($email)) {
                wp_safe_redirect(self::url('profesores', ['lc_error' => "El email de {$ini} no es válido: no se ha guardado nada."]));
                exit;
            }
            if ($email !== '' && isset($vistos[$email])) {
                wp_safe_redirect(self::url('profesores', ['lc_error' => "El email {$email} está repetido ({$vistos[$email]} y {$ini}): no se ha guardado nada."]));
                exit;
            }
            if ($email !== '') $vistos[$email] = $ini;
            $nuevos[] = ['iniciales' => $ini, 'nombre' => sanitize_text_field(wp_unslash($fila['nombre'] ?? '')), 'email' => $email];
        }
        LC_Horario::save_profesores($nuevos);
        wp_safe_redirect(self::url('profesores', ['lc_msg' => $quitar !== '' ? 'eliminado' : 'guardado']));
        exit;
    }

    private static function validar_alta($profes, $ini, $nombre, $email) {
        if ($ini === '' || $nombre === '') return 'Faltan las iniciales o el nombre.';
        if ($email !== '' && !is_email($email)) return 'Email no válido.';
        foreach ($profes as $p) {
            if ($p['iniciales'] === $ini) return "Ya hay un profesor con las iniciales {$ini}.";
            if ($email !== '' && strtolower($p['email'] ?? '') === $email) return "Ese email ya es de {$p['iniciales']}.";
        }
        return null;
    }
}
