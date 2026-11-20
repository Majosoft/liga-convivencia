# Documentación de Liga Convivencia

Documentación por módulo del plugin. El [README principal](../README.md) explica el
proyecto, la instalación y la demo; aquí está el detalle de cada parte.

## Módulos

### Competiciones (lo que suma o resta puntos)

| Módulo | Qué registra | Ficha |
|---|---|---|
| Semáforo | Un gomet (verde, amarillo, rojo) por hora de clase | [semaforo.md](modulos/semaforo.md) |
| Deportes | Partidos entre clases, entre alumnos o pruebas por marca | [deportes.md](modulos/deportes.md) |
| Partes | Partes positivos, leves y graves de cada alumno | [partes.md](modulos/partes.md) |
| Biblioteca | Actividades de lectura y partidas (ajedrez…) | [biblioteca.md](modulos/biblioteca.md) |
| Extraordinarias | Actividades puntuales con reparto de puntos | [extraordinarias.md](modulos/extraordinarias.md) |
| Tiempo de Aprendizaje | Puntos mensuales por uso del ticket de baño | [tiempo-aprendizaje.md](modulos/tiempo-aprendizaje.md) |

### Resultados

| Módulo | Ficha |
|---|---|
| Clasificación pública y widget | [clasificacion.md](modulos/clasificacion.md) |
| Informes de IA para el administrador | [informes-ia.md](modulos/informes-ia.md) |

### Gestión y soporte

| Módulo | Ficha |
|---|---|
| Acceso y autenticación | [acceso.md](modulos/acceso.md) |
| Horario y profesorado | [horario.md](modulos/horario.md) |
| Alumnado | [alumnado.md](modulos/alumnado.md) |
| Configuración (clases, puntuaciones, calendario) | [configuracion.md](modulos/configuracion.md) |
| Apps instalables (PWA) | [apps.md](modulos/apps.md) |

## Arquitectura

```
Navegador                                  WordPress (servidor)
─────────                                  ────────────────────
Panel [lc_panel] / apps PWA                API REST  /wp-json/liga-creus/v1
  app.js (sesión, pestañas, barra    ───►    rest-*.php   (una clase por módulo)
          de clase y semana)                    │
  ui-*.js (un archivo por módulo)               ▼
                                            Lógica: horario.php, alumnos.php,
Clasificación [liga_creus_clasificacion]    competiciones.php, auth.php
  clasificacion.js                   ───►       │
                                                ▼
Admin de WordPress                          Base de datos: tablas lc_*
  admin.php, admin-profesorado.php,         Opciones:     lc_config_*, lc_usuarios…
  informes-ia.php                   ───►    WP-Cron:      informes de IA
                                            API externa:  Claude (solo informes)
```

- **Un módulo = una clase REST + un archivo de interfaz.** Por ejemplo, Deportes es
  `includes/class/rest-deportes.php` (servidor) y `assets/js/ui-deportes.js` (panel).
- **Contrato de la interfaz.** Cada `ui-X.js` expone `uiX()`, que devuelve el HTML, y
  `bindX(container, ctx)`, que engancha el comportamiento. `ctx` trae
  `{ clase, year, week, onDirtyChange }` de la barra de contexto compartida, y
  la función devuelve `{ refresh(ctx) }` para recargar al cambiar de clase o semana.
- **Semanas ISO-8601.** Todos los registros semanales se guardan con `iso_year` +
  `iso_week`. Navegador y servidor calculan la semana igual (`isoYearWeek()` en
  `helpers.js` y `date('o')`/`date('W')` en PHP).
- **Clases por nombre, alumnos por id.** Las clases se enlazan por su nombre exacto
  (por eso conviene desactivarlas en lugar de renombrarlas). Los alumnos, por su id,
  así que renombrarlos no rompe su historial.
- **Permisos.** Cada ruta REST declara su `permission_callback`: público,
  `LC_Auth::only_allowed` (acceso completo) o `LC_Auth::solo_profesor` (gomets).
- **Seguridad.** SQL siempre con `$wpdb->prepare()`, entrada con `sanitize_*`,
  salida en el navegador con `lcEscapar()`. Ver [acceso.md](modulos/acceso.md).

## Convenciones del código

- PHP: clases con prefijo `LC_`, opciones `lc_`, tablas `{prefijo}lc_`.
- CSS: clases `lc-`, colores y espaciados en `assets/css/tokens.css`.
- Cada archivo empieza con un comentario que dice qué hace y por qué. Los comentarios
  explican decisiones (el *porqué*), no repiten el código.
- Pruebas en `tests/` (`bash tests/run-all.sh`), sin WordPress: SQLite en memoria para la base de datos y `node:test` para el JavaScript. Se ejecutan en cada push con GitHub Actions.
