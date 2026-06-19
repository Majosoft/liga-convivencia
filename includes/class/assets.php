<?php
if (!defined('ABSPATH')) { exit; }

class LC_Assets {
    
    public static function init(){
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 100);
        add_shortcode('lc_panel', [__CLASS__, 'shortcode_panel']);
    }

    public static function enqueue(){
        // Solo si la página tiene el shortcode
        if (!is_singular()) return;
        global $post;
        if (!$post || strpos((string)$post->post_content, '[lc_panel]') === false) return;

        // CSS
        wp_enqueue_style('lc-app', LC_URL . 'assets/css/app.css', [], LC_VERSION);

        // JS - Cargar en orden de dependencias
        wp_enqueue_script('lc-helpers', LC_URL . 'assets/js/helpers.js', [], LC_VERSION, true);
        wp_enqueue_script('lc-ui-semaforo', LC_URL . 'assets/js/ui-semaforo.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-deportes', LC_URL . 'assets/js/ui-deportes.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-partes', LC_URL . 'assets/js/ui-partes.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-biblioteca', LC_URL . 'assets/js/ui-biblioteca.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-app', LC_URL . 'assets/js/app.js', ['lc-helpers', 'lc-ui-semaforo', 'lc-ui-deportes', 'lc-ui-partes', 'lc-ui-biblioteca'], LC_VERSION, true);

        // Ajustes para el JS
        $cfg = [
            'endpoint' => esc_url_raw( rest_url('liga-creus/v1') ),
            'nonce'    => wp_create_nonce('wp_rest'),
        ];
        wp_add_inline_script('lc-app', 'window.LC_SETTINGS = '.wp_json_encode($cfg).';', 'before');
    }

    public static function shortcode_panel(){
        return '<div data-lc-panel></div>';
    }
}
