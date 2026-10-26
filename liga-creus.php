<?php
/**
 * Plugin Name: Liga Convivencia
 * Description: Login por email de usuarios autorizados + registro de competiciones (Semáforo, Deportes, Partes, Biblioteca, Extraordinarias, Tiempo de Aprendizaje) y clasificación pública.
 * Version: 1.0.0
 * Author: Majosoft
 */

if (!defined('ABSPATH')) { exit; }

// Definir constantes del plugin
define('LC_PATH', plugin_dir_path(__FILE__));
define('LC_URL', plugin_dir_url(__FILE__));
define('LC_VERSION', '1.0.0');

// Cargar archivos necesarios
require_once LC_PATH . 'includes/helpers.php';
require_once LC_PATH . 'includes/class/activator.php';
require_once LC_PATH . 'includes/class/auth.php';
require_once LC_PATH . 'includes/class/competiciones.php';
require_once LC_PATH . 'includes/class/alumnos.php';
require_once LC_PATH . 'includes/class/horario.php';
require_once LC_PATH . 'includes/class/admin.php';
require_once LC_PATH . 'includes/class/admin-profesorado.php';
require_once LC_PATH . 'includes/class/assets.php';
require_once LC_PATH . 'includes/class/rest-auth.php';
require_once LC_PATH . 'includes/class/rest-alumnos.php';
require_once LC_PATH . 'includes/class/rest-semaforo.php';
require_once LC_PATH . 'includes/class/rest-gomets.php';
require_once LC_PATH . 'includes/class/rest-deportes.php';
require_once LC_PATH . 'includes/class/rest-partes.php';
require_once LC_PATH . 'includes/class/rest-biblioteca.php';
require_once LC_PATH . 'includes/class/rest-extraordinarias.php';
require_once LC_PATH . 'includes/class/rest-tiempo-aprendizaje.php';
require_once LC_PATH . 'includes/class/rest-clasificacion.php';
require_once LC_PATH . 'includes/class/shortcode-clasificacion.php';
require_once LC_PATH . 'includes/class/app.php';

// Hook de activación
register_activation_hook(__FILE__, ['LC_Activator', 'activate']);

// Aplica cambios de esquema (ej. nuevas columnas) sin tener que reactivar el plugin a mano
add_action('init', function() {
    if (get_option('lc_db_version') !== LC_VERSION) {
        LC_Activator::activate();
        update_option('lc_db_version', LC_VERSION);
    }
}, 5);

// Inicializar el plugin
add_action('init', function() {
    // Inicializar autenticación
    LC_Auth::init();
    
    // Inicializar admin
    LC_Admin::init();
    LC_Admin_Profesorado::init();
    
    // Inicializar assets
    LC_Assets::init();

    // App instalable del profesorado (https://sitio/<slug>/)
    LC_App::init();

    // Registrar shortcodes
    LC_Shortcode_Clasificacion::register();

    // El Semáforo guarda un acumulado, así que cambiar los puntos de los gomets
    // obliga a rehacer los totales ya guardados o dejarían de cuadrar con los
    // gomets registrados. update_option_X solo dispara si el valor cambia.
    add_action('update_option_' . LC_Competiciones::OPT_SEMAFORO, ['LC_REST_Semaforo', 'recalcular_totales']);
});

// Registrar rutas REST
add_action('rest_api_init', function() {
    LC_REST_Auth::register();
    LC_REST_Alumnos::register();
    LC_REST_Semaforo::register();
    LC_REST_Gomets::register();
    LC_REST_Deportes::register();
    LC_REST_Partes::register();
    LC_REST_Biblioteca::register();
    LC_REST_Extraordinarias::register();
    LC_REST_TiempoAprendizaje::register();
    LC_REST_Clasificacion::register();
});