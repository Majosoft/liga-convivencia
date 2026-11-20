# Liga Convivencia

Plugin de WordPress que convierte la convivencia de un instituto en una **liga entre
clases**. El profesorado registra puntos desde el móvil (gomets del semáforo en cada
hora de clase, deportes, biblioteca, partes, actividades…) y una clasificación
pública muestra en tiempo real qué clase va primera.

> **Trabajo Fin de Máster** del Máster de Programación con IA. Es un proyecto real:
> se usa en un instituto durante el curso 2026-27.

- **Versión**: 1.2.0
- **Requiere**: WordPress 5.8+, PHP 7.3+, HTTPS en producción
- **Licencia del código**: propiedad del autor (Majosoft)

---

## Índice

0. [Demo en línea](#demo-en-línea)
1. [El problema](#el-problema)
2. [La solución](#la-solución)
3. [Funcionalidades](#funcionalidades)
4. [Stack tecnológico](#stack-tecnológico)
5. [Instalación y ejecución](#instalación-y-ejecución)
6. [Seguridad y privacidad](#seguridad-y-privacidad)
7. [Desarrollo con IA](#desarrollo-con-ia)
8. [Estructura del proyecto](#estructura-del-proyecto)
9. [Referencia técnica](#referencia-técnica)
10. [Recursos externos](#recursos-externos)
11. [Pruebas](#pruebas)
12. [Mejoras pendientes](#mejoras-pendientes)

---

## Demo en línea

**[▶ Abrir la demo en WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/Majosoft/liga-convivencia/main/blueprint.json)**

Un WordPress completo que se ejecuta en el navegador, sin instalar nada. El
[blueprint.json](blueprint.json) instala el plugin desde este repositorio y carga
datos ficticios de dos cursos ([demo/seed.php](demo/seed.php)): el anterior, cerrado
y con sus tres trimestres, y el actual hasta hoy. Así todos los filtros de la
clasificación (general, semanal, mensual, trimestral y temporada) y todas las
modalidades de cada competición tienen datos. Tarda menos de un
minuto en arrancar, y cada visita empieza de cero.

| Qué probar | Dónde | Acceso |
|---|---|---|
| Clasificación pública | `/clasificacion/` (página inicial) | Sin login |
| Panel del profesorado (todas las competiciones) | `/panel/` | Email `direccion@demo.edu` |
| Solo gomets del Semáforo | `/semaforo/` | Email `profe@demo.edu` |
| Administración e **Informes IA** | `/wp-admin/` → *Liga Convivencia* | `admin` / `password` |

En la demo no se envían emails: el código de acceso aparece en pantalla (modo
demo, activado solo por la constante `LC_DEMO` del blueprint). Para generar un
informe de IA hay que añadir una clave de la API de Anthropic en *Informes IA*.

## El problema

El centro organiza una *liga de convivencia*: las clases compiten durante el curso
y suman o restan puntos por su comportamiento, su participación en deportes y en la
biblioteca, los partes, el uso del ticket de baño… La idea funciona, pero en la
práctica se gestionaba con **hojas de cálculo y papel**:

- Cada profesor apuntaba los gomets del semáforo (verde, amarillo o rojo) en papel
  y alguien los pasaba a mano, una vez por semana y clase.
- Los resultados de deportes, biblioteca y actividades llegaban por canales
  distintos y había que sumarlos.
- La clasificación se publicaba tarde y sin detalle, y el alumnado —que es a quien
  tiene que motivar— no la veía al día.

## La solución

Un plugin para la web de WordPress que el centro ya tiene, sin servidores nuevos
ni aplicaciones de tienda:

- **Panel del profesorado** (shortcode `[lc_panel]`) con una pestaña por competición.
- **Dos apps instalables (PWA)** que se abren desde un icono en el móvil: la del
  profesorado (todo el panel) y la del **Semáforo** (solo los gomets del día).
- **Semáforo por hora de clase**: cada profesor ve *sus* clases del día según su
  horario y pone un gomet por hora con un toque. El recuento semanal se calcula solo.
- **Clasificación pública** (shortcode `[liga_creus_clasificacion]`) por periodos
  (semana, mes, trimestre, temporada), con escudos de clase y rankings por competición.
- **Administración** desde el escritorio de WordPress: clases, competiciones,
  puntuaciones, calendario, horario, alumnado y quién tiene acceso.
- **Informes con IA** para el administrador: cada semana y cada mes, Claude analiza
  los puntos de cada clase y redacta un informe con lo que va bien, las clases a
  vigilar y propuestas concretas.

## Funcionalidades

### Competiciones y puntuación

| Módulo | Periodicidad | Puntuación (configurable) |
|---|---|---|
| **Semáforo** | Por hora de clase | Gomet verde +1, amarillo −1, rojo −3. Se acumula semana a semana |
| **Deportes** | Semanal | *Enfrentamiento* clase contra clase (3/1/0), *individual* alumno contra alumno, o *por marca* (5, 3, 2, 1 por posición) |
| **Partes** | Semanal, por alumno | Positivo +3, leve −1, grave −10 |
| **Biblioteca** | Semanal | Por participantes, por enfrentamiento entre clases o individual |
| **Extraordinarias** | Semanal | Actividad libre: 10, 8, 5, 3 o 0 puntos por clase |
| **Tiempo de Aprendizaje** | Mensual | Puntos libres (cuanto menos ticket de baño, más puntos) |

### Semáforo por horario (1.0)

- El horario del centro se importa desde un CSV (Liga Convivencia → Horario).
- Cada profesor entra con su email y ve sus clases del día. Puede consultar también
  su horario de la semana.
- Reglas del centro ya implementadas: si en una hora los alumnos de una clase están
  repartidos en varias sesiones (desdobles, optativas, religión/atención educativa),
  cuenta el gomet **de menor valor**; los apoyos no ponen gomet.
- El acumulado semanal de `lc_semaforo` se rehace a partir de los gomets, y al
  editar una semana pasada se recalculan en cascada las posteriores.

### Dos niveles de acceso

- **Lista Formularios** (acceso completo): todas las competiciones.
- **Lista Semáforo**: profesorado del horario que solo pone gomets en sus clases.

### Clasificación pública

- Tabla general y pestañas por competición, con sub-rankings por deporte y actividad.
- Filtros: general (temporada activa), semanal, mensual, trimestral y temporadas cerradas.
- En la vista mensual, cada semana cuenta en un solo mes (el de su miércoles), para
  que la suma de los meses dé el total.
- Ranking de alumnos **solo con méritos** (ver [privacidad](#seguridad-y-privacidad)).
- Se refresca sola cada 5 minutos.

### Informes de IA (1.2)

Página **Liga Convivencia → Informes IA**, solo para administradores:

- **Informe semanal** (de la semana anterior) y **mensual** (del mes anterior), con
  las secciones *Resumen*, *Clasificación y cambios*, *Lo que va bien*, *Clases a
  vigilar* y *Propuestas*.
- Se generan solos con una tarea diaria de WP-Cron, o al momento con **Generar ahora**.
- Para cada periodo, el modelo recibe los puntos por clase y competición, la
  comparación con el periodo anterior, la clasificación de la temporada y el
  recuento de gomets verdes, amarillos y rojos por clase.
- Histórico de informes anteriores, con modelo, tokens y coste aproximado de cada uno
  (unos céntimos por informe).
- Modelo `claude-opus-5-5` con respaldo automático del servidor
  (`fallbacks: "default"`) si los filtros de seguridad rechazan la petición.

### Gestión del curso

- Clases con escudo, deportes, actividades y puntuaciones editables.
- Temporadas y trimestres, con un asistente **Empezar nueva temporada**.
- Alumnado: se registra solo el **nombre** (sin apellidos) y la clase.
- *Activo* vs. eliminar: desactivar algo lo saca del panel sin perder su histórico.

## Stack tecnológico

| Capa | Tecnología |
|---|---|
| Plataforma | WordPress 5.8+ (plugin), shortcodes, Settings API, transients |
| Backend | PHP 7.3+ orientado a clases (`LC_*`), API REST de WordPress (`liga-creus/v1`) |
| Base de datos | MySQL/MariaDB vía `$wpdb`, 10 tablas propias con migraciones automáticas |
| Frontend | JavaScript sin frameworks ni build (ES2020, `fetch`), CSS con *design tokens* |
| App móvil | PWA: manifest + service worker servidos por el propio plugin |
| Autenticación | Código de un solo uso por email + token HMAC-SHA256 en cookie `HttpOnly` |
| IA | API de Claude (Anthropic), modelo `claude-opus-5-5`, vía `wp_remote_post` + WP-Cron |
| Pruebas | PHP CLI con simulación de las funciones de WordPress |
| Desarrollo | Claude Code (IA), Git/GitHub, Local (WordPress local), Playwright (capturas) |

Sin dependencias de Composer ni npm: el plugin se instala copiando una carpeta.

## Instalación y ejecución

### En un WordPress (producción o local)

1. Copia la carpeta del plugin en `wp-content/plugins/` (o sube el `.zip` desde
   *Plugins → Añadir nuevo → Subir plugin*) y actívalo.
   Al activarlo se crean las tablas y se cargan el profesorado y el horario de
   **demostración** de `data/` (nombres ficticios).
2. **Comprueba el correo**: *Liga Convivencia → Acceso* → **Enviar email de prueba**.
   El login depende de que WordPress pueda enviar emails; si no llega, configura
   SMTP (p. ej. con el plugin *WP Mail SMTP*).
3. Da de alta los emails con acceso en *Acceso → Lista Formularios* y/o asigna
   emails al profesorado en *Acceso → Lista Semáforo*.
4. Revisa *Clases*, *Competición* y *Calendario*.
5. Crea dos páginas: una con `[lc_panel]` (panel del profesorado) y otra con
   `[liga_creus_clasificacion]` (clasificación pública). Ver
   [ejemplo-clasificacion.md](ejemplo-clasificacion.md).
6. Opcional, **informes de IA**: añade la clave de la API de Anthropic (mejor en
   `wp-config.php`: `define('LC_ANTHROPIC_API_KEY', 'sk-ant-...');`, o en la propia
   página *Informes IA*) y marca *Generar automáticamente*.
7. Opcional: las apps instalables están en `https://tu-sitio/app-profesorado/` y
   `https://tu-sitio/semaforo/` (las direcciones se cambian en el admin).

En producción el sitio **debe ir por HTTPS**: sin él, el plugin no permite iniciar
sesión. En un entorno `local` o `development` (constante `WP_ENVIRONMENT_TYPE`) sí.

### Datos reales del centro

El horario y el profesorado reales **no están en el repositorio**. Se colocan en
`data/privado/horario.csv` y `data/privado/profesores.csv` (carpeta ignorada por git)
y el plugin los usa en lugar de los de demostración. Formato:

```csv
iniciales,nombre
AEM,Adrián Esteban Marín
```
```csv
profesor,dia,hora,materia,aula,grupo,n
AEM,1,2,LE,BIBL,2ºBDS,
```

### Actualizaciones

`liga-creus.php` compara `lc_db_version` con `LC_VERSION` en cada carga y vuelve a
lanzar el activador cuando cambian: **al subir una versión nueva no hace falta
reactivar el plugin** para que se apliquen los cambios de esquema.

## Seguridad y privacidad

El plugin guarda datos de **menores** (nombre, clase y partes de conducta), así que
la seguridad se ha tratado como requisito, no como añadido. En la versión 1.1.0 se
hizo una auditoría completa con ayuda de IA y se corrigió todo lo encontrado.

**Autenticación**
- Login en dos pasos: el email recibe un **código de 6 dígitos** que caduca en
  10 minutos, es de un solo uso y admite 5 intentos. Solo se guarda su HMAC.
- La respuesta es idéntica exista o no el email: la API no permite averiguar quién
  tiene acceso. Límite de 20 peticiones de login por IP cada 15 minutos.
- La sesión es un token firmado con HMAC-SHA256 (`wp_salt('auth')`), en una cookie
  `HttpOnly`, `Secure` y `SameSite=Strict` (esto último corta el CSRF), válida 24 h.
  No se acepta en cabeceras ni se guarda en `localStorage`.
- Cerrar sesión **invalida el token** (versión de sesión por usuario); dar de baja a
  un usuario corta su acceso al instante.
- Sin HTTPS no se puede iniciar sesión fuera de un entorno local.

**Datos y API**
- Toda consulta con parámetros pasa por `$wpdb->prepare()`; toda entrada por
  `sanitize_*`; el admin exige `manage_options` y nonce en cada acción.
- Todo lo que se pinta en el navegador se escapa con `lcEscapar()` (protección XSS).
- Cada profesor de la Lista Semáforo solo puede leer y escribir gomets de sus clases.

**Privacidad (RGPD)**
- *Minimización*: del alumnado solo se guarda el nombre de pila y la clase.
- La clasificación pública muestra alumnos **solo por méritos** (deportes, biblioteca,
  extraordinarias) y solo si suman puntos. Los partes nunca aparecen asociados a un
  nombre en público; el ranking de partes por alumno solo llega al profesorado con
  acceso completo y con `Cache-Control: private, no-store`.
- **Informes de IA**: a la API de Anthropic solo se envían datos **agregados por
  clase** (lista blanca explícita de campos); nunca nombres de alumnos ni de profesores.
  El texto del modelo se escapa antes de mostrarse, y la clave de la API puede vivir
  en `wp-config.php` en lugar de en la base de datos.
- Los datos reales del profesorado y el horario se mantienen fuera del repositorio;
  se limpió el historial de git antes de publicarlo.
- Pendiente para el centro: política de conservación (borrado al cerrar el curso) e
  información a las familias, validado por su delegado de protección de datos.

## Desarrollo con IA

El proyecto se ha desarrollado con **Claude Code** (Anthropic) como asistente de
programación en el editor, sobre una base inicial propia (versión 0.x). La IA ha
intervenido en todo el ciclo:

La IA está presente de dos formas: como **herramienta de desarrollo** y como
**funcionalidad del producto** (los [informes semanales y mensuales](#informes-de-ia-12)
que redacta Claude para el administrador).

| Fase | Uso de la IA |
|---|---|
| Análisis | Traducir las reglas del centro (desdobles, apoyos, gomet de menor valor) a un modelo de datos de horario y gomets |
| Diseño | Rediseño de la interfaz con *design tokens*, panel por pestañas y PWA instalable |
| Implementación | Endpoints REST, migraciones de esquema idempotentes, lógica de clasificación por periodos ISO |
| Depuración | Diagnóstico directo en la base de datos local (MySQL de Local) y capturas con Playwright para verificar la interfaz |
| Seguridad | Auditoría del código: se detectaron login sin verificación, exposición pública de datos de menores y XSS en atributos, y se corrigieron con pruebas |
| Producto | Diseño de los informes de IA: qué datos enviar (y cuáles no), prompt con secciones fijas, llamada a la API con respaldo ante rechazos, y pruebas con respuestas simuladas |
| Datos | Anonimización del horario real (profesorado ficticio con la misma estructura) y limpieza del historial de git |
| Documentación | Este README, comentarios en el código explicando el *porqué* y mensajes de commit descriptivos |

**Forma de trabajo**: yo defino el problema y las reglas del centro, y decido;
la IA propone e implementa; cada cambio se revisa, se prueba en un WordPress local y
se versiona en git. Los commits hechos con IA llevan la línea `Co-Authored-By: Claude`,
así que el historial muestra qué parte del trabajo se hizo con ella.

## Estructura del proyecto

```
liga-creus.php                    Cabecera del plugin, constantes, carga de clases y hooks
data/
  profesores.csv · horario.csv    Datos de DEMOSTRACIÓN (profesorado ficticio)
  privado/                        Datos reales del centro (ignorado por git)
demo/seed.php                     Datos ficticios para la demo (solo con LC_DEMO)
blueprint.json                    Configuración de la demo en WordPress Playground
includes/
  helpers.php                     Sanitización y logging
  class/
    activator.php                 Creación de tablas y migraciones de versiones antiguas
    auth.php                      Listas de acceso, código por email, token y cookie
    competiciones.php             Configuración: clases, deportes, actividades, puntos
    alumnos.php                   Alumnado (por id, no por nombre)
    horario.php                   Horario, profesorado, gomets y recálculo del semáforo
    admin.php                     Menú, pestañas, ajustes y asistente de temporada
    admin-profesorado.php         Lista Semáforo y Horario
    app.php · app-semaforo.php    Las dos PWA (página, manifest, service worker)
    informes-ia.php               Informes semanales y mensuales con IA (admin)
    assets.php                    Shortcode [lc_panel] y carga de CSS/JS
    shortcode-clasificacion.php   Shortcode público de la clasificación
    rest-*.php                    Un endpoint REST por módulo (auth, gomets, semáforo,
                                  deportes, partes, alumnos, biblioteca,
                                  extraordinarias, tiempo-aprendizaje, clasificación)
assets/
  css/   tokens.css · app.css · clasificacion.css · admin.css
  js/    helpers.js · app.js · ui-*.js (uno por módulo) · clasificacion.js · pwa.js
  img/   Iconos de las dos apps
tests/
  auth-test.php                   Pruebas de autenticación (PHP CLI)
  informes-ia-test.php            Pruebas de los informes de IA (PHP CLI)
```

Convenciones: clases PHP con prefijo `LC_`, CSS con `lc-`, opciones con `lc_`. Cada
módulo del panel expone `uiX()` (marcado) y `bindX(container, ctx)` (comportamiento).

## Referencia técnica

### API REST (`/wp-json/liga-creus/v1`)

| Método | Ruta | Acceso | Parámetros |
|---|---|---|---|
| POST | `/auth/login` | público | `email` → envía el código si tiene acceso |
| POST | `/auth/verify` | público | `email`, `codigo` → abre la sesión |
| GET | `/auth/me` | público | — |
| POST | `/auth/logout` | público | — |
| GET | `/gomets` | profesor | `fecha` |
| POST | `/gomets` | profesor | `sesion_id`, `fecha`, `color` |
| GET | `/gomets/horario` | profesor | — |
| GET·POST | `/semaforo` | completo | `clase`, `year`, `week` |
| GET·POST | `/deportes` | completo | `deporte`, `year`, `week` |
| GET·POST·DELETE | `/partes` | completo | `clase`, `year`, `week`; POST `alumno_id`, `tipo`; DELETE `id` |
| GET·POST | `/alumnos` | completo | `clase`; alta con `nombre` + `clase` |
| GET·POST | `/biblioteca` | completo | `actividad`, `year`, `week` |
| GET·POST | `/extraordinarias` | completo | `year`, `week` |
| GET·POST | `/tiempo-aprendizaje` | completo | `clase`, `year`, `month` |
| GET | `/clasificacion` | público | `periodo`, `year`, `week`, `month`, `trimestre`, `temporada` |
| GET | `/widget` | público | — (HTML ya renderizado para el widget del tema) |

Las semanas son **ISO-8601** (`iso_year` + `iso_week`).

### Base de datos

| Tabla | Contenido | Clave única |
|---|---|---|
| `lc_semaforo` | Acumulado semanal por clase | `(clase, iso_year, iso_week)` |
| `lc_horario` | Sesiones del horario | — |
| `lc_gomets` | Un gomet por sesión y día | `(sesion_id, fecha)` |
| `lc_deportes` | Partidos y pruebas | `(deporte, iso_year, iso_week, equipo_a, equipo_b)` |
| `lc_partes_alumnos` | Un parte por fila | índices por semana y alumno |
| `lc_biblioteca` | Actividades de biblioteca | índice `(actividad, iso_year, iso_week)` |
| `lc_extraordinarias` | Actividades extraordinarias | `(nombre, iso_year, iso_week, clase)` |
| `lc_tiempo_aprendizaje` | Puntos mensuales | `(clase, iso_year, iso_month)` |
| `lc_alumnos` | Nombre de pila y clase | `(nombre, clase)` |
| `lc_informes` | Informes de IA | `(tipo, iso_year, num)` |
| `lc_partes` | Modelo antiguo (se migra a `lc_partes_alumnos`) | `(clase, iso_year, iso_week)` |

Guardar dos veces la misma semana actualiza la fila en vez de duplicarla. El
activador migra los esquemas de versiones anteriores y es idempotente.

### Configuración (menú *Liga Convivencia*)

| Página | Contenido | Opciones |
|---|---|---|
| Clases | Nombre, activo, escudo | `lc_config_clases` |
| Competición | Deportes, Biblioteca, Semáforo, Partes | `lc_config_*` |
| Calendario | Temporadas, trimestres | `lc_config_temporadas`, `lc_config_trimestres` |
| Acceso | Lista Formularios, Lista Semáforo, email de prueba | `lc_usuarios`, `lc_profesores` |
| Horario | Importación y consulta del horario | tabla `lc_horario` |

## Recursos externos

- [WordPress](https://wordpress.org/) y su [API REST](https://developer.wordpress.org/rest-api/).
- [Google Fonts](https://fonts.google.com/): tipografías *Inter* y *Baloo 2*.
- Iconos de las apps: propios (`assets/img/`).
- [API de Claude](https://platform.claude.com/docs) (Anthropic): redacción de los informes. Requiere una clave de API de pago por uso.
- [Claude Code](https://claude.com/claude-code): asistente de IA usado en el desarrollo.
- [Local](https://localwp.com/): entorno WordPress local. [Playwright](https://playwright.dev/): capturas para verificar la interfaz.

## Pruebas

```bash
php tests/auth-test.php
php tests/informes-ia-test.php
```

- **Autenticación** (18 casos): código de un solo uso, bloqueo tras 5 fallos, límite
  por IP, rechazo de tokens manipulados o en cabecera, cierre de sesión, bajas de
  usuario y HTTPS obligatorio.
- **Informes de IA** (25 casos): periodos con cambio de año ISO, lista blanca de
  campos enviados, escapado del Markdown del modelo, y la petición a la API
  (cabeceras, modelo, respaldo, rechazos y errores) con respuestas simuladas.

Se ejecutan sin WordPress ni red gracias a una simulación mínima de sus funciones.

## Mejoras pendientes

- Enviar el informe por email al administrador además de mostrarlo en el admin.

- Herramienta de borrado de datos de alumnado al cerrar una temporada.
- Servir las fuentes desde el propio sitio en lugar de Google Fonts (privacidad).
- Ampliar las pruebas a la lógica de clasificación y del semáforo.
