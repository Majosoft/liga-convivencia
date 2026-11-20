# Partes

Partes de conducta: **positivos** (reconocimientos) y **negativos** (leves o graves).
Cada parte se asocia a un alumno o, si no se elige ninguno, a toda la clase.

## Cómo se usa (panel, acceso completo)

1. Elegir clase y semana en la barra de contexto.
2. Elegir el alumno (o "Toda la clase") y el tipo de parte. Las notas son opcionales.
3. **Añadir**. La lista de la semana muestra cada parte con sus puntos y un resumen.
4. **Borrar** quita un parte puesto por error.

## Puntuación

| Tipo | Puntos por defecto |
|---|---|
| Positivo | +3 |
| Leve | −1 |
| Grave | −10 |

Se cambian en *Liga Convivencia → Competición → Partes*. Los puntos se calculan al
consultar, así que **un cambio afecta también al histórico**.

## Reglas

- **La clase del parte es la del alumno**, no la que venga del formulario, para que sus
  puntos no puedan acabar en otra clase.
- Si el alumno se borra después, el parte se conserva (cuenta para la clase) y se
  muestra como "Alumno eliminado". Sus notas se borran (ver [alumnado.md](alumnado.md)).

## Privacidad

Los partes son datos de conducta de menores:
- En la **clasificación pública** solo aparecen **agregados por clase** (positivos, leves,
  graves y saldo). Nunca junto a un nombre.
- El **ranking de partes por alumno** solo se envía a quien tiene acceso completo, con
  `Cache-Control: private, no-store`.
- El ranking público de alumnos no suma los partes (ver [clasificacion.md](clasificacion.md)).

## Datos

Tabla `lc_partes_alumnos`, una fila por parte: `alumno_id` (0 = toda la clase),
`clase`, `iso_year`, `iso_week`, `tipo`, `notas`.
La tabla antigua `lc_partes` (contadores por clase) se migra sola a esta al actualizar.

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/partes` | `clase`, `year`, `week` |
| POST | `/partes` | `clase`, `year`, `week`, `tipo`, `alumno_id` (opcional), `notas` |
| DELETE | `/partes` | `id` |

## Código

- `includes/class/rest-partes.php`: alta, lista con resumen y borrado.
- `assets/js/ui-partes.js`: formulario, lista y ranking de clases o alumnos.
