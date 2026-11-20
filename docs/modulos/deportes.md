# Deportes

Competiciones deportivas semanales. Cada deporte se configura con uno de **tres modos**.

## Modos

| Modo | Qué se registra | Puntos |
|---|---|---|
| **Enfrentamiento** | Clase A contra clase B, con marcador | Victoria / empate / derrota (3 / 1 / 0 por defecto) a cada clase |
| **Alumnos** | Alumno de la clase A contra alumno de la clase B | Igual que enfrentamiento, y **además** al alumno |
| **Individual** (por marca) | Participantes (clase, nombre, marca) | Se ordena por marca y se reparten puntos por posición (5, 3, 2, 1 por defecto) |

En el modo individual se elige si gana la marca menor (tiempos) o la mayor (distancias).

## Cómo se usa (panel, acceso completo)

1. Elegir la semana en la barra de contexto y el deporte en el formulario.
2. Rellenar según el modo: las dos clases y el marcador, los dos alumnos, o la lista
   de participantes con su marca.
3. **Guardar**. La lista de la semana y el ranking del deporte se actualizan al momento.
4. **Editar** carga el registro en el formulario. **Borrar** lo elimina, después de confirmar.

## Reglas

- **Enfrentamiento:** un partido por pareja de clases y semana. Guardarlo otra vez lo
  actualiza. El orden se normaliza: "1ºB–1ºA" se guarda como "1ºA–1ºB", con el
  marcador girado.
- **Alumnos:** cada partida es un registro propio, porque las mismas clases pueden
  jugar varias veces con alumnos distintos. Cada alumno tiene que ser de la clase
  por la que compite.
- **Individual:** un evento por deporte y semana. Los puntos se calculan al guardar.
- Solo se aceptan deportes que existan en la configuración.

## Datos

Tabla `lc_deportes`: `deporte`, `iso_year`, `iso_week`, `equipo_a/b`,
`marcador_a/b`, `pts_a/b`, `alumno_a/b` (ids) y `participantes` (JSON, en el modo individual).

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/deportes` | `deporte`, `year`, `week` |
| POST | `/deportes` | Según el modo: `equipo_a/b` + `marcador_a/b` (+ `alumno_a/b`), o `participantes` (JSON). `id` para editar |
| DELETE | `/deportes` | `id` |

Todas exigen acceso completo.

## Configuración

*Liga Convivencia → Competición → Deportes*: nombre, modo, puntos y si está activo.
Desactivar un deporte lo quita del panel sin perder su histórico.

## Código

- `includes/class/rest-deportes.php`: `upsert_enfrentamiento()`, `upsert_individual()` y `borrar()`.
- `assets/js/ui-deportes.js`: formulario, lista semanal y ranking del deporte.
- Puntos en la clasificación: `rest-clasificacion.php`, bloque de Deportes.
