<?php
/**
 * Debug para verificar el shortcode
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de WordPress
 * 2. Ejecuta: https://tudominio.com/debug-shortcode.php
 * 3. Elimina este archivo después de ejecutarlo
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

echo "<h2>🔍 Debug del Shortcode Liga Creus</h2>";

// Verificar si el plugin está activo
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

$plugin_file = 'Plugin-Liga-Creus/liga-creus.php';
$is_active = is_plugin_active($plugin_file);

echo "<p><strong>Plugin activo:</strong> " . ($is_active ? "✅ Sí" : "❌ No") . "</p>";

if (!$is_active) {
    echo "<p>⚠️ El plugin no está activo. Actívalo desde WordPress Admin > Plugins</p>";
    exit;
}

// Verificar si la clase existe
if (class_exists('LC_Shortcode_Clasificacion')) {
    echo "<p><strong>Clase LC_Shortcode_Clasificacion:</strong> ✅ Existe</p>";
} else {
    echo "<p><strong>Clase LC_Shortcode_Clasificacion:</strong> ❌ No existe</p>";
}

// Verificar si el shortcode está registrado
global $shortcode_tags;
$shortcode_registered = isset($shortcode_tags['liga_creus_clasificacion']);

echo "<p><strong>Shortcode registrado:</strong> " . ($shortcode_registered ? "✅ Sí" : "❌ No") . "</p>";

if ($shortcode_registered) {
    echo "<p><strong>Callback del shortcode:</strong> " . print_r($shortcode_tags['liga_creus_clasificacion'], true) . "</p>";
}

// Verificar archivos necesarios
$required_files = [
    'includes/class/shortcode-clasificacion.php',
    'includes/class/rest-clasificacion.php',
    'assets/css/clasificacion.css',
    'assets/js/clasificacion.js'
];

echo "<h3>📁 Archivos Requeridos:</h3>";
foreach ($required_files as $file) {
    $full_path = WP_PLUGIN_DIR . '/Plugin-Liga-Creus/' . $file;
    $exists = file_exists($full_path);
    echo "<p><strong>$file:</strong> " . ($exists ? "✅ Existe" : "❌ No existe") . "</p>";
    if (!$exists) {
        echo "<p style='color: red;'>Ruta completa: $full_path</p>";
    }
}

// Verificar endpoint REST
echo "<h3>🌐 Endpoint REST:</h3>";
$rest_url = rest_url('liga-creus/v1/clasificacion');
echo "<p><strong>URL:</strong> <a href='$rest_url' target='_blank'>$rest_url</a></p>";

// Probar el shortcode directamente
echo "<h3>🧪 Prueba del Shortcode:</h3>";
echo "<p>Ejecutando shortcode directamente:</p>";
echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
$shortcode_output = do_shortcode('[liga_creus_clasificacion]');
echo $shortcode_output;
echo "</div>";

// Verificar errores de PHP
echo "<h3>⚠️ Errores PHP:</h3>";
$errors = error_get_last();
if ($errors) {
    echo "<p style='color: red;'>" . print_r($errors, true) . "</p>";
} else {
    echo "<p>✅ No hay errores PHP recientes</p>";
}

echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de ejecutarlo por seguridad.</p>";
?>
