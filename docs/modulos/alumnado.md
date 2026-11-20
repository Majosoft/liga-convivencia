# Alumnado

Los alumnos aparecen en los partes, en los enfrentamientos entre alumnos (Deportes y
Biblioteca) y en el ranking de alumnos. Del alumnado **solo se guarda el nombre de
pila y la clase**.

## Cómo se usa

- **Admin**: *Liga Convivencia → Alumnos*. Alta, edición (nombre, clase, activo) y borrado.
- **Panel**: en cualquier formulario que pida un alumno, el botón **+ Nuevo** lo da de
  alta sin salir del formulario. Si ya existe en esa clase, se reutiliza.

## Reglas

- **Por id, no por nombre.** Los registros apuntan al id del alumno, así que renombrarlo
  no rompe su historial.
- Nombre único por clase. Solo valen clases configuradas.
- **Cambiar de clase** no mueve sus puntos anteriores: siguen contando para la clase con
  la que los consiguió.
- **Inactivo**: deja de ofrecerse en los formularios, pero conserva sus puntos y su historial.

## Borrado y supresión de datos (RGPD)

Borrar un alumno elimina **también sus datos personales**:

| Dónde | Qué pasa |
|---|---|
| `lc_alumnos` | Se borra la ficha |
| Sus partes | Se conservan (los puntos son de la clase), **sin notas**, como "Alumno eliminado" |
| Participantes de Biblioteca y pruebas por marca | Su nombre pasa a "Alumno eliminado" (también si está guardado con los acentos escapados en el JSON) |
| Enfrentamientos entre alumnos | Se conservan con su id, que ya no lleva a ningún nombre |

Los puntos de su clase no cambian. Otro alumno con el mismo nombre en otra clase no se toca.

## Datos

Tabla `lc_alumnos`: `id`, `nombre`, `clase`, `activo`. Clave única `(nombre, clase)`.

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/alumnos` | `clase` (sin ella, todos los activos) |
| POST | `/alumnos` | `nombre`, `clase` (alta o reutilización) |

Ambas exigen acceso completo.

## Código y pruebas

- `includes/class/alumnos.php`: `find_or_create()`, `update()`, `delete()` con supresión y `clase_valida()`.
- `includes/class/rest-alumnos.php`: rutas. `assets/js/helpers.js`: selector de alumno con alta al vuelo.
- `tests/alumnos-test.php`: 11 casos.
