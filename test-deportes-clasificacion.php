<?php
/**
 * Test para verificar la clasificación de deportes
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de WordPress
 * 2. Ejecuta: https://tudominio.com/test-deportes-clasificacion.php
 * 3. Elimina este archivo después de ejecutarlo
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

echo "<h2>⚽ Test de Clasificación de Deportes</h2>";

// Verificar que el plugin esté activo
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

$plugin_file = 'Plugin-Liga-Creus/liga-creus.php';
$is_active = is_plugin_active($plugin_file);

if (!$is_active) {
    echo "<p style='color: red;'>❌ El plugin no está activo</p>";
    exit;
}

echo "<p>✅ Plugin activo</p>";

// Probar el endpoint de clasificación
echo "<h3>🌐 Probando Endpoint de Clasificación:</h3>";

$endpoint_url = rest_url('liga-creus/v1/clasificacion');
echo "<p><strong>URL:</strong> <a href='$endpoint_url' target='_blank'>$endpoint_url</a></p>";

// Hacer petición al endpoint
$response = wp_remote_get($endpoint_url, [
    'timeout' => 30,
    'headers' => [
        'X-WP-Nonce' => wp_create_nonce('wp_rest')
    ]
]);

if (is_wp_error($response)) {
    echo "<p style='color: red;'>❌ Error en la petición: " . $response->get_error_message() . "</p>";
} else {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if ($data && isset($data['clasificacion'])) {
        echo "<p>✅ Endpoint funcionando</p>";
        
        echo "<h3>📊 Datos de Clasificación:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Clase</th><th>Semáforo</th><th>Deportes</th><th>Partes</th><th>Biblioteca</th><th>Total</th></tr>";
        
        foreach ($data['clasificacion'] as $clase) {
            $deportes = $clase['deportes'];
            $es_decimal = is_float($deportes) && $deportes != intval($deportes);
            $color = $es_decimal ? 'red' : 'green';
            
            echo "<tr>";
            echo "<td>{$clase['clase']}</td>";
            echo "<td>{$clase['semaforo']}</td>";
            echo "<td style='color: $color; font-weight: bold;'>$deportes " . ($es_decimal ? "❌ DECIMAL" : "✅") . "</td>";
            echo "<td>{$clase['partes']}</td>";
            echo "<td>{$clase['biblioteca']}</td>";
            echo "<td>{$clase['total']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar si hay decimales
        $tiene_decimales = false;
        foreach ($data['clasificacion'] as $clase) {
            if (is_float($clase['deportes']) && $clase['deportes'] != intval($clase['deportes'])) {
                $tiene_decimales = true;
                break;
            }
        }
        
        if ($tiene_decimales) {
            echo "<p style='color: red; font-weight: bold;'>❌ PROBLEMA: Se detectaron decimales en deportes</p>";
        } else {
            echo "<p style='color: green; font-weight: bold;'>✅ CORRECTO: No hay decimales en deportes</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Error en la respuesta del endpoint</p>";
        echo "<pre>" . print_r($data, true) . "</pre>";
    }
}

echo "<h3>📝 Sistema de Puntos en Deportes:</h3>";
echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>🏆 Sistema 3-1-0:</strong><br>";
echo "• <strong>Victoria:</strong> 3 puntos<br>";
echo "• <strong>Empate:</strong> 1 punto<br>";
echo "• <strong>Derrota:</strong> 0 puntos<br><br>";
echo "<strong>📊 Ejemplo:</strong><br>";
echo "• 1ºA vs 1ºB: 2-1 → 1ºA: 3 pts, 1ºB: 0 pts<br>";
echo "• 2ºA vs 2ºB: 1-1 → 2ºA: 1 pt, 2ºB: 1 pt<br>";
echo "• 3ºA vs 3ºB: 0-2 → 3ºA: 0 pts, 3ºB: 3 pts";
echo "</div>";

echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de ejecutarlo por seguridad.</p>";
?>
