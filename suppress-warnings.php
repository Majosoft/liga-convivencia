<?php
/**
 * Configuración para suprimir warnings deprecated de otros plugins
 * 
 * Este archivo debe incluirse en wp-config.php para reducir el ruido en los logs
 */

// Solo suprimir warnings deprecated, no errores críticos
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

// Suprimir warnings deprecated específicos
add_action('init', function() {
    // Suprimir warnings de ACF (Advanced Custom Fields)
    if (class_exists('ACF')) {
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    }
    
    // Suprimir warnings de otros plugins conocidos
    $suppress_plugins = [
        'advanced-custom-fields',
        'asgaros-forum',
        'woosidebars',
        'firmasite-theme-enhancer',
        'really-simple-ssl'
    ];
    
    foreach ($suppress_plugins as $plugin) {
        if (is_plugin_active($plugin . '/' . $plugin . '.php')) {
            // Este plugin está activo, podríamos suprimir sus warnings específicos
            // Pero es mejor actualizar los plugins
        }
    }
});

/**
 * ALTERNATIVA: Agregar esto a wp-config.php
 * 
 * // Suprimir warnings deprecated (NO recomendado para producción)
 * error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
 * 
 * // O mejor, solo para desarrollo
 * if (WP_DEBUG) {
 *     error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
 * }
 */
?>
