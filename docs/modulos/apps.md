# Apps instalables (PWA)

El panel también se puede **instalar como una app** en el móvil, la tableta o el ordenador,
sin pasar por ninguna tienda. Hay dos:

| App | Dirección por defecto | Qué muestra |
|---|---|---|
| **App profesorado** | `https://sitio/app-profesorado/` | El panel completo (según los permisos de cada uno) |
| **App Semáforo** | `https://sitio/semaforo/` | Solo "Mis gomets", aunque la persona tenga acceso completo |

## Cómo se usa

1. El profesor abre la dirección en el navegador del móvil y entra con su email.
2. El navegador ofrece **"Instalar app" / "Añadir a pantalla de inicio"**.
3. A partir de ahí la abre desde su icono, a pantalla completa y sin el tema de WordPress.

**Admin** (*Acceso → App profesorado / App Semáforo*): dirección (*slug*), nombre, nombre
corto e icono de cada app. No se pueden usar direcciones reservadas de WordPress
(`wp-admin`, `wp-json`…). El cambio es inmediato.

## Cómo funciona

- Las tres rutas de cada app (página, `manifest.webmanifest` y service worker) se resuelven
  en `parse_request` comparando con el *slug*, sin reglas de reescritura. Así, cambiar la
  dirección no obliga a regenerar los enlaces permanentes.
- El **service worker** solo guarda en caché el CSS y el JS del plugin (con la versión
  en el nombre de la caché). **Nunca guarda datos de la API.** Sin conexión muestra una
  página de aviso.
- Cada app tiene su propia caché y sus iconos (`assets/img/app-icon-*` y `semaforo-icon-*`).

## Código

- `includes/class/app.php` (`LC_App`): página, manifest y service worker.
- `includes/class/app-semaforo.php` (`LC_App_Semaforo`): hereda de `LC_App` y solo
  cambia nombre, colores, iconos y modo.
- `assets/js/pwa.js`: registra el service worker y ofrece la instalación.
