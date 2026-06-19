<?php
/**
 * Script para desactivar el plugin anterior y evitar warnings deprecated
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de tu WordPress
 * 2. Ejecuta: https://tudominio.com/disable-old-plugin.php
 * 3. Elimina este archivo después de ejecutarlo
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

// Verificar permisos de administrador
if (!current_user_can('activate_plugins')) {
    die('No tienes permisos para desactivar plugins');
}

echo "<h2>Desactivando Plugin Anterior</h2>";

// Lista de plugins a desactivar
$plugins_to_deactivate = [
    'Plugin-Liga-Creus/liga_creus_plugin_v_0_3.php',
    'liga_creus_plugin_v_0_3.php'
];

$deactivated = [];

foreach ($plugins_to_deactivate as $plugin) {
    if (is_plugin_active($plugin)) {
        deactivate_plugins($plugin);
        $deactivated[] = $plugin;
        echo "<p>✅ Plugin desactivado: $plugin</p>";
    } else {
        echo "<p>ℹ️ Plugin no activo: $plugin</p>";
    }
}

if (empty($deactivated)) {
    echo "<p>✅ No se encontraron plugins anteriores activos</p>";
} else {
    echo "<p>✅ Se desactivaron " . count($deactivated) . " plugins anteriores</p>";
}

// Verificar que el plugin actual esté activo
if (is_plugin_active('Plugin-Liga-Creus/liga-creus.php')) {
    echo "<p>✅ Plugin actual (liga-creus.php) está activo</p>";
} else {
    echo "<p>⚠️ Plugin actual (liga-creus.php) no está activo</p>";
}

echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de ejecutarlo por seguridad.</p>";
?>
