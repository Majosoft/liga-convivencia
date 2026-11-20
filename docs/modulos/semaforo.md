# Semáforo

Valora la convivencia en el aula hora a hora. En cada clase, el profesor pone un
**gomet**: verde (buena hora), amarillo (regular) o rojo (mala). Es la competición
con más peso, porque se registra todas las horas de todos los días.

## Cómo se usa

**Profesorado: "Mis gomets"** (panel o [app del Semáforo](apps.md))
1. Al entrar, el profesor ve **sus clases del día**, sacadas de su [horario](horario.md).
2. Pone un gomet por hora con un toque. Tocar el mismo color otra vez lo quita.
3. Puede moverse a otros días lectivos (no futuros ni de hace más de un año) y
   consultar su horario de la semana.

**Acceso completo: pestaña "Semáforo"** (registro semanal manual)
- Para cada clase y semana se escriben cuántos verdes, amarillos y rojos ha tenido
  (de 0 a 30 por color: 6 horas × 5 días), con notas opcionales.
- Pensado para semanas anteriores al horario o para corregir a mano.

## Puntuación

| Gomet | Puntos por defecto |
|---|---|
| Verde | +1 |
| Amarillo | −1 |
| Rojo | −3 |

Se cambian en *Liga Convivencia → Competición → Semáforo*. Al guardarlos **se
recalculan todas las semanas registradas** (`LC_REST_Semaforo::recalcular_totales`).

## Reglas

- **Un gomet por sesión y día.** Repetirlo sustituye al anterior (clave única
  `sesion_id + fecha`).
- **Clases mezcladas.** Cuando en una hora los alumnos de una clase están repartidos en
  varias sesiones (desdobles, optativas, religión o atención educativa), a esa clase le
  cuenta **el gomet de menor valor** de todas ellas. El reparto lo calcula el
  [horario](horario.md).
- **Los apoyos no ponen gomet**: materias C1, C0 y AL, y el profesor `APOYO`.
- **Acumulado.** `lc_semaforo.total` guarda el acumulado de la clase hasta esa semana.
  Al editar una semana pasada se recalculan en cascada todas las posteriores. En la
  clasificación, los puntos de un periodo son `total al final − total antes de empezar`.
- **Gomets y registro manual.** Si una semana tiene gomets, el recuento automático
  rehace esa semana y sobrescribe lo que se hubiera escrito a mano para esa clase.

## Datos

| Tabla | Contenido |
|---|---|
| `lc_gomets` | Un gomet: `sesion_id`, `fecha`, `profesor`, `color` |
| `lc_semaforo` | Recuento semanal por clase: `gomets_verdes/amarillos/rojos`, `total` (acumulado), `notas` |

## API

| Método | Ruta | Permiso | Uso |
|---|---|---|---|
| GET | `/gomets?fecha=` | profesor | Sesiones del profesor ese día, con su gomet |
| POST | `/gomets` | profesor | `sesion_id`, `fecha`, `color` (`''` lo quita) |
| GET | `/gomets/horario` | profesor | Horario semanal del profesor |
| GET·POST | `/semaforo` | completo | Registro semanal manual (`clase`, `year`, `week`) |

## Código

- `includes/class/horario.php`: `poner_gomet()` y `recalcular_semana()`, el gomet de menor valor.
- `includes/class/rest-gomets.php`: rutas de gomets y validación de fechas.
- `includes/class/rest-semaforo.php`: `guardar_semana()` con la cascada del acumulado.
- `assets/js/ui-gomets.js`: pantalla "Mis gomets". `assets/js/ui-semaforo.js`: registro manual.
