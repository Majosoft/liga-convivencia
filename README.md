# Liga Convivencia

Plugin de WordPress para gestionar una liga de convivencia escolar: el profesorado
registra puntos por clase en varias competiciones desde un panel propio, y la
clasificación resultante se publica en una página abierta, sin login.

- **Versión**: 0.8.1
- **Autor**: Majosoft
- **Requiere**: WordPress 5.8+, PHP 7.3+ (por los atributos `SameSite` de la cookie de sesión)

## Puesta en marcha

1. Sube la carpeta del plugin a `/wp-content/plugins/` y actívalo.
2. Ve a **Liga Convivencia → Acceso → Usuarios** y da de alta el email de cada
   profesor. Ese email es su credencial: no hay contraseña ni PIN.
3. Revisa **Liga Convivencia → Clases** y **→ Competición** para ajustar clases,
   deportes y actividades.
4. Crea dos páginas: una con `[lc_panel]` (panel del profesorado) y otra con
   `[liga_creus_clasificacion]` (clasificación pública). Ver
   [ejemplo-clasificacion.md](ejemplo-clasificacion.md) para la segunda.

Las tablas se crean al activar. Además, `liga-creus.php` compara `lc_db_version`
con `LC_VERSION` en cada carga y vuelve a lanzar el activador cuando cambian, así
que **al subir una versión nueva no hace falta reactivar el plugin a mano** para
que se apliquen los cambios de esquema.

## Shortcodes

| Shortcode | Acceso | Qué muestra |
|---|---|---|
| `[lc_panel]` | Profesorado (email autorizado) | Panel de registro, con una pestaña por módulo |
| `[liga_creus_clasificacion]` | Público | Clasificación con filtros de periodo y pestañas por competición |
| `[liga_creus_test]` | Público | Diagnóstico mínimo (versión y URL del plugin) |

## Módulos y puntuación

El panel comparte una barra de contexto con **clase** y **semana**; cada módulo
usa lo que necesita de ella.

| Módulo | Periodicidad | Puntuación |
|---|---|---|
| **Semáforo** | Semanal, por clase | Gomet verde +1, amarillo −1, rojo −3 — configurable |
| **Deportes** | Semanal | *Enfrentamiento*: clase A vs clase B, victoria/empate/derrota configurables (3/1/0). *Individual*: alumno de la clase A vs alumno de la clase B, misma puntuación. *Por marca*: se ordena por marca y se reparten puntos por posición (5,3,2,1) |
| **Partes** | Semanal, **por alumno** | Positivo +3, leve −1, grave −10 — configurable |
| **Biblioteca** | Semanal | *Participantes*: los puntos del nivel se reparten entre los participantes, cada uno aporta a su clase. *Enfrentamiento*: clase A contra clase B. *Individual*: alumno de la clase A vs alumno de la clase B |
| **Extraordinarias** | Semanal | Actividad de nombre libre; cada clase participante recibe 10, 8, 5, 3 o 0 |
| **Tiempo de Aprendizaje** | **Mensual**, por clase | Puntos libres (a menos uso del ticket de baño, más puntos) |

El **Semáforo guarda un acumulado**: el `total` de cada semana es el de la semana
anterior más lo ganado en la actual. Al editar una semana pasada, el plugin
recalcula en cascada todas las posteriores de esa clase.

Los puntos de gomets y partes se editan en **Competición → Semáforo** y
**Competición → Partes**, con su signo (`-3` resta, `3` suma). Cambiar los de
Partes afecta también al histórico, porque sus puntos se calculan al consultar
la Clasificación. Los del Semáforo obligan a rehacer el acumulado, así que al
guardarlos **se recalculan automáticamente todas las semanas ya registradas**.

## Alumnos

Un alumno pertenece a una clase y se referencia **por id**, no por nombre: a
diferencia de las clases, renombrar a un alumno no rompe su histórico.

Se dan de alta desde **Liga Convivencia → Alumnos** o, más cómodo, sobre la
marcha desde cualquier formulario que los use (botón *+ Nuevo* junto al
selector). Una vez creado queda disponible en todos los módulos.

Los puntos de una prueba individual van **a la vez a la clase y al alumno**, así
que la clasificación por clases no cambia de significado al activar el modo
individual: simplemente además se sabe quién los consiguió.

Cambiar a un alumno de clase no mueve sus puntos anteriores: siguen contando
para la clase con la que los consiguió. Eliminarlo tampoco borra sus registros;
estos conservan sus puntos para la clase y dejan de mostrar el nombre.

## Clasificación

`GET /clasificacion` agrega las seis fuentes y devuelve, además de la tabla
general, un ranking por cada deporte y por cada actividad. Se puede filtrar por
periodo:

- **General**: la temporada activa, o todo el histórico si no hay ninguna configurada.
- **Semanal** / **Mensual**: `year` + `week`, o `year` + `month`.
- **Trimestral**: por nombre de trimestre, priorizando el de la temporada activa.
- **Temporada**: por nombre de temporada (cursos ya cerrados).

Como el Semáforo es acumulado, dentro de un periodo se calcula como
`total al final − total justo antes de empezar`, no sumando filas. La página
pública se refresca sola cada 5 minutos.

## Configuración (menú *Liga Convivencia*)

| Página | Pestañas | Opción en BD |
|---|---|---|
| Clases | Clases (nombre, activo, escudo) | `lc_config_clases` |
| Competición | Deportes, Biblioteca, Semáforo, Partes | `lc_config_deportes`, `lc_config_actividades`, `lc_config_semaforo`, `lc_config_partes` |
| Calendario | Temporadas, Trimestres | `lc_config_temporadas`, `lc_config_trimestres` |
| Acceso | Usuarios | `lc_usuarios` |

**Activo vs. eliminar.** Desmarcar *Activo* en una clase, deporte o actividad la
retira del panel pero **conserva su histórico en la clasificación**. Eliminar la
fila, o renombrar una clase, rompe ese vínculo: el nombre exacto es lo que une los
registros guardados con la clase. Para dejar de usar algo, desmarca *Activo*.

El asistente **Empezar nueva temporada** (Calendario → Temporadas) cierra la
temporada en curso el día anterior a la nueva, aplica de una vez el checklist de
qué sigue activo y crea los trimestres del curso que empieza.

## API REST

Namespace `liga-creus/v1`. Todos los endpoints requieren sesión válida salvo los
marcados como públicos.

| Método | Ruta | Parámetros |
|---|---|---|
| POST | `/auth/login` | `email` — *público* |
| GET | `/auth/me` | — *público* |
| POST | `/auth/logout` | — *público* |
| GET | `/test` | — *público* |
| GET·POST | `/semaforo` | `clase`, `year`, `week` |
| GET·POST | `/deportes` | `deporte`, `year`, `week` |
| GET·POST·DELETE | `/partes` | `clase`, `year`, `week`; POST añade uno (`alumno_id`, `tipo`), DELETE borra por `id` |
| GET·POST | `/alumnos` | `clase` para listar; `nombre` + `clase` para dar de alta |
| GET·POST | `/biblioteca` | `actividad`, `year`, `week` |
| GET·POST | `/extraordinarias` | `year`, `week` |
| GET·POST | `/tiempo-aprendizaje` | `clase`, `year`, `month` |
| GET | `/clasificacion` | `periodo`, `year`, `week`, `month`, `trimestre`, `temporada` — *público* |
| GET | `/widget` | — *público*. Devuelve `{ok, html}` con la clasificación ya renderizada, para el widget del tema |

Las semanas son **ISO-8601** (`iso_year` + `iso_week`), no semanas naturales del mes.

## Base de datos

Seis tablas, todas con prefijo de la instalación:

| Tabla | Clave única |
|---|---|
| `lc_semaforo` | `(clase, iso_year, iso_week)` |
| `lc_deportes` | `(deporte, iso_year, iso_week, equipo_a, equipo_b)` |
| `lc_partes` | `(clase, iso_year, iso_week)` |
| `lc_biblioteca` | índice por `(actividad, iso_year, iso_week)` |
| `lc_extraordinarias` | `(nombre, iso_year, iso_week, clase)` |
| `lc_tiempo_aprendizaje` | `(clase, iso_year, iso_month)` |
| `lc_alumnos` | `(nombre, clase)` |
| `lc_partes_alumnos` | una fila por parte, índices por semana y por alumno |

Gracias a esas claves, guardar dos veces la misma semana actualiza la fila en vez
de duplicarla. El activador también migra esquemas antiguos (campo único `partes`
→ `partes_positivos`; `partes_negativos` → `partes_leves`). Las columnas viejas no
se borran: quedan huérfanas e inofensivas.

## Seguridad

- **El acceso es solo por email.** La lista de emails autorizados vive en la
  opción `lc_usuarios` y se gestiona en **Acceso → Usuarios**; la lista antigua
  `lc_allowed_emails` se migra sola. No hay contraseña, PIN ni verificación de
  que quien escribe el email sea su dueño: **conocer un email de la lista basta
  para entrar al panel y escribir datos**. Mantén la lista corta, da de baja a
  quien deje de necesitar acceso y no publiques la URL de la página del panel.
- **Token firmado** con HMAC-SHA256 sobre `wp_salt('auth')`, guardado en la cookie
  `lc_token` (HttpOnly, Secure en HTTPS, **SameSite=Strict**) con 24 h de validez.
- Cada petición revalida que el usuario **siga existiendo**: eliminarlo corta su
  acceso al instante, aunque tuviera la sesión abierta.
- Toda entrada pasa por las funciones `sanitize_*` de WordPress y toda consulta
  con parámetros por `$wpdb->prepare()`.
- El panel de administración exige `manage_options` y nonce propio en cada acción.

## Estructura

```
liga-creus.php                 Cabecera, constantes, requires y hooks
includes/
  helpers.php                  LC_Helpers: sanitización y logging
  class/
    activator.php              Creación de tablas y migraciones
    auth.php                   Emails autorizados, token y cookie
    competiciones.php          Configuración: clases, deportes, actividades, periodos
    admin.php                  Menú, pestañas, sanitización y asistente de temporada
    assets.php                 Shortcode [lc_panel] y carga de CSS/JS
    rest-auth.php              login / me / logout / test
    rest-semaforo.php          Semáforo, con recálculo en cascada
    rest-deportes.php          Deportes (enfrentamiento e individual)
    rest-partes.php            Partes
    rest-biblioteca.php        Biblioteca (participantes y enfrentamiento)
    rest-extraordinarias.php   Actividades extraordinarias
    rest-tiempo-aprendizaje.php Tiempo de aprendizaje (mensual)
    rest-clasificacion.php     Agregación y filtros de periodo
    shortcode-clasificacion.php Shortcode público
assets/
  css/  tokens.css · app.css · clasificacion.css · admin.css
  js/   helpers.js · app.js · ui-*.js · clasificacion.js · admin-competiciones.js
```

## Convenciones

- Clases PHP con prefijo `LC_`, clases CSS con `lc-`, opciones con `lc_`.
- Cada módulo del panel expone `uiX()` (devuelve el marcado) y `bindX(container, ctx)`,
  que recibe `{ clase, year, week, onDirtyChange }` y devuelve `{ refresh(ctx) }`.
- Los assets se encolan desde el propio shortcode, no desde `wp_enqueue_scripts`,
  para que funcionen también en widgets y plantillas FSE.
