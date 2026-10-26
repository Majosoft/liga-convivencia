# 📊 Página de Clasificación - Liga Convivencia

## 🎯 Cómo Usar

### 1. Crear una Nueva Página
1. Ve a **WordPress Admin > Páginas > Añadir Nueva**
2. Título: "Clasificación Liga Convivencia"
3. En el contenido, añade el shortcode:

```
[liga_creus_clasificacion]
```

### 2. Configurar la Página
- **Visibilidad**: Pública (sin login requerido)
- **Plantilla**: Página completa

### 3. Características de la Clasificación

#### 🏆 Vista General
- Tabla con una fila por clase y una columna por competición: Semáforo, Deportes,
  Partes, Extraordinarias, Biblioteca y Tiempo de Aprendizaje, más el total.
- Las posiciones se numeran 1, 2, 3… y cada clase muestra su escudo si tiene uno
  asignado en **Liga Convivencia → Clases**.

#### 📊 Pestañas individuales
- **Semáforo**, **Partes**, **Extraordinarias** y **T. Aprendizaje**: ranking de
  esa competición. La de Partes desglosa positivos, leves y graves.
- **Deportes** y **Biblioteca**: una subpestaña por cada deporte o actividad
  configurados, con su propio ranking.

#### 🗓️ Filtro de periodo
Encima de las pestañas, un selector permite ver la clasificación de:
- **General**: la temporada en curso (o todo el histórico si no hay temporadas configuradas).
- **Semanal** y **Mensual**: una semana o un mes concretos de la temporada.
- **Trimestral**: los trimestres definidos en **Liga Convivencia → Calendario**.
- **Temporada anterior**: el total de un curso ya cerrado.

#### 🎨 Diseño
- **Responsive**: se adapta a móviles y tablets.
- **Auto-refresh**: la tabla se recarga sola cada 5 minutos.
- Estilos en `assets/css/clasificacion.css`, sobre los tokens de `assets/css/tokens.css`.

### 4. De dónde salen los datos

La clasificación no se guarda: se calcula en cada petición en
`includes/class/rest-clasificacion.php`, agregando las seis tablas del plugin y
aplicando el filtro de periodo. Dos detalles que conviene conocer:

- **El Semáforo es acumulado.** Dentro de un periodo se calcula como
  `total al final − total justo antes de empezar`, no sumando las filas.
- **Los inactivos siguen contando.** Un deporte, actividad o clase desmarcado como
  *Activo* desaparece del panel de registro, pero su histórico se sigue viendo aquí.

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
<h1>🏆 Liga Convivencia - Clasificación</h1>

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
