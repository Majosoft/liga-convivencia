# Actividades extraordinarias

Actividades puntuales que no encajan en otra competición: recogidas solidarias,
concursos, jornadas… Tienen **nombre libre** y cada clase participante recibe una
puntuación fija.

## Cómo se usa (panel, acceso completo)

1. Elegir la semana.
2. Escribir el nombre de la actividad y añadir las clases participantes, cada una con
   sus puntos: **10, 8, 5, 3 o 0** (por ejemplo, 1.º, 2.º, 3.º, 4.º puesto y participación).
3. Observaciones opcionales y **Guardar**.
4. **Editar** permite cambiar clases, puntos o el nombre. **Borrar** elimina la
   actividad entera de esa semana.

## Reglas

- Una actividad se identifica por **nombre + semana**. Guardarla otra vez sustituye
  todas sus clases.
- **Renombrar** sobre el nombre de otra actividad de la misma semana la sustituye
  (el panel pide confirmación).
- No se puede repetir una clase en la misma actividad, y solo valen clases configuradas.
  Se comprueba antes de borrar la versión anterior, para no dejarla a medias.

## Datos

Tabla `lc_extraordinarias`, una fila por clase participante: `nombre`, `iso_year`,
`iso_week`, `clase`, `puntos`, `notas`. Clave única `(nombre, iso_year, iso_week, clase)`.

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/extraordinarias` | `year`, `week` (actividades agrupadas por nombre) |
| POST | `/extraordinarias` | `nombre`, `year`, `week`, `clases` (JSON `[{clase, puntos}]`), `notas`, `original_nombre` (para renombrar) |
| DELETE | `/extraordinarias` | `nombre`, `year`, `week` |

## Código

- `includes/class/rest-extraordinarias.php`: alta con sustitución, lista agrupada y borrado.
- `assets/js/ui-extraordinarias.js`: formulario de clases dinámico, lista y ranking.
