<?php
/**
 * Script de diagnóstico para el plugin Liga Creus
 * Acceder desde: /wp-content/plugins/Plugin-Liga-Creus/debug.php
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    // Cargar WordPress
    require_once('../../../wp-load.php');
}

echo "<h1>Diagnóstico Plugin Liga Creus</h1>";

// 1. Verificar que el plugin está activo
echo "<h2>1. Estado del Plugin</h2>";
if (function_exists('get_plugins')) {
    $plugins = get_plugins();
    $plugin_file = 'Plugin-Liga-Creus/liga-creus.php';
    if (isset($plugins[$plugin_file])) {
        echo "✅ Plugin encontrado: " . $plugins[$plugin_file]['Name'] . " v" . $plugins[$plugin_file]['Version'];
    } else {
        echo "❌ Plugin no encontrado";
    }
} else {
    echo "❌ No se puede verificar el estado del plugin";
}

// 2. Verificar tablas de la base de datos
echo "<h2>2. Tablas de Base de Datos</h2>";
global $wpdb;
$tables = [
    $wpdb->prefix . 'lc_semaforo',
    $wpdb->prefix . 'lc_deportes', 
    $wpdb->prefix . 'lc_partes'
];

foreach ($tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        echo "✅ $table existe ($count registros)<br>";
    } else {
        echo "❌ $table NO existe<br>";
    }
}

// 3. Verificar endpoints REST
echo "<h2>3. Endpoints REST</h2>";
$rest_routes = rest_get_server()->get_routes();
$lc_routes = array_filter($rest_routes, function($key) {
    return strpos($key, 'liga-creus') !== false;
}, ARRAY_FILTER_USE_KEY);

if (!empty($lc_routes)) {
    echo "✅ Endpoints encontrados:<br>";
    foreach ($lc_routes as $route => $handlers) {
        echo "- $route<br>";
    }
} else {
    echo "❌ No se encontraron endpoints de Liga Creus";
}

// 4. Verificar configuración
echo "<h2>4. Configuración</h2>";
$allowed_emails = get_option('lc_allowed_emails', '');
echo "Emails autorizados: " . (empty($allowed_emails) ? "❌ Ninguno configurado" : "✅ Configurados") . "<br>";

// 5. Test de conexión a la API
echo "<h2>5. Test de API</h2>";
$test_url = rest_url('liga-creus/v1/auth/me');
echo "URL de test: $test_url<br>";

// 6. Verificar permisos
echo "<h2>6. Permisos</h2>";
echo "Usuario actual: " . wp_get_current_user()->user_email . "<br>";
echo "¿Es admin?: " . (current_user_can('manage_options') ? "✅ Sí" : "❌ No") . "<br>";

echo "<hr>";
echo "<p><strong>Nota:</strong> Elimina este archivo después del diagnóstico por seguridad.</p>";
?>
