<?php
if (!defined('ABSPATH')) { exit; }

class LC_Assets {
    
    public static function init(){
        add_shortcode('lc_panel', [__CLASS__, 'shortcode_panel']);
    }

    // Se encola desde el propio shortcode (igual que LC_Shortcode_Clasificacion) en
    // vez de engancharse a wp_enqueue_scripts y buscar "[lc_panel]" dentro de
    // post_content: esa comprobación dejaba el panel sin CSS ni JS cuando el
    // shortcode no vive en el contenido de una entrada singular -- un widget, una
    // plantilla FSE o cualquier contenido reutilizable. Los shortcodes se procesan
    // al renderizar el contenido, antes de wp_footer, así que los scripts (todos
    // con $in_footer = true) siguen llegando a la página.
    public static function enqueue(){
        // CSS
        wp_enqueue_style('lc-tokens', LC_URL . 'assets/css/tokens.css', [], LC_VERSION);
        wp_enqueue_style('lc-app', LC_URL . 'assets/css/app.css', ['lc-tokens'], LC_VERSION);

        // JS - Cargar en orden de dependencias
        wp_enqueue_script('lc-helpers', LC_URL . 'assets/js/helpers.js', [], LC_VERSION, true);
        wp_enqueue_script('lc-ui-semaforo', LC_URL . 'assets/js/ui-semaforo.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-deportes', LC_URL . 'assets/js/ui-deportes.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-partes', LC_URL . 'assets/js/ui-partes.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-biblioteca', LC_URL . 'assets/js/ui-biblioteca.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-extraordinarias', LC_URL . 'assets/js/ui-extraordinarias.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-ui-tiempo-aprendizaje', LC_URL . 'assets/js/ui-tiempo-aprendizaje.js', ['lc-helpers'], LC_VERSION, true);
        wp_enqueue_script('lc-app', LC_URL . 'assets/js/app.js', ['lc-helpers', 'lc-ui-semaforo', 'lc-ui-deportes', 'lc-ui-partes', 'lc-ui-biblioteca', 'lc-ui-extraordinarias', 'lc-ui-tiempo-aprendizaje'], LC_VERSION, true);

        // Ajustes para el JS
        $cfg = [
            'endpoint'    => esc_url_raw( rest_url('liga-creus/v1') ),
            'nonce'       => wp_create_nonce('wp_rest'),
            'clases'      => LC_Competiciones::get_clases_activas(),
            'deportes'    => LC_Competiciones::get_deportes_activos(),
            'actividades' => LC_Competiciones::get_actividades_activas(),
            // Marca el rango de semanas/meses que ofrece el panel. Sin esto el
            // selector usa un curso octubre->junio fijo, que en septiembre aún
            // apunta al curso ANTERIOR: los registros se guardaban con el año
            // equivocado y luego no aparecían al filtrar por trimestre.
            'temporada'   => LC_Competiciones::get_temporada_vigente(),
            // Para etiquetar cada semana del selector con el trimestre al que
            // pertenece: sin esto no hay forma de saber en cuál estás registrando.
            'trimestres'  => LC_Competiciones::get_trimestres(),
            // Puntuación configurable: el panel la usa para los textos de ayuda
            // ("+1 punto cada uno") y para el total que calcula en vivo, que debe
            // dar lo mismo que el servidor al guardar.
            'semaforo'    => LC_Competiciones::get_semaforo(),
            'partes'      => LC_Competiciones::get_partes(),
        ];
        wp_add_inline_script('lc-app', 'window.LC_SETTINGS = '.wp_json_encode($cfg).';', 'before');
    }

    public static function shortcode_panel(){
        self::enqueue();
        return '<div data-lc-panel></div>';
    }
}
