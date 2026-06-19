<?php
if (!defined('ABSPATH')) { exit; }

class LC_Admin {
    
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    /**
     * Añade la página de configuración en el menú de WordPress
     */
    public static function add_settings_page() {
        add_options_page('Liga Creus', 'Liga Creus', 'manage_options', 'liga-creus', [__CLASS__, 'render_settings_page']);
    }

    /**
     * Registra las opciones de configuración
     */
    public static function register_settings() {
        register_setting('lc_settings', LC_Auth::OPT_KEY, [
            'type' => 'string',
            'sanitize_callback' => function($raw){
                $lines = array_filter(array_map('trim', preg_split('/\r?\n/', (string)$raw)));
                $clean = array_map(function($e){ return sanitize_email($e); }, $lines);
                $clean = array_filter($clean);
                return implode("\n", array_unique($clean));
            },
            'default' => ''
        ]);
        
        add_settings_section('lc_section', 'Emails autorizados', function(){
            echo '<p>Introduce un email por línea. Solo estos correos podrán iniciar sesión en la página de resultados.</p>';
        }, 'lc_settings');
        
        add_settings_field('lc_emails', 'Lista de emails', function(){
            $val = esc_textarea(get_option(LC_Auth::OPT_KEY, ''));
            echo '<textarea style="width:100%;height:220px" name="'.esc_attr(LC_Auth::OPT_KEY).'">'.$val.'</textarea>';
        }, 'lc_settings', 'lc_section');
    }

    /**
     * Renderiza la página de configuración
     */
    public static function render_settings_page() {
        echo '<div class="wrap"><h1>Liga Creus</h1><form method="post" action="options.php">';
        settings_fields('lc_settings');
        do_settings_sections('lc_settings');
        submit_button('Guardar cambios');
        echo '</form></div>';
    }
}
