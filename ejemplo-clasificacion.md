# 📊 Página de Clasificación - Liga Creus

## 🎯 Cómo Usar

### 1. Crear una Nueva Página
1. Ve a **WordPress Admin > Páginas > Añadir Nueva**
2. Título: "Clasificación Liga Creus"
3. En el contenido, añade el shortcode:

```
[liga_creus_clasificacion]
```

### 2. Configurar la Página
- **Visibilidad**: Pública (sin login requerido)
- **Plantilla**: Página completa

### 3. Características de la Clasificación

#### 🏆 **Vista General**
- Tabla completa con todas las competiciones
- Puntos por Semáforo, Deportes, Partes, Biblioteca
- Total general de cada clase
- Posiciones con medallas (🥇🥈🥉)

#### 📊 **Vistas Individuales**
- **Semáforo**: Clasificación solo por puntos de semáforo
- **Deportes**: Clasificación solo por puntos de deportes  
- **Partes**: Clasificación solo por puntos de partes
- **Biblioteca**: Clasificación solo por puntos de biblioteca

#### 🎨 **Diseño**
- **Responsive**: Se adapta a móviles y tablets
- **Colores**: Gradientes y colores distintivos
- **Animaciones**: Transiciones suaves entre tabs
- **Auto-refresh**: Se actualiza cada 5 minutos

### 4. Estructura de Datos

La clasificación se calcula automáticamente:

```php
// Semáforo: Total acumulado por clase
SELECT clase, MAX(total) as total_semaforo
FROM lc_semaforo GROUP BY clase

// Deportes: Suma de puntos por clase
SELECT equipo_a, SUM(pts_a + pts_b) as total_deportes
FROM lc_deportes GROUP BY equipo_a

// Partes: Suma de partes por clase
SELECT clase, SUM(COUNT) as total_partes
FROM lc_partes GROUP BY clase

// Biblioteca: Puntos por clase (divididos entre participantes)
SELECT clase, SUM(puntos) as total_biblioteca
FROM lc_biblioteca GROUP BY clase
```

### 5. Personalización CSS

Puedes personalizar los estilos editando:
```
/assets/css/clasificacion.css
```

**Variables principales:**
- Colores de medallas
- Gradientes de fondo
- Tamaños de fuente
- Espaciado

### 6. Ejemplo de Página Completa

```html
<!-- Título de la página -->
<h1>🏆 Liga Creus - Clasificación</h1>

<!-- Shortcode de clasificación -->
[liga_creus_clasificacion]

<!-- Información adicional -->
<div class="info-extra">
    <p>La clasificación se actualiza automáticamente cada 5 minutos.</p>
    <p>Los puntos se calculan sumando todas las competiciones.</p>
</div>
```

### 7. Troubleshooting

**Si no aparece la clasificación:**
1. Verifica que el shortcode esté correcto: `[liga_creus_clasificacion]`
2. Revisa que el plugin esté activo
3. Comprueba los permisos de la página
4. Mira la consola del navegador para errores

**Si hay errores de datos:**
1. Verifica que haya datos en las tablas
2. Revisa los logs de WordPress
3. Comprueba la conexión a la base de datos

### 8. Endpoint REST

- **API**: `/wp-json/liga-creus/v1/clasificacion`

---

## 🚀 ¡Listo para Usar!

La clasificación estará disponible públicamente sin necesidad de login y se actualizará automáticamente con los datos más recientes.
