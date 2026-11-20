# Biblioteca

Actividades de la biblioteca del centro: podcasts, traducciones, mitos, ajedrez…
Cada actividad se configura con uno de **tres modos**.

## Modos

| Modo | Qué se registra | Puntos |
|---|---|---|
| **Participantes** | Lista de participantes (clase y nombre) y una valoración | Los puntos del nivel elegido se **reparten** entre los participantes; cada uno aporta su parte a su clase |
| **Enfrentamiento** | Clase A contra clase B y el resultado | Victoria / empate / derrota (3 / 1 / 0 por defecto) |
| **Alumnos** | Alumno de la clase A contra alumno de la clase B | Como enfrentamiento, y además al alumno |

Niveles de valoración por defecto en el modo participantes: Excelente 3, Regular 1,
Insuficiente 0.

## Cómo se usa (panel, acceso completo)

1. Elegir la semana y la actividad.
2. Según el modo: añadir participantes y valoración, o elegir las dos clases (o alumnos)
   y quién gana.
3. **Guardar**. **Editar** y **Borrar** en la lista de la semana.

## Reglas

- **Reparto con decimales.** 3 puntos entre 2 participantes dan 1,5 a cada uno. La
  clasificación lo redondea a enteros (ver [clasificacion.md](clasificacion.md)).
- **Puntos al consultar.** En enfrentamiento solo se guarda el ganador: los puntos se
  calculan con la configuración vigente, así que cambiarlos afecta al histórico.
- **Enfrentamiento:** una partida por pareja de clases y semana, con el orden
  normalizado. **Alumnos:** cada partida es un registro propio.
- Solo se aceptan actividades que existan en la configuración.

## Datos

Tabla `lc_biblioteca`: `actividad`, `iso_year`, `iso_week`, `participantes` (JSON),
`puntos` (modo participantes), `clase_a/b`, `alumno_a/b` y `ganador`
(`clase-a`, `clase-b` o `empate`).

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/biblioteca` | `actividad`, `year`, `week` |
| POST | `/biblioteca` | `participantes` + `puntos`, o `clase_a/b` + `ganador` (+ `alumno_a/b`). `id` para editar |
| DELETE | `/biblioteca` | `id` |

## Configuración

*Liga Convivencia → Competición → Biblioteca*: nombre, modo, niveles o puntos, y si está activa.

## Código

- `includes/class/rest-biblioteca.php`: `upsert_participantes()`, `upsert_enfrentamiento()` y `borrar()`.
- `assets/js/ui-biblioteca.js`: formulario, lista y ranking de la actividad.
