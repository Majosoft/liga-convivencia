<?php
if (!defined('ABSPATH')) { exit; }

class LC_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_init', [__CLASS__, 'maybe_handle_nueva_temporada']);
        add_action('admin_init', [__CLASS__, 'maybe_handle_usuario_action']);
        add_action('admin_init', [__CLASS__, 'maybe_handle_alumno_action']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    // Categorías del menú: cada una es su propia página (submenú real de
    // WordPress), agrupando las pestañas internas que tengan sentido juntas.
    // "liga-creus" (Clases) usa el slug del propio padre para ocupar el hueco
    // que WordPress crea automáticamente para el menú de primer nivel.
    private static function categorias() {
        return [
            'liga-creus' => [
                'titulo' => 'Clases',
                'tabs' => ['clases' => 'Clases'],
            ],
            'liga-creus-competicion' => [
                'titulo' => 'Competición',
                // La clave 'actividades' es el identificador interno (URL ?tab=,
                // grupo lc_settings_actividades y render_actividades_field); aquí
                // solo se cambia la etiqueta que ve el usuario.
                'tabs' => [
                    'deportes' => 'Deportes',
                    'actividades' => 'Biblioteca',
                    'semaforo' => 'Semáforo',
                    'partes' => 'Partes',
                ],
            ],
            'liga-creus-calendario' => [
                'titulo' => 'Calendario',
                'tabs' => ['temporadas' => 'Temporadas', 'trimestres' => 'Trimestres'],
            ],
            // 1.0: horario del profesorado (LC_Admin_Profesorado).
            'liga-creus-profesorado' => [
                'titulo' => 'Horario',
                'tabs' => ['horario' => 'Horario'],
            ],
            'liga-creus-alumnos' => [
                'titulo' => 'Alumnos',
                'tabs' => ['alumnos' => 'Alumnos'],
            ],
            'liga-creus-acceso' => [
                'titulo' => 'Acceso',
                // Las dos listas de acceso (1.0): 'profesores' = solo gomets del
                // Semáforo (LC_Admin_Profesorado); 'usuarios' = formularios y
                // resto de registros (acceso completo).
                'tabs' => [
                    'profesores' => 'Lista Semáforo',
                    'usuarios' => 'Lista Formularios',
                    'app' => 'App profesorado',
                    'app_semaforo' => 'App Semáforo',
                ],
            ],
        ];
    }

    /**
     * Añade el menú de primer nivel "Liga Convivencia" y sus 4 submenús/categorías
     * (antes era una única página con 7 pestañas sueltas).
     */
    public static function add_settings_page() {
        add_menu_page('Liga Convivencia', 'Liga Convivencia', 'manage_options', 'liga-creus', [__CLASS__, 'render_settings_page'], 'dashicons-awards', 26);
        foreach (self::categorias() as $slug => $cat) {
            add_submenu_page('liga-creus', $cat['titulo'], $cat['titulo'], 'manage_options', $slug, [__CLASS__, 'render_settings_page']);
        }
    }

    /**
     * Carga el JS de filas dinámicas solo en nuestras páginas de ajustes
     */
    public static function enqueue($hook) {
        // No hardcodeamos los hooks de submenú como "liga-creus_page_...": WordPress
        // los deriva del MENU TITLE de add_menu_page() ("Liga Convivencia" ->
        // sanitize_title -> "liga-convivencia"), no del slug ("liga-creus"). Por eso
        // se calculan aquí con get_plugin_page_hookname() en vez de adivinar el string.
        $hooks = ['toplevel_page_liga-creus'];
        foreach (self::categorias() as $slug => $cat) {
            if ($slug === 'liga-creus') continue; // ya cubierto por el hook de toplevel
            $hooks[] = get_plugin_page_hookname($slug, 'liga-creus');
        }
        if (!in_array($hook, $hooks, true)) return;
        wp_enqueue_media();
        wp_enqueue_style('lc-admin', LC_URL . 'assets/css/admin.css', [], LC_VERSION);
        wp_enqueue_script('lc-admin-competiciones', LC_URL . 'assets/js/admin-competiciones.js', [], LC_VERSION, true);
    }

    /**
     * Registra las opciones de configuración
     */
    public static function register_settings() {
        // ===== Deportes =====
        register_setting('lc_settings_deportes', LC_Competiciones::OPT_DEPORTES, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_deportes'],
            'default' => LC_Competiciones::DEFAULT_DEPORTES,
        ]);

        add_settings_section('lc_section_deportes', 'Deportes', function(){
            echo '<p>Configura los deportes disponibles y cómo se puntúan. '
                . 'Desmarca <strong>Activo</strong> para dejar de ofrecerlo en el panel (por ejemplo, al cambiar de trimestre) sin perder su histórico: seguirá apareciendo en la Clasificación. '
                . '<strong>No elimines uno que ya tenga partidos guardados</strong> — usa la casilla Activo en vez de borrarlo (los partidos ya guardados dejarían de aparecer en la clasificación).</p>';
        }, 'lc_settings_deportes');

        // ===== Actividades =====
        register_setting('lc_settings_actividades', LC_Competiciones::OPT_ACTIVIDADES, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_actividades'],
            'default' => LC_Competiciones::DEFAULT_ACTIVIDADES,
        ]);

        add_settings_section('lc_section_actividades', 'Actividades de Biblioteca', function(){
            echo '<p>Configura las actividades disponibles y cómo se puntúan. '
                . 'Desmarca <strong>Activo</strong> para dejar de ofrecerla en el panel sin perder su histórico (seguirá apareciendo en la Clasificación). '
                . '<strong>No elimines una que ya tenga actividades guardadas</strong> por el mismo motivo que los deportes.</p>';
        }, 'lc_settings_actividades');

        // ===== Semáforo =====
        register_setting('lc_settings_semaforo', LC_Competiciones::OPT_SEMAFORO, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_semaforo'],
            'default' => LC_Competiciones::DEFAULT_SEMAFORO,
        ]);

        add_settings_section('lc_section_semaforo', 'Puntos del Semáforo', function(){
            echo '<p>Cuántos puntos suma o resta cada gomet. Escribe el valor <strong>con su signo</strong>: '
                . 'un número negativo resta (ej. <code>-3</code>) y uno positivo suma.</p>'
                . '<p><strong>Al guardar se recalculan los totales de todas las semanas ya registradas</strong>, '
                . 'porque el Semáforo guarda un acumulado: si no se rehiciera, las semanas antiguas seguirían '
                . 'sumando con la puntuación vieja y el total dejaría de cuadrar con los gomets que se ven.</p>';
        }, 'lc_settings_semaforo');

        // ===== Partes =====
        register_setting('lc_settings_partes', LC_Competiciones::OPT_PARTES, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_partes'],
            'default' => LC_Competiciones::DEFAULT_PARTES,
        ]);

        add_settings_section('lc_section_partes', 'Puntos de los Partes', function(){
            echo '<p>Cuántos puntos suma o resta cada parte. Escribe el valor <strong>con su signo</strong>: '
                . 'un número negativo resta (ej. <code>-10</code>) y uno positivo suma.</p>'
                . '<p>Los puntos de Partes se calculan al consultar la Clasificación, no se guardan: '
                . '<strong>cambiar estos valores afecta también al histórico ya registrado</strong>.</p>';
        }, 'lc_settings_partes');

        // ===== Clases =====
        register_setting('lc_settings_clases', LC_Competiciones::OPT_CLASES, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_clases'],
            'default' => LC_Competiciones::DEFAULT_CLASES,
        ]);

        add_settings_section('lc_section_clases', 'Clases / Grupos', function(){
            echo '<p>Configura las clases o grupos que participan en la competición. '
                . 'Desmarca <strong>Activo</strong> para dejar de ofrecerla en el panel (por ejemplo, al empezar una temporada nueva) sin perder su histórico: seguirá apareciendo en la Clasificación. '
                . '<strong>No renombres ni elimines una clase que ya tenga datos guardados</strong> — el nombre exacto es lo que vincula los registros históricos (Semáforo, Deportes, Partes, Biblioteca, Escudos) con la clase. Renombrarla o eliminarla hace que esos datos dejen de aparecer en la clasificación; usa la casilla Activo en su lugar.</p>';
        }, 'lc_settings_clases');

        // ===== Trimestres =====
        register_setting('lc_settings_trimestres', LC_Competiciones::OPT_TRIMESTRES, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_trimestres'],
            'default' => LC_Competiciones::DEFAULT_TRIMESTRES,
        ]);

        add_settings_section('lc_section_trimestres', 'Trimestres', function(){
            echo '<p>Define las fechas de inicio y fin de cada trimestre (o el periodo que uses) para poder filtrar la Clasificación pública por trimestre. '
                . 'Editar las fechas de un trimestre ya usado no recalcula nada retroactivamente, solo cambia qué semanas se incluyen la próxima vez que se consulte.</p>';
        }, 'lc_settings_trimestres');

        // ===== Temporadas =====
        register_setting('lc_settings_temporadas', LC_Competiciones::OPT_TEMPORADAS, [
            'type' => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_temporadas'],
            'default' => LC_Competiciones::DEFAULT_TEMPORADAS,
        ]);

        add_settings_section('lc_section_temporadas', 'Temporadas', function(){
            echo '<p>Cada fila es un curso escolar. Deja <strong>Fin</strong> en blanco en la temporada que está en curso. '
                . 'Para empezar un curso nuevo decidiendo qué deportes/actividades/clases se mantienen, usa el botón <strong>Empezar Curso</strong> de arriba en vez de editar esta tabla a mano.</p>';
        }, 'lc_settings_temporadas');

        // ===== App profesorado =====
        register_setting('lc_settings_app', LC_App::OPT, [
            'type' => 'array',
            'sanitize_callback' => ['LC_App', 'sanitize'],
            'default' => LC_App::DEFAULTS,
        ]);
        register_setting('lc_settings_app_semaforo', LC_App_Semaforo::OPT, [
            'type' => 'array',
            'sanitize_callback' => ['LC_App_Semaforo', 'sanitize'],
            'default' => LC_App_Semaforo::DEFAULTS,
        ]);

        add_settings_section('lc_section_app', 'App para el profesorado', function(){
            echo '<p>El panel de registro también se puede usar como una app instalada en el móvil, la tableta o el ordenador. '
                . 'Comparte el enlace con el profesorado: al abrirlo, el navegador les ofrece <strong>Instalar app</strong> '
                . '(Chrome/Edge, Android) o, en iPhone/iPad, <strong>Compartir → Añadir a pantalla de inicio</strong> (Safari). '
                . 'Se entra con el mismo email autorizado de la pestaña Usuarios.</p>';
        }, 'lc_settings_app');
    }

    /**
     * Renderiza la página de configuración de la categoría actual (con pestañas
     * internas si la categoría agrupa más de una sección).
     */
    public static function render_settings_page() {
        $categorias = self::categorias();
        $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'liga-creus';
        if (!isset($categorias[$page])) $page = 'liga-creus';
        $cat = $categorias[$page];
        $tabs = $cat['tabs'];
        $tabKeys = array_keys($tabs);

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : $tabKeys[0];
        if (!in_array($tab, $tabKeys, true)) $tab = $tabKeys[0];

        echo '<div class="wrap"><h1>Liga Convivencia — ' . esc_html($cat['titulo']) . '</h1>';

        if (count($tabs) > 1) {
            echo '<h2 class="nav-tab-wrapper">';
            foreach ($tabs as $key => $label) {
                $url = esc_url(add_query_arg(['page' => $page, 'tab' => $key], admin_url('admin.php')));
                $class = 'nav-tab' . ($tab === $key ? ' nav-tab-active' : '');
                echo '<a href="' . $url . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
            }
            echo '</h2>';
        }

        if ($tab === 'temporadas' && ($_GET['accion'] ?? '') === 'nueva') {
            self::render_wizard_nueva_temporada();
            echo '</div>';
            return;
        }

        if ($tab === 'temporadas') {
            self::render_temporadas_intro();
        }

        // La pestaña Usuarios no pasa por la Settings API (options.php): cada
        // acción (añadir, eliminar) es su propio mini-formulario con su propio
        // nonce, gestionado en maybe_handle_usuario_action(). Mezclarlo con el
        // form genérico de abajo significaría anidar <form>, inválido en HTML.
        if ($tab === 'usuarios') {
            self::render_usuarios_tab();
            echo '</div>';
            return;
        }

        if ($tab === 'profesores' || $tab === 'horario') {
            $tab === 'profesores' ? LC_Admin_Profesorado::render_profesores_tab() : LC_Admin_Profesorado::render_horario_tab();
            echo '</div>';
            return;
        }

        if ($tab === 'alumnos') {
            self::render_alumnos_tab();
            echo '</div>';
            return;
        }

        $group = 'lc_settings_' . $tab;

        // Estos campos se pintan aquí directamente en vez de vía
        // add_settings_field(): ese camino siempre envuelve el campo en un
        // <table class="form-table"><tr><th>...</th><td>, y el <th> (vacío o
        // con la etiqueta repetida) es lo que generaba el hueco/línea suelta
        // a la izquierda del campo. Los inputs siguen guardándose igual
        // porque solo dependen del name="" y de settings_fields()+
        // register_setting(), no de add_settings_field().
        $tableRenderers = [
            'deportes' => 'render_deportes_field',
            'actividades' => 'render_actividades_field',
            'clases' => 'render_clases_field',
            'semaforo' => 'render_semaforo_field',
            'partes' => 'render_partes_field',
            'trimestres' => 'render_trimestres_field',
            'temporadas' => 'render_temporadas_field',
            'app' => 'render_app_field',
            'app_semaforo' => 'render_app_semaforo_field',
        ];

        echo '<div class="lc-admin-card"><form method="post" action="options.php">';
        settings_fields($group);
        echo '<div class="lc-settings-fields">';
        do_settings_sections($group);
        if (isset($tableRenderers[$tab])) {
            self::{$tableRenderers[$tab]}();
        }
        echo '</div>';
        submit_button('Guardar cambios');
        echo '</form></div>';
        echo '<p class="lc-admin-signature">Desarrollado por Majosoft</p>';
        echo '</div>';
    }

    // Aviso + botón de acceso al asistente, encima de la tabla manual de Temporadas.
    private static function render_temporadas_intro() {
        if (($_GET['lc_nueva_temporada'] ?? '') === '1') {
            echo '<div class="notice notice-success"><p>Curso iniciado correctamente.</p></div>';
        }
        $url = esc_url(add_query_arg(['page' => 'liga-creus-calendario', 'tab' => 'temporadas', 'accion' => 'nueva'], admin_url('admin.php')));
        echo '<p><a href="' . $url . '" class="button button-primary">+ Empezar Curso</a></p>';
    }

    // =========================================================
    // Render de las tablas editables
    // =========================================================

    // Pestaña Acceso > Usuarios: lista de emails autorizados, con altas y bajas.
    // No usa la Settings API (ver render_settings_page()), así que se renderiza
    // entera aquí en vez de seguir el patrón render_X_field() de las demás.
    public static function render_usuarios_tab() {
        $back = admin_url('admin.php?page=liga-creus-acceso&tab=usuarios');

        $notices = [
            'creado' => 'Usuario creado correctamente.',
            'eliminado' => 'Usuario eliminado correctamente.',
            'email_ok' => 'WordPress ha aceptado el email de prueba. Comprueba que ha llegado (y que no está en spam).',
        ];
        $msg = sanitize_key($_GET['lc_msg'] ?? '');
        if (isset($notices[$msg])) {
            echo '<div class="notice notice-success"><p>' . esc_html($notices[$msg]) . '</p></div>';
        }
        $error = sanitize_text_field(wp_unslash($_GET['lc_error'] ?? ''));
        if ($error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        echo '<div class="lc-admin-card">';
        echo '<p><strong>Lista Formularios: acceso completo.</strong> Estas personas usan los formularios del panel y meten todos los registros '
            . '(Deportes, Partes, Biblioteca, Extraordinarias, el Semáforo semanal...). Para quien solo tiene que poner gomets en sus clases está la '
            . '<a href="' . esc_url(admin_url('admin.php?page=liga-creus-acceso&tab=profesores')) . '">Lista Semáforo</a>.</p>';
        echo '<p>'
            . 'Para entrar, cada persona escribe su email y recibe en él un <strong>código de 6 dígitos</strong> que caduca en 10 minutos: '
            . 'conocer un email de la lista no basta. Eliminar a un usuario le bloquea el acceso de inmediato, aunque tuviera una sesión abierta.</p>';

        // Sin correo no hay login: botón para comprobar que WordPress envía emails.
        $admin_email = wp_get_current_user()->user_email;
        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" style="margin:12px 0">';
        wp_nonce_field('lc_usuario_action');
        echo '<input type="hidden" name="lc_action" value="email_prueba" />';
        echo '<button type="submit" class="button">Enviar email de prueba a ' . esc_html($admin_email) . '</button> ';
        echo '<span class="description">Los códigos de acceso llegan por email: si este no llega, configura el envío de correo (p. ej. con el plugin WP Mail SMTP).</span>';
        echo '</form>';

        $usuarios = LC_Auth::get_users();
        echo '<table class="widefat lc-admin-table"><thead><tr>'
            . '<th>Email</th><th style="width:160px">Creado</th><th style="width:160px"></th>'
            . '</tr></thead><tbody>';
        if (empty($usuarios)) {
            echo '<tr><td colspan="3">Todavía no hay ningún usuario. Añade el primero abajo.</td></tr>';
        }
        foreach ($usuarios as $u) {
            self::render_usuario_row($u);
        }
        echo '</tbody></table>';
        echo '</div>';

        echo '<div class="lc-admin-card" style="margin-top:20px">';
        echo '<h3 style="margin-top:0;padding-top:0;border-top:none">+ Añadir usuario</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" class="lc-usuario-form">';
        wp_nonce_field('lc_usuario_action');
        echo '<input type="hidden" name="lc_action" value="usuario_add" />';
        echo '<input type="hidden" name="page" value="liga-creus-acceso" />';
        echo '<input type="hidden" name="tab" value="usuarios" />';
        echo '<input type="email" name="usuario_email" placeholder="profesor@correo.edu" required style="width:260px" /> ';
        echo '<button type="submit" class="button button-primary">Añadir usuario</button>';
        echo '</form>';
        echo '</div>';
    }

    // Fila de la tabla de Usuarios: el email no se edita aquí (cambiar el
    // identificador de un usuario no tiene sentido — se borra y se crea otro),
    // solo se puede eliminar.
    private static function render_usuario_row($u) {
        $email = $u['email'] ?? '';
        $creado = $u['creado'] ?? '';
        echo '<tr class="lc-admin-row">';
        echo '<td>' . esc_html($email) . '</td>';
        echo '<td>' . esc_html($creado) . '</td>';
        echo '<td class="lc-usuario-acciones">';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" class="lc-usuario-delete-form" onsubmit="return confirm(\'¿Eliminar a ' . esc_js($email) . '? Perderá el acceso de inmediato.\');">';
        wp_nonce_field('lc_usuario_action');
        echo '<input type="hidden" name="lc_action" value="usuario_delete" />';
        echo '<input type="hidden" name="page" value="liga-creus-acceso" />';
        echo '<input type="hidden" name="tab" value="usuarios" />';
        echo '<input type="hidden" name="usuario_email" value="' . esc_attr($email) . '" />';
        echo '<button type="submit" class="button lc-admin-remove-row">Eliminar</button>';
        echo '</form>';

        echo '</td></tr>';
    }

    // Gestiona los 2 mini-formularios de Usuarios (añadir / eliminar). Igual que
    // maybe_handle_nueva_temporada(): un admin_init normal con su propio nonce,
    // en vez de la Settings API.
    public static function maybe_handle_usuario_action() {
        $action = $_POST['lc_action'] ?? '';
        if (!in_array($action, ['usuario_add', 'usuario_delete', 'email_prueba'], true)) return;
        if (!current_user_can('manage_options')) return;
        check_admin_referer('lc_usuario_action');

        $back = admin_url('admin.php?page=liga-creus-acceso&tab=usuarios');

        if ($action === 'email_prueba') {
            $ok = wp_mail(wp_get_current_user()->user_email, 'Liga Convivencia: email de prueba',
                "Si lees esto, WordPress envía correos y el profesorado recibirá sus códigos de acceso.");
            wp_safe_redirect(add_query_arg($ok ? ['lc_msg' => 'email_ok']
                : ['lc_error' => 'WordPress no ha podido enviar el email. Configura el envío de correo (p. ej. WP Mail SMTP).'], $back));
            exit;
        }
        $email = sanitize_text_field($_POST['usuario_email'] ?? '');

        if ($action === 'usuario_add') {
            $result = LC_Auth::add_user($email);
            $msg = 'creado';
        } else {
            $result = LC_Auth::delete_user($email);
            $msg = 'eliminado';
        }

        if ($result === true) {
            wp_safe_redirect(add_query_arg(['lc_msg' => $msg], $back));
        } else {
            wp_safe_redirect(add_query_arg(['lc_error' => $result], $back));
        }
        exit;
    }

    // Barra sobre la tabla con el botón de añadir, alineado a la derecha.
    // El JS localiza el botón por id (ver setupGroup en admin-competiciones.js),
    // así que da igual dónde se pinte.
    private static function render_add_button($id, $label) {
        echo '<div class="lc-admin-toolbar">'
            . '<button type="button" class="button button-secondary" id="' . esc_attr($id) . '">' . esc_html($label) . '</button>'
            . '</div>';
    }

    // Pestaña Alumnos. Igual que Usuarios, se renderiza entera aquí: los alumnos
    // están en la tabla lc_alumnos, no en una opción, así que la Settings API no
    // aplica. Cada acción es su propio mini-formulario con su nonce.
    public static function render_alumnos_tab() {
        $notices = [
            'creado'     => 'Alumno añadido correctamente.',
            'actualizado'=> 'Alumno actualizado.',
            'eliminado'  => 'Alumno eliminado.',
        ];
        $msg = sanitize_key($_GET['lc_msg'] ?? '');
        if (isset($notices[$msg])) {
            echo '<div class="notice notice-success"><p>' . esc_html($notices[$msg]) . '</p></div>';
        }
        $error = sanitize_text_field(wp_unslash($_GET['lc_error'] ?? ''));
        if ($error !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
        }

        echo '<div class="lc-admin-card">';
        echo '<p>Alumnos que participan en las pruebas individuales y a los que se asignan los partes de conducta. '
            . 'También se pueden dar de alta sobre la marcha desde los formularios del panel; aquí se corrigen nombres, '
            . 'se cambian de clase o se dan de baja. <strong>Al cambiar un alumno de clase, sus puntos ya registrados '
            . 'siguen contando para la clase con la que los consiguió</strong>: solo cambia a dónde irán los siguientes.</p>';

        self::render_alumno_add_form();
        echo '</div>';

        $alumnos = LC_Alumnos::todos();
        echo '<div class="lc-admin-card" style="margin-top:20px">';
        if (empty($alumnos)) {
            echo '<p>Todavía no hay ningún alumno.</p>';
        } else {
            echo '<table class="widefat lc-admin-table"><thead><tr>'
                . '<th>Nombre</th><th style="width:140px">Clase</th><th style="width:80px">Activo</th><th style="width:220px"></th>'
                . '</tr></thead><tbody>';
            foreach ($alumnos as $a) {
                self::render_alumno_row($a);
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    private static function render_clase_options($seleccionada) {
        $html = '';
        foreach (LC_Competiciones::get_clases() as $clase) {
            $html .= '<option value="' . esc_attr($clase) . '"' . selected($seleccionada, $clase, false) . '>' . esc_html($clase) . '</option>';
        }
        return $html;
    }

    private static function render_alumno_add_form() {
        echo '<h3 style="margin-top:0;padding-top:0;border-top:none">+ Añadir alumno</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" class="lc-usuario-form">';
        wp_nonce_field('lc_alumno_action');
        echo '<input type="hidden" name="lc_action" value="alumno_add" />';
        echo '<input type="text" name="alumno_nombre" placeholder="Nombre y apellidos" required style="width:280px" /> ';
        echo '<select name="alumno_clase" required>' . self::render_clase_options('') . '</select> ';
        echo '<button type="submit" class="button button-primary">Añadir alumno</button>';
        echo '</form>';
    }

    private static function render_alumno_row($a) {
        $id = (int) $a['id'];
        echo '<tr class="lc-admin-row">';
        echo '<td colspan="4" class="lc-usuario-acciones">';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" class="lc-usuario-pin-form">';
        wp_nonce_field('lc_alumno_action');
        echo '<input type="hidden" name="lc_action" value="alumno_update" />';
        echo '<input type="hidden" name="alumno_id" value="' . esc_attr($id) . '" />';
        echo '<input type="text" name="alumno_nombre" value="' . esc_attr($a['nombre']) . '" style="width:260px" required /> ';
        echo '<select name="alumno_clase">' . self::render_clase_options($a['clase']) . '</select> ';
        echo '<label><input type="checkbox" name="alumno_activo" value="1"' . checked(!empty($a['activo']), true, false) . ' /> Activo</label> ';
        echo '<button type="submit" class="button">Guardar</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin.php')) . '" class="lc-usuario-delete-form" onsubmit="return confirm(\'¿Eliminar a ' . esc_js($a['nombre']) . '? Se borran también sus datos personales (su nombre en los registros y las notas de sus partes). Los puntos de su clase se conservan.\');">';
        wp_nonce_field('lc_alumno_action');
        echo '<input type="hidden" name="lc_action" value="alumno_delete" />';
        echo '<input type="hidden" name="alumno_id" value="' . esc_attr($id) . '" />';
        echo '<button type="submit" class="button lc-admin-remove-row">Eliminar</button>';
        echo '</form>';

        echo '</td></tr>';
    }

    public static function maybe_handle_alumno_action() {
        $action = $_POST['lc_action'] ?? '';
        if (!in_array($action, ['alumno_add', 'alumno_update', 'alumno_delete'], true)) return;
        if (!current_user_can('manage_options')) return;
        check_admin_referer('lc_alumno_action');

        $back = admin_url('admin.php?page=liga-creus-alumnos&tab=alumnos');
        $nombre = sanitize_text_field(wp_unslash($_POST['alumno_nombre'] ?? ''));
        $clase  = sanitize_text_field($_POST['alumno_clase'] ?? '');
        $id     = (int) ($_POST['alumno_id'] ?? 0);

        if ($action === 'alumno_add') {
            $nuevo = LC_Alumnos::find_or_create($nombre, $clase);
            if (!$nuevo) {
                wp_safe_redirect(add_query_arg(['lc_error' => 'Indica el nombre del alumno y su clase.'], $back));
                exit;
            }
            $msg = 'creado';
        } elseif ($action === 'alumno_update') {
            if (!LC_Alumnos::update($id, $nombre, $clase, !empty($_POST['alumno_activo']))) {
                wp_safe_redirect(add_query_arg(['lc_error' => 'No se pudo actualizar el alumno.'], $back));
                exit;
            }
            $msg = 'actualizado';
        } else {
            LC_Alumnos::delete($id);
            $msg = 'eliminado';
        }

        wp_safe_redirect(add_query_arg(['lc_msg' => $msg], $back));
        exit;
    }

    public static function render_deportes_field() {
        $deportes = LC_Competiciones::get_deportes();
        self::render_add_button('lc-admin-add-deporte', '+ Añadir deporte');
        echo '<table class="widefat lc-admin-table" id="lc-admin-rows-deportes"><thead><tr>'
            . '<th style="width:110px">Slug</th><th>Nombre</th><th style="width:70px">Activo</th><th style="width:260px">Modo</th><th>Parámetros</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($deportes as $i => $d) {
            self::render_deporte_row('lc_config_deportes', $i, $d);
        }
        echo '</tbody></table>';
        echo '<template id="lc-admin-template-deporte">';
        self::render_deporte_row('lc_config_deportes', '__INDEX__', ['value' => '', 'label' => '', 'modo' => 'enfrentamiento', 'activo' => true]);
        echo '</template>';
    }

    public static function render_actividades_field() {
        $actividades = LC_Competiciones::get_actividades();
        self::render_add_button('lc-admin-add-actividad', '+ Añadir actividad');
        echo '<table class="widefat lc-admin-table" id="lc-admin-rows-actividades"><thead><tr>'
            . '<th style="width:110px">Slug</th><th>Nombre</th><th style="width:70px">Activo</th><th style="width:260px">Modo</th><th>Parámetros</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($actividades as $i => $a) {
            self::render_actividad_row('lc_config_actividades', $i, $a);
        }
        echo '</tbody></table>';
        echo '<template id="lc-admin-template-actividad">';
        self::render_actividad_row('lc_config_actividades', '__INDEX__', ['value' => '', 'label' => '', 'modo' => 'participantes', 'activo' => true]);
        echo '</template>';
    }

    // Tabla simple "concepto -> puntos", compartida por Semáforo y Partes: son
    // listas cerradas (tres conceptos fijos), así que no llevan añadir/eliminar
    // filas como las de Deportes o Clases.
    private static function render_puntos_field($name, $cfg, $filas) {
        echo '<table class="widefat lc-admin-table"><thead><tr>'
            . '<th>Concepto</th><th style="width:160px">Puntos</th><th>Efecto</th>'
            . '</tr></thead><tbody>';
        foreach ($filas as $clave => $fila) {
            list($icono, $etiqueta) = $fila;
            $valor = (int) ($cfg[$clave] ?? 0);
            echo '<tr class="lc-admin-row">';
            echo '<td>' . $icono . ' ' . esc_html($etiqueta) . '</td>';
            echo '<td><input type="number" step="1" name="' . esc_attr($name) . '[' . esc_attr($clave) . ']" value="' . esc_attr($valor) . '" /></td>';
            echo '<td class="lc-puntos-efecto">' . esc_html(self::describe_puntos($valor)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    // Traduce el número a lenguaje llano, para que no haya duda con el signo.
    private static function describe_puntos($valor) {
        if ($valor === 0) return 'No cuenta';
        $abs = abs($valor);
        $palabra = $abs === 1 ? 'punto' : 'puntos';
        return ($valor > 0 ? 'Suma ' : 'Resta ') . $abs . ' ' . $palabra . ' cada uno';
    }

    public static function render_semaforo_field() {
        self::render_puntos_field('lc_config_semaforo', LC_Competiciones::get_semaforo(), [
            'verde'    => ['🟢', 'Gomet verde'],
            'amarillo' => ['🟡', 'Gomet amarillo'],
            'rojo'     => ['🔴', 'Gomet rojo'],
        ]);
    }

    public static function render_partes_field() {
        self::render_puntos_field('lc_config_partes', LC_Competiciones::get_partes(), [
            'positivo' => ['✅', 'Parte positivo'],
            'leve'     => ['⚠️', 'Parte leve'],
            'grave'    => ['❌', 'Parte grave'],
        ]);
    }

    private static function render_activo_checkbox($name, $i, $item) {
        $activo = $item['activo'] ?? true;
        echo '<td style="text-align:center">'
            . '<input type="checkbox" name="' . esc_attr($name) . '[' . $i . '][activo]" value="1"' . ($activo ? ' checked' : '') . ' title="Visible para registrar nuevos resultados en el panel" />'
            . '</td>';
    }

    private static function render_deporte_row($name, $i, $d) {
        $modo = $d['modo'] ?? 'enfrentamiento';
        echo '<tr class="lc-admin-row" data-modo="' . esc_attr($modo) . '">';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][value]" value="' . esc_attr($d['value'] ?? '') . '" placeholder="slug" /></td>';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][label]" value="' . esc_attr($d['label'] ?? '') . '" placeholder="Nombre visible" style="width:100%" /></td>';
        self::render_activo_checkbox($name, $i, $d);
        echo '<td><select name="' . esc_attr($name) . '[' . $i . '][modo]" class="lc-admin-modo">'
            . '<option value="enfrentamiento"' . selected($modo, 'enfrentamiento', false) . '>Enfrentamiento (clase A vs clase B)</option>'
            . '<option value="alumnos"' . selected($modo, 'alumnos', false) . '>Individual (alumno A vs alumno B)</option>'
            . '<option value="individual"' . selected($modo, 'individual', false) . '>Por marca o tiempo</option>'
            . '</select></td>';
        echo '<td>';
        self::render_enfrentamiento_params($name, $i, $d);
        echo '<span class="lc-admin-param lc-admin-param-individual lc-param-group">';
        $orden = $d['orden'] ?? 'asc';
        $puntos = implode(',', $d['puntos_por_posicion'] ?? [5, 3, 2, 1]);
        echo self::param_field('Orden', '<select name="' . esc_attr($name) . '[' . $i . '][orden]">'
            . '<option value="asc"' . selected($orden, 'asc', false) . '>Menor gana</option>'
            . '<option value="desc"' . selected($orden, 'desc', false) . '>Mayor gana</option>'
            . '</select>');
        echo self::param_field('Puntos por posición', '<input type="text" name="' . esc_attr($name) . '[' . $i . '][puntos_por_posicion]" value="' . esc_attr($puntos) . '" placeholder="5,3,2,1" />');
        echo '</span>';
        echo '</td>';
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    private static function render_actividad_row($name, $i, $a) {
        $modo = $a['modo'] ?? 'participantes';
        echo '<tr class="lc-admin-row" data-modo="' . esc_attr($modo) . '">';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][value]" value="' . esc_attr($a['value'] ?? '') . '" placeholder="slug" /></td>';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][label]" value="' . esc_attr($a['label'] ?? '') . '" placeholder="Nombre visible" style="width:100%" /></td>';
        self::render_activo_checkbox($name, $i, $a);
        echo '<td><select name="' . esc_attr($name) . '[' . $i . '][modo]" class="lc-admin-modo">'
            . '<option value="participantes"' . selected($modo, 'participantes', false) . '>Participantes + valoración</option>'
            . '<option value="enfrentamiento"' . selected($modo, 'enfrentamiento', false) . '>Enfrentamiento (clase A vs clase B)</option>'
            . '<option value="alumnos"' . selected($modo, 'alumnos', false) . '>Individual (alumno A vs alumno B)</option>'
            . '</select></td>';
        echo '<td>';
        echo '<span class="lc-admin-param lc-admin-param-participantes lc-param-group">';
        $niveles_lines = [];
        foreach (($a['niveles'] ?? []) as $n) { $niveles_lines[] = $n['label'] . '=' . $n['puntos']; }
        $niveles_raw = esc_textarea(implode("\n", $niveles_lines));
        echo self::param_field('Niveles', '<textarea name="' . esc_attr($name) . '[' . $i . '][niveles_raw]" rows="3" placeholder="Excelente=3&#10;Regular=1&#10;Insuficiente=0">' . $niveles_raw . '</textarea>');
        echo '</span>';
        self::render_enfrentamiento_params($name, $i, $a);
        echo '</td>';
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    // Pestaña Acceso > App profesorado. El icono reutiliza el selector de escudo
    // de Clases (mismo marcado y mismo JS, ver bindEscudo en admin-competiciones.js).
    // App Semáforo (1.0): el mismo formulario, sobre LC_App_Semaforo.
    public static function render_app_semaforo_field() {
        echo '<p>App aparte, <strong>solo del Semáforo</strong>: cada profesor de '
            . '<a href="' . esc_url(admin_url('admin.php?page=liga-creus-acceso&tab=profesores')) . '">Lista Semáforo</a> la instala en su móvil '
            . 'y al abrirla ve únicamente sus clases del día para poner los gomets. Comparte el inicio de sesión por email con el resto del panel.</p>';
        self::render_app_field('LC_App_Semaforo');
    }

    public static function render_app_field($app = 'LC_App') {
        $cfg  = $app::get();
        $name = $app::OPT;
        $url  = $app::url();
        $id   = (int) $cfg['icono'];
        $img  = $id ? wp_get_attachment_image_url($id, 'thumbnail') : '';

        echo '<p><strong>Enlace para el profesorado:</strong><br>'
            . '<input type="text" readonly value="' . esc_attr($url) . '" style="width:100%;max-width:480px" onclick="this.select()" /> '
            . '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" class="button">Abrir</a></p>';

        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="lc-app-slug">Dirección</label></th><td>'
            . '<code>' . esc_html(home_url('/')) . '</code>'
            . '<input type="text" id="lc-app-slug" name="' . esc_attr($name) . '[slug]" value="' . esc_attr($cfg['slug']) . '" style="width:200px" /><code>/</code>'
            . '<p class="description">Solo letras minúsculas, números y guiones. No uses la dirección de una página que ya exista en la web. '
            . 'Si la cambias, quien ya tenga la app instalada tendrá que volver a instalarla desde el enlace nuevo.</p>'
            . '</td></tr>';

        echo '<tr><th scope="row"><label for="lc-app-nombre">Nombre de la app</label></th><td>'
            . '<input type="text" id="lc-app-nombre" name="' . esc_attr($name) . '[nombre]" value="' . esc_attr($cfg['nombre']) . '" style="width:320px" />'
            . '</td></tr>';

        echo '<tr><th scope="row"><label for="lc-app-corto">Nombre bajo el icono</label></th><td>'
            . '<input type="text" id="lc-app-corto" name="' . esc_attr($name) . '[nombre_corto]" value="' . esc_attr($cfg['nombre_corto']) . '" maxlength="12" style="width:160px" />'
            . '<p class="description">Máximo 12 caracteres; es lo que se ve debajo del icono en el móvil.</p>'
            . '</td></tr>';

        echo '<tr class="lc-app-icono-row"><th scope="row">Icono</th><td>';
        echo '<div class="lc-escudo-cell ' . ($img ? 'has-escudo' : 'is-empty') . '">';
        echo '<input type="hidden" class="lc-escudo-input" name="' . esc_attr($name) . '[icono]" value="' . esc_attr($id) . '" />';
        echo '<button type="button" class="button lc-escudo-select">Seleccionar imagen</button>';
        echo '<div class="lc-escudo-wrap">';
        echo '<img class="lc-escudo-preview"' . ($img ? ' src="' . esc_url($img) . '"' : '') . ' alt="" />';
        echo '<span class="lc-escudo-acciones">';
        echo '<button type="button" class="button lc-escudo-edit" title="Cambiar el icono" aria-label="Cambiar el icono">'
            . '<span class="dashicons dashicons-edit" aria-hidden="true"></span></button>';
        echo '<button type="button" class="button lc-escudo-remove" title="Quitar el icono" aria-label="Quitar el icono">'
            . '<span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
        echo '</span></div></div>';
        echo '<p class="description">Imagen <strong>cuadrada</strong> PNG de al menos 512×512 píxeles, sin fondo transparente. '
            . 'Sin imagen se usa el icono ' . ($app === 'LC_App_Semaforo' ? 'del semáforo' : 'del trofeo') . ' que trae el plugin.</p>';
        echo '</td></tr>';

        echo '</tbody></table>';
    }

    public static function render_clases_field() {
        $clases = LC_Competiciones::get_clases_config();
        self::render_add_button('lc-admin-add-clase', '+ Añadir clase');
        echo '<table class="widefat lc-admin-table" id="lc-admin-rows-clases"><thead><tr>'
            . '<th>Clase / Grupo</th><th style="width:70px">Activo</th><th style="width:220px">Escudo</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($clases as $i => $c) {
            self::render_clase_row('lc_config_clases', $i, $c);
        }
        echo '</tbody></table>';
        echo '<template id="lc-admin-template-clase">';
        self::render_clase_row('lc_config_clases', '__INDEX__', ['nombre' => '', 'activo' => true, 'escudo' => 0]);
        echo '</template>';
    }

    private static function render_clase_row($name, $i, $c) {
        $id = (int) ($c['escudo'] ?? 0);
        $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : '';
        echo '<tr class="lc-admin-row lc-escudo-row">';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][nombre]" value="' . esc_attr($c['nombre'] ?? '') . '" placeholder="Ej. 1ºA" style="width:100%" /></td>';
        self::render_activo_checkbox($name, $i, $c);
        // La celda lleva su estado en una clase (is-empty / has-escudo) y es el CSS
        // quien decide qué se ve: sin imagen, el botón de subirla; con imagen, la
        // miniatura y los botones de Editar/Quitar.
        //
        // La miniatura y sus botones van dentro de .lc-escudo-wrap y no sueltos en
        // el <td>: los td de estas tablas están forzados a "display: table-cell
        // !important" (es lo que sostiene el diseño de fila-tarjeta, ver admin.css),
        // así que la celda no puede ser ella misma el contenedor flex.
        echo '<td class="lc-escudo-cell ' . ($url ? 'has-escudo' : 'is-empty') . '">';
        echo '<input type="hidden" class="lc-escudo-input" name="' . esc_attr($name) . '[' . $i . '][escudo]" value="' . esc_attr($id) . '" />';
        echo '<button type="button" class="button lc-escudo-select">Seleccionar imagen</button>';
        echo '<div class="lc-escudo-wrap">';
        echo '<img class="lc-escudo-preview"' . ($url ? ' src="' . esc_url($url) . '"' : '') . ' alt="" />';
        echo '<span class="lc-escudo-acciones">';
        // Solo icono: el nombre accesible lo da aria-label, y title muestra el tooltip.
        echo '<button type="button" class="button lc-escudo-edit" title="Cambiar la imagen del escudo" aria-label="Cambiar la imagen del escudo">'
            . '<span class="dashicons dashicons-edit" aria-hidden="true"></span></button>';
        echo '<button type="button" class="button lc-escudo-remove" title="Quitar la imagen del escudo" aria-label="Quitar la imagen del escudo">'
            . '<span class="dashicons dashicons-trash" aria-hidden="true"></span></button>';
        echo '</span>';
        echo '</div>';
        echo '</td>';
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    public static function render_trimestres_field() {
        $trimestres = LC_Competiciones::get_trimestres();
        $activa = LC_Competiciones::get_temporada_activa();
        self::render_add_button('lc-admin-add-trimestre', '+ Añadir trimestre');
        echo '<table class="widefat lc-admin-table" id="lc-admin-rows-trimestres"><thead><tr>'
            . '<th>Nombre</th><th style="width:160px">Inicio</th><th style="width:160px">Fin</th><th style="width:180px">Temporada</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($trimestres as $i => $t) {
            self::render_trimestre_row('lc_config_trimestres', $i, $t);
        }
        echo '</tbody></table>';
        echo '<template id="lc-admin-template-trimestre">';
        self::render_trimestre_row('lc_config_trimestres', '__INDEX__', ['label' => '', 'inicio' => '', 'fin' => '', 'temporada' => $activa['label'] ?? '']);
        echo '</template>';
    }

    // Selector de Temporada para una fila de Trimestre: a qué curso pertenece. Dos
    // trimestres pueden repetir el mismo nombre ("1er Trimestre") en temporadas
    // distintas sin chocar, porque se distinguen por (temporada, nombre).
    private static function render_temporada_select($name, $i, $selected) {
        echo '<td><select name="' . esc_attr($name) . '[' . $i . '][temporada]">';
        echo '<option value=""' . selected($selected, '', false) . '>— Sin asignar —</option>';
        foreach (LC_Competiciones::get_temporadas() as $t) {
            $label = $t['label'] ?? '';
            echo '<option value="' . esc_attr($label) . '"' . selected($selected, $label, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
    }

    private static function render_trimestre_row($name, $i, $t) {
        echo '<tr class="lc-admin-row">';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][label]" value="' . esc_attr($t['label'] ?? '') . '" placeholder="Ej. 1er Trimestre" style="width:100%" /></td>';
        echo '<td><input type="date" name="' . esc_attr($name) . '[' . $i . '][inicio]" value="' . esc_attr($t['inicio'] ?? '') . '" /></td>';
        echo '<td><input type="date" name="' . esc_attr($name) . '[' . $i . '][fin]" value="' . esc_attr($t['fin'] ?? '') . '" /></td>';
        self::render_temporada_select($name, $i, $t['temporada'] ?? '');
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    public static function render_temporadas_field() {
        $temporadas = LC_Competiciones::get_temporadas();
        self::render_add_button('lc-admin-add-temporada', '+ Añadir temporada');
        echo '<table class="widefat lc-admin-table" id="lc-admin-rows-temporadas"><thead><tr>'
            . '<th>Nombre</th><th style="width:160px">Inicio</th><th style="width:160px">Fin (vacío = en curso)</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($temporadas as $i => $t) {
            self::render_temporada_row('lc_config_temporadas', $i, $t);
        }
        echo '</tbody></table>';
        echo '<template id="lc-admin-template-temporada">';
        self::render_temporada_row('lc_config_temporadas', '__INDEX__', ['label' => '', 'inicio' => '', 'fin' => '']);
        echo '</template>';
    }

    private static function render_temporada_row($name, $i, $t) {
        echo '<tr class="lc-admin-row">';
        echo '<td><input type="text" name="' . esc_attr($name) . '[' . $i . '][label]" value="' . esc_attr($t['label'] ?? '') . '" placeholder="Ej. 2026/2027" style="width:100%" /></td>';
        echo '<td><input type="date" name="' . esc_attr($name) . '[' . $i . '][inicio]" value="' . esc_attr($t['inicio'] ?? '') . '" /></td>';
        echo '<td><input type="date" name="' . esc_attr($name) . '[' . $i . '][fin]" value="' . esc_attr($t['fin'] ?? '') . '" /></td>';
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    // Asistente "Empezar Curso": un formulario aparte (no pasa por la
    // Settings API) porque en un solo envío toca 4 opciones distintas (Temporadas +
    // el flag "activo" de Deportes/Actividades/Clases).
    private static function render_wizard_nueva_temporada() {
        $back_url = esc_url(add_query_arg(['page' => 'liga-creus-calendario', 'tab' => 'temporadas'], admin_url('admin.php')));
        echo '<h2>Empezar Curso</h2>';
        if (($_GET['lc_error'] ?? '') === '1') {
            echo '<div class="notice notice-error"><p>Indica un nombre y una fecha de inicio válidos.</p></div>';
        }
        echo '<p>Esto cierra la temporada actual (si hay alguna en curso) y empieza una nueva. '
            . 'Marca qué deportes, actividades y clases siguen activos en la temporada que empieza — lo que desmarques deja de ofrecerse para registrar nuevos resultados en el panel, pero su histórico sigue intacto en la Clasificación.</p>';
        echo '<p><a href="' . $back_url . '">&larr; Cancelar y volver</a></p>';

        echo '<div class="lc-admin-card"><form method="post" action="' . esc_url(admin_url('admin.php')) . '">';
        wp_nonce_field('lc_nueva_temporada');
        echo '<input type="hidden" name="lc_action" value="nueva_temporada" />';
        echo '<input type="hidden" name="page" value="liga-creus-calendario" />';
        echo '<input type="hidden" name="tab" value="temporadas" />';

        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="lc-nueva-temporada-label">Nombre de la temporada</label></th><td>'
            . '<input type="text" id="lc-nueva-temporada-label" name="nueva_temporada_label" placeholder="Ej. 2026/2027" style="width:240px" required /></td></tr>';
        echo '<tr><th><label for="lc-nueva-temporada-inicio">Fecha de inicio</label></th><td>'
            . '<input type="date" id="lc-nueva-temporada-inicio" name="nueva_temporada_inicio" value="' . esc_attr(date('Y-m-d')) . '" required /></td></tr>';
        echo '</tbody></table>';

        echo '<h3>Deportes</h3><div class="lc-checklist">';
        foreach (LC_Competiciones::get_deportes() as $d) {
            $checked = ($d['activo'] ?? true) ? ' checked' : '';
            echo '<label class="lc-checklist-item"><input type="checkbox" name="deportes_activos[]" value="' . esc_attr($d['value']) . '"' . $checked . '> ' . esc_html($d['label']) . '</label>';
        }
        echo '</div>';

        echo '<h3>Actividades</h3><div class="lc-checklist">';
        foreach (LC_Competiciones::get_actividades() as $a) {
            $checked = ($a['activo'] ?? true) ? ' checked' : '';
            echo '<label class="lc-checklist-item"><input type="checkbox" name="actividades_activos[]" value="' . esc_attr($a['value']) . '"' . $checked . '> ' . esc_html($a['label']) . '</label>';
        }
        echo '</div>';

        echo '<h3>Clases</h3><div class="lc-checklist">';
        foreach (LC_Competiciones::get_clases_config() as $c) {
            $checked = ($c['activo'] ?? true) ? ' checked' : '';
            echo '<label class="lc-checklist-item"><input type="checkbox" name="clases_activas[]" value="' . esc_attr($c['nombre']) . '"' . $checked . '> ' . esc_html($c['nombre']) . '</label>';
        }
        echo '</div>';

        echo '<h3>Trimestres del curso nuevo</h3>';
        echo '<p class="description">Los trimestres del curso anterior se quedan tal cual en el histórico. Define aquí los de la temporada que empieza — puedes partir de los mismos nombres, pero con fechas nuevas.</p>';
        $activa = LC_Competiciones::get_temporada_activa();
        $plantilla = [];
        if ($activa) {
            foreach (LC_Competiciones::get_trimestres() as $t) {
                if (($t['temporada'] ?? '') === $activa['label']) {
                    $plantilla[] = ['label' => $t['label'] ?? '', 'inicio' => '', 'fin' => ''];
                }
            }
        }
        if (empty($plantilla)) $plantilla[] = ['label' => '', 'inicio' => '', 'fin' => ''];

        self::render_add_button('lc-wizard-add-trimestre', '+ Añadir trimestre');
        echo '<table class="widefat lc-admin-table" id="lc-wizard-rows-trimestres"><thead><tr>'
            . '<th>Nombre</th><th style="width:160px">Inicio</th><th style="width:160px">Fin</th><th style="width:90px"></th>'
            . '</tr></thead><tbody>';
        foreach ($plantilla as $i => $t) {
            self::render_wizard_trimestre_row($i, $t);
        }
        echo '</tbody></table>';
        echo '<template id="lc-wizard-template-trimestre">';
        self::render_wizard_trimestre_row('__INDEX__', ['label' => '', 'inicio' => '', 'fin' => '']);
        echo '</template>';

        submit_button('Empezar Curso');
        echo '</form></div>';
    }

    // Fila del mini-editor de Trimestres dentro del asistente. A diferencia de la
    // tabla de la pestaña Trimestres, aquí no hay selector de Temporada: todas las
    // filas son, implícitamente, de la temporada que se está creando.
    private static function render_wizard_trimestre_row($i, $t) {
        echo '<tr class="lc-admin-row">';
        echo '<td><input type="text" name="nuevos_trimestres[' . $i . '][label]" value="' . esc_attr($t['label'] ?? '') . '" placeholder="Ej. 1er Trimestre" style="width:100%" /></td>';
        echo '<td><input type="date" name="nuevos_trimestres[' . $i . '][inicio]" value="' . esc_attr($t['inicio'] ?? '') . '" /></td>';
        echo '<td><input type="date" name="nuevos_trimestres[' . $i . '][fin]" value="' . esc_attr($t['fin'] ?? '') . '" /></td>';
        echo '<td><button type="button" class="button lc-admin-remove-row">Eliminar</button></td>';
        echo '</tr>';
    }

    // Maneja el envío del asistente. No usa register_setting()/options.php (la
    // trampa de doble invocación de sanitize_callback no aplica aquí) — es un
    // admin_init normal que actualiza varias opciones y redirige (POST-redirect-GET).
    public static function maybe_handle_nueva_temporada() {
        if (($_POST['lc_action'] ?? '') !== 'nueva_temporada') return;
        if (!current_user_can('manage_options')) return;
        check_admin_referer('lc_nueva_temporada');

        $label = sanitize_text_field($_POST['nueva_temporada_label'] ?? '');
        $inicio = sanitize_text_field($_POST['nueva_temporada_inicio'] ?? '');
        $back = admin_url('admin.php?page=liga-creus-calendario&tab=temporadas');

        if ($label === '' || !self::is_valid_date($inicio)) {
            wp_safe_redirect(add_query_arg(['accion' => 'nueva', 'lc_error' => '1'], $back));
            exit;
        }

        // 1) Cerrar la temporada abierta (si hay alguna) el día antes de que empiece la nueva.
        $temporadas = LC_Competiciones::get_temporadas();
        foreach ($temporadas as &$t) {
            if (empty($t['fin'])) {
                $t['fin'] = date('Y-m-d', strtotime($inicio . ' -1 day'));
            }
        }
        unset($t);
        $temporadas[] = ['label' => $label, 'inicio' => $inicio, 'fin' => ''];
        update_option(LC_Competiciones::OPT_TEMPORADAS, array_values($temporadas));

        // 2) Aplicar el checklist de qué deportes/actividades/clases siguen activos.
        $deportes_activos = array_map('sanitize_text_field', (array) ($_POST['deportes_activos'] ?? []));
        $deportes = LC_Competiciones::get_deportes();
        foreach ($deportes as &$d) { $d['activo'] = in_array($d['value'], $deportes_activos, true); }
        unset($d);
        update_option(LC_Competiciones::OPT_DEPORTES, $deportes);

        $actividades_activas = array_map('sanitize_text_field', (array) ($_POST['actividades_activos'] ?? []));
        $actividades = LC_Competiciones::get_actividades();
        foreach ($actividades as &$a) { $a['activo'] = in_array($a['value'], $actividades_activas, true); }
        unset($a);
        update_option(LC_Competiciones::OPT_ACTIVIDADES, $actividades);

        $clases_activas = array_map('sanitize_text_field', (array) ($_POST['clases_activas'] ?? []));
        $clases = LC_Competiciones::get_clases_config();
        foreach ($clases as &$c) { $c['activo'] = in_array($c['nombre'], $clases_activas, true); }
        unset($c);
        update_option(LC_Competiciones::OPT_CLASES, $clases);

        // 3) Añadir los trimestres del curso nuevo, etiquetados con su temporada.
        // Los del curso anterior no se tocan — quedan intactos en el histórico.
        $trimestres = LC_Competiciones::get_trimestres();
        $seen = [];
        foreach ($trimestres as $t) { $seen[($t['temporada'] ?? '') . '|' . ($t['label'] ?? '')] = true; }
        foreach ((array) ($_POST['nuevos_trimestres'] ?? []) as $row) {
            $t_label = sanitize_text_field($row['label'] ?? '');
            $t_inicio = sanitize_text_field($row['inicio'] ?? '');
            $t_fin = sanitize_text_field($row['fin'] ?? '');
            if ($t_label === '' || !self::is_valid_date($t_inicio) || !self::is_valid_date($t_fin)) continue;
            if (strtotime($t_inicio) > strtotime($t_fin)) continue;
            $key = $label . '|' . $t_label;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $trimestres[] = ['label' => $t_label, 'inicio' => $t_inicio, 'fin' => $t_fin, 'temporada' => $label];
        }
        update_option(LC_Competiciones::OPT_TRIMESTRES, array_values($trimestres));

        wp_safe_redirect(add_query_arg(['lc_nueva_temporada' => '1'], $back));
        exit;
    }

    // Etiqueta encima del control, en vez de "Etiqueta: control" en línea —
    // así varios campos seguidos (Victoria/Empate/Derrota...) envuelven línea
    // como bloques completos en vez de partir una etiqueta de su input.
    private static function param_field($label, $input_html) {
        return '<span class="lc-param-field"><label>' . esc_html($label) . '</label>' . $input_html . '</span>';
    }

    private static function render_enfrentamiento_params($name, $i, $item) {
        echo '<span class="lc-admin-param lc-admin-param-enfrentamiento lc-param-group">';
        echo self::param_field('Victoria', '<input type="number" name="' . esc_attr($name) . '[' . $i . '][pts_victoria]" value="' . esc_attr($item['pts_victoria'] ?? 3) . '" />');
        echo self::param_field('Empate', '<input type="number" name="' . esc_attr($name) . '[' . $i . '][pts_empate]" value="' . esc_attr($item['pts_empate'] ?? 1) . '" />');
        echo self::param_field('Derrota', '<input type="number" name="' . esc_attr($name) . '[' . $i . '][pts_derrota]" value="' . esc_attr($item['pts_derrota'] ?? 0) . '" />');
        echo '</span>';
    }

    // =========================================================
    // Sanitización
    // =========================================================

    // El Settings API de WordPress puede invocar un sanitize_callback más de una
    // vez por guardado (pasa el resultado de la primera pasada como entrada de la
    // segunda) — por eso estas funciones deben ser idempotentes: sanitizar dos
    // veces el mismo dato ya sanitizado tiene que dar el mismo resultado.
    private static function sanitize_checkbox_bool($val) {
        return !in_array($val, [false, '0', 0, '', null], true);
    }

    public static function sanitize_deportes($raw) {
        if (!is_array($raw)) return LC_Competiciones::DEFAULT_DEPORTES;
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            $value = sanitize_title($row['value'] ?? '');
            $label = sanitize_text_field($row['label'] ?? '');
            if ($value === '' || $label === '' || isset($seen[$value])) continue;
            $seen[$value] = true;

            $modo = in_array($row['modo'] ?? '', ['individual', 'alumnos'], true) ? $row['modo'] : 'enfrentamiento';
            $activo = isset($row['activo']) ? self::sanitize_checkbox_bool($row['activo']) : false;
            $item = ['value' => $value, 'label' => $label, 'modo' => $modo, 'activo' => $activo];

            if ($modo === 'individual') {
                $item['orden'] = ($row['orden'] ?? '') === 'desc' ? 'desc' : 'asc';
                $puntos_raw = $row['puntos_por_posicion'] ?? '';
                $puntos = is_array($puntos_raw)
                    ? array_map('intval', $puntos_raw)
                    : array_map('intval', array_filter(array_map('trim', explode(',', (string)$puntos_raw)), 'strlen'));
                $item['puntos_por_posicion'] = !empty($puntos) ? $puntos : [5, 3, 2, 1];
            } else {
                $item['pts_victoria'] = max(0, intval($row['pts_victoria'] ?? 3));
                $item['pts_empate']   = max(0, intval($row['pts_empate'] ?? 1));
                $item['pts_derrota']  = max(0, intval($row['pts_derrota'] ?? 0));
            }

            $out[] = $item;
        }
        return !empty($out) ? array_values($out) : LC_Competiciones::DEFAULT_DEPORTES;
    }

    public static function sanitize_actividades($raw) {
        if (!is_array($raw)) return LC_Competiciones::DEFAULT_ACTIVIDADES;
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            $value = sanitize_title($row['value'] ?? '');
            $label = sanitize_text_field($row['label'] ?? '');
            if ($value === '' || $label === '' || isset($seen[$value])) continue;
            $seen[$value] = true;

            $modo = in_array($row['modo'] ?? '', ['enfrentamiento', 'alumnos'], true) ? $row['modo'] : 'participantes';
            $activo = isset($row['activo']) ? self::sanitize_checkbox_bool($row['activo']) : false;
            $item = ['value' => $value, 'label' => $label, 'modo' => $modo, 'activo' => $activo];

            if ($modo === 'enfrentamiento' || $modo === 'alumnos') {
                $item['pts_victoria'] = max(0, intval($row['pts_victoria'] ?? 3));
                $item['pts_empate']   = max(0, intval($row['pts_empate'] ?? 1));
                $item['pts_derrota']  = max(0, intval($row['pts_derrota'] ?? 0));
            } else {
                $niveles = [];
                if (is_array($row['niveles'] ?? null)) {
                    // Segunda pasada de sanitización: "niveles" ya viene estructurado.
                    foreach ($row['niveles'] as $n) {
                        $lbl = sanitize_text_field($n['label'] ?? '');
                        if ($lbl === '') continue;
                        $niveles[] = ['label' => $lbl, 'puntos' => intval($n['puntos'] ?? 0)];
                    }
                } else {
                    $raw_niveles = (string)($row['niveles_raw'] ?? '');
                    foreach (preg_split('/\r?\n/', $raw_niveles) as $line) {
                        $line = trim($line);
                        if ($line === '' || strpos($line, '=') === false) continue;
                        $parts = array_map('trim', explode('=', $line, 2));
                        if ($parts[0] === '') continue;
                        $niveles[] = ['label' => sanitize_text_field($parts[0]), 'puntos' => intval($parts[1] ?? 0)];
                    }
                }
                $item['niveles'] = !empty($niveles) ? $niveles : [
                    ['label' => 'Excelente', 'puntos' => 3], ['label' => 'Regular', 'puntos' => 1], ['label' => 'Insuficiente', 'puntos' => 0],
                ];
            }

            $out[] = $item;
        }
        return !empty($out) ? array_values($out) : LC_Competiciones::DEFAULT_ACTIVIDADES;
    }

    // Solo se aceptan las claves conocidas y siempre como entero (con signo).
    // Una clave ausente conserva su valor por defecto en vez de quedar en 0.
    private static function sanitize_puntos($raw, $defaults) {
        if (!is_array($raw)) return $defaults;
        $out = [];
        foreach ($defaults as $clave => $def) {
            $out[$clave] = array_key_exists($clave, $raw) ? intval($raw[$clave]) : $def;
        }
        return $out;
    }

    public static function sanitize_semaforo($raw) {
        return self::sanitize_puntos($raw, LC_Competiciones::DEFAULT_SEMAFORO);
    }

    public static function sanitize_partes($raw) {
        return self::sanitize_puntos($raw, LC_Competiciones::DEFAULT_PARTES);
    }

    public static function sanitize_clases($raw) {
        if (!is_array($raw)) return LC_Competiciones::DEFAULT_CLASES;
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            // Compatibilidad: filas planas tipo string (formato anterior a "Activo").
            $nombre = sanitize_text_field(is_array($row) ? ($row['nombre'] ?? '') : $row);
            if ($nombre === '' || isset($seen[$nombre])) continue;
            $seen[$nombre] = true;
            $activo = (is_array($row) && isset($row['activo'])) ? self::sanitize_checkbox_bool($row['activo']) : false;
            $escudo = max(0, intval(is_array($row) ? ($row['escudo'] ?? 0) : 0));
            $out[] = ['nombre' => $nombre, 'activo' => $activo, 'escudo' => $escudo];
        }
        return !empty($out) ? array_values($out) : LC_Competiciones::DEFAULT_CLASES;
    }

    private static function is_valid_date($s) {
        if (!is_string($s) || $s === '') return false;
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d && $d->format('Y-m-d') === $s;
    }

    public static function sanitize_trimestres($raw) {
        if (!is_array($raw)) return LC_Competiciones::DEFAULT_TRIMESTRES;
        $out = [];
        $seen = [];
        foreach ($raw as $row) {
            $label = sanitize_text_field($row['label'] ?? '');
            $inicio = sanitize_text_field($row['inicio'] ?? '');
            $fin = sanitize_text_field($row['fin'] ?? '');
            $temporada = sanitize_text_field($row['temporada'] ?? '');
            if ($label === '' || !self::is_valid_date($inicio) || !self::is_valid_date($fin)) continue;
            if (strtotime($inicio) > strtotime($fin)) continue;
            // Dos trimestres pueden compartir nombre si son de temporadas distintas
            // (ej. "1er Trimestre" en 2025/2026 y otra vez en 2026/2027) — lo que no
            // se permite es repetir el mismo (temporada, nombre).
            $key = $temporada . '|' . $label;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['label' => $label, 'inicio' => $inicio, 'fin' => $fin, 'temporada' => $temporada];
        }
        return array_values($out);
    }

    public static function sanitize_temporadas($raw) {
        if (!is_array($raw)) return LC_Competiciones::DEFAULT_TEMPORADAS;
        $out = [];
        foreach ($raw as $row) {
            $label = sanitize_text_field($row['label'] ?? '');
            $inicio = sanitize_text_field($row['inicio'] ?? '');
            $fin = sanitize_text_field($row['fin'] ?? '');
            if ($label === '' || !self::is_valid_date($inicio)) continue;
            if ($fin !== '' && !self::is_valid_date($fin)) continue;
            if ($fin !== '' && strtotime($inicio) > strtotime($fin)) continue;
            $out[] = ['label' => $label, 'inicio' => $inicio, 'fin' => $fin];
        }
        return array_values($out);
    }

}
