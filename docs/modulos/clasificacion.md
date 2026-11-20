# Clasificación

La parte visible de la Liga: una página **pública, sin login**, con la clasificación de
las clases y los rankings de cada competición.

## Cómo se usa

Crear una página con el shortcode `[liga_creus_clasificacion]`
(ver [ejemplo-clasificacion.md](../../ejemplo-clasificacion.md)). Se refresca sola cada
5 minutos.

### Filtros de periodo

| Periodo | Qué muestra |
|---|---|
| **General** | La temporada en curso (o todo el histórico si no hay temporadas) |
| **Semanal** | Una semana del curso |
| **Mensual** | Un mes. Cada semana cuenta entera en el mes de su miércoles, para que la suma de los meses dé el total |
| **Trimestral** | Un trimestre de la temporada en curso |
| **Temporada** | Un curso ya cerrado |

### Pestañas

- **General**: una fila por clase con cada competición y el total.
- **Semáforo, Deportes, Partes, Extraordinarias, Biblioteca, T. Aprendizaje**: ranking de
  cada una. Deportes y Biblioteca tienen una subpestaña por deporte o actividad, y en
  los modos con alumnos un conmutador *Clases / Alumnos*.
- **Alumnos**: ranking global de alumnos.

## Reglas de cálculo

- **Semáforo:** acumulado al final del periodo menos el acumulado justo antes.
- **Puntos enteros.** El servidor redondea cada columna (Biblioteca reparte y deja
  decimales), y el **total es la suma de lo que se ve**. Así el orden, la tabla y los
  [informes de IA](informes-ia.md) usan el mismo número.
- **Empates:** las clases con los mismos puntos comparten puesto (1, 2, 2, 4) y medalla.

## Privacidad

- El **ranking público de alumnos solo cuenta méritos** (deportes, biblioteca y
  extraordinarias) y solo muestra a quien suma puntos. Los partes no entran: un parte no
  puede deducirse desde fuera.
- El ranking de **partes por alumno** solo se incluye si la petición trae una sesión de
  acceso completo, y entonces la respuesta lleva `Cache-Control: private, no-store`.
- Los alumnos borrados no aparecen.

## Widget del tema

`GET /widget` devuelve `{ok, html}` con la clasificación general ya maquetada (estilos
en línea), para el widget "Indicadores de Convivencia" del tema. Se guarda en caché 5
minutos, y cualquier escritura en la API del plugin borra esa caché.

## API

| Método | Ruta | Permiso | Parámetros |
|---|---|---|---|
| GET | `/clasificacion` | público | `periodo` (`general`, `semanal`, `mensual`, `trimestral`, `temporada`), `year`, `week`, `month`, `trimestre`, `temporada` |
| GET | `/widget` | público | — |

La respuesta incluye `clasificacion` (por clase), `por_deporte`, `por_actividad`,
`alumnos`, `alumnos_partes` (vacío sin sesión), `trimestres`, `temporadas` y `semanas_mes`.

## Código

- `includes/class/rest-clasificacion.php`: `get_clasificacion()` (agrega las seis
  fuentes), `compute_week_range()` (periodos) y el widget.
- `includes/class/shortcode-clasificacion.php`: marcado de la página y carga de recursos.
- `assets/js/clasificacion.js`: filtros, pestañas y tablas. `helpers.js`: rankings
  compartidos con el panel (`renderRankingConToggle`, `lcPosiciones`).
