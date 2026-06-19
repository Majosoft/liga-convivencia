<?php
/**
 * Test para verificar la nueva lógica de gomets
 * 
 * INSTRUCCIONES:
 * 1. Sube este archivo a la raíz de WordPress
 * 2. Ejecuta: https://tudominio.com/test-gomets-logic.php
 * 3. Elimina este archivo después de ejecutarlo
 */

// Verificar que estamos en WordPress
if (!defined('ABSPATH')) {
    die('Acceso directo no permitido');
}

echo "<h2>🧪 Test de Lógica de Gomets</h2>";

// Simular la función calculateTotal
function testCalculateTotal($verdes, $amarillos, $rojos) {
    return ($verdes * 1) - ($amarillos * 1) - ($rojos * 3);
}

echo "<h3>📊 Pruebas de Cálculo:</h3>";

$test_cases = [
    ['verdes' => 5, 'amarillos' => 2, 'rojos' => 1, 'esperado' => 0],
    ['verdes' => 3, 'amarillos' => 1, 'rojos' => 0, 'esperado' => 2],
    ['verdes' => 2, 'amarillos' => 3, 'rojos' => 1, 'esperado' => -4],
    ['verdes' => 10, 'amarillos' => 5, 'rojos' => 2, 'esperado' => -1],
    ['verdes' => 0, 'amarillos' => 0, 'rojos' => 0, 'esperado' => 0],
];

foreach ($test_cases as $i => $test) {
    $resultado = testCalculateTotal($test['verdes'], $test['amarillos'], $test['rojos']);
    $correcto = $resultado === $test['esperado'];
    
    echo "<div style='border: 1px solid " . ($correcto ? '#10b981' : '#ef4444') . "; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
    echo "<strong>Prueba " . ($i + 1) . ":</strong> ";
    echo "Verdes: {$test['verdes']}, Amarillos: {$test['amarillos']}, Rojos: {$test['rojos']}<br>";
    echo "Resultado: $resultado | Esperado: {$test['esperado']} ";
    echo $correcto ? "✅" : "❌";
    echo "</div>";
}

echo "<h3>🔢 Fórmula Actual:</h3>";
echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Total = (Verdes × 1) - (Amarillos × 1) - (Rojos × 3)</strong><br>";
echo "• 🟢 Gomets Verdes: +1 punto cada uno<br>";
echo "• 🟡 Gomets Amarillos: -1 punto cada uno<br>";
echo "• 🔴 Gomets Rojos: -3 puntos cada uno";
echo "</div>";

echo "<h3>📝 Ejemplos Prácticos:</h3>";
echo "<div style='background: #f8fafc; padding: 15px; border-radius: 8px; margin: 10px 0;'>";
echo "<strong>Ejemplo 1:</strong> 5 verdes, 2 amarillos, 1 rojo = (5×1) - (2×1) - (1×3) = 5 - 2 - 3 = 0<br>";
echo "<strong>Ejemplo 2:</strong> 3 verdes, 1 amarillo, 0 rojos = (3×1) - (1×1) - (0×3) = 3 - 1 - 0 = 2<br>";
echo "<strong>Ejemplo 3:</strong> 2 verdes, 3 amarillos, 1 rojo = (2×1) - (3×1) - (1×3) = 2 - 3 - 3 = -4";
echo "</div>";

echo "<p><strong>IMPORTANTE:</strong> Elimina este archivo después de ejecutarlo por seguridad.</p>";
?>
