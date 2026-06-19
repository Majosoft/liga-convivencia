<?php
/**
 * Plugin Name: Liga Creus
 * Description: Login por email autorizado + selector de competición (Semáforo, Deportes, Partes, Biblioteca).
 * Version: 0.2.0
 * Author: Jose Borrás
 */

if (!defined('ABSPATH')) { exit; }

// Definir constantes del plugin
define('LC_PATH', plugin_dir_path(__FILE__));
define('LC_URL', plugin_dir_url(__FILE__));
define('LC_VERSION', '0.2.0');

// Cargar archivos necesarios
require_once LC_PATH . 'includes/helpers.php';
require_once LC_PATH . 'includes/class/activator.php';
require_once LC_PATH . 'includes/class/auth.php';
require_once LC_PATH . 'includes/class/admin.php';
require_once LC_PATH . 'includes/class/assets.php';
require_once LC_PATH . 'includes/class/rest-auth.php';
require_once LC_PATH . 'includes/class/rest-semaforo.php';
require_once LC_PATH . 'includes/class/rest-deportes.php';
require_once LC_PATH . 'includes/class/rest-partes.php';
require_once LC_PATH . 'includes/class/rest-biblioteca.php';
require_once LC_PATH . 'includes/class/rest-clasificacion.php';
require_once LC_PATH . 'includes/class/shortcode-clasificacion.php';

// Hook de activación
register_activation_hook(__FILE__, ['LC_Activator', 'activate']);

// Inicializar el plugin
add_action('init', function() {
    // Inicializar autenticación
    LC_Auth::init();
    
    // Inicializar admin
    LC_Admin::init();
    
    // Inicializar assets
    LC_Assets::init();
    
    // Registrar shortcodes
    LC_Shortcode_Clasificacion::register();
});

// Registrar rutas REST
add_action('rest_api_init', function() {
    LC_REST_Auth::register();
    LC_REST_Semaforo::register();
    LC_REST_Deportes::register();
    LC_REST_Partes::register();
    LC_REST_Biblioteca::register();
    LC_REST_Clasificacion::register();
});