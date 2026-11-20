# Acceso y autenticación

Quién puede entrar al panel y cómo. El profesorado **no tiene cuenta de WordPress**:
entra con su email y un código de un solo uso.

## Las dos listas de acceso

*Liga Convivencia → Acceso*:

| Lista | Quién | Qué puede hacer |
|---|---|---|
| **Lista Formularios** | Coordinación, dirección… | Todas las competiciones del panel (acceso completo) |
| **Lista Semáforo** | Profesorado del [horario](horario.md) con su email | Solo poner gomets en sus clases |

Una misma persona puede estar en las dos: entonces ve "Mis gomets" y todo lo demás.

## Cómo se entra

1. Escribe su email en el panel y pulsa **Enviarme un código**.
2. Si el email está en alguna lista, recibe un **código de 6 dígitos** por correo.
3. Lo escribe y entra. La sesión dura 24 horas.

Antes de usarlo en el centro hay que pulsar **Enviar email de prueba** (*Acceso*) para
comprobar que WordPress envía correos. Si no, configurar SMTP (por ejemplo, con WP Mail SMTP).

## Medidas de seguridad

| Medida | Detalle |
|---|---|
| Código de un solo uso | 6 dígitos, caduca en 10 minutos y admite 5 intentos. Solo se guarda su HMAC |
| Sin enumeración | La respuesta es la misma tenga o no acceso el email |
| Límites | Como mucho 5 códigos por email y hora, y 1 por minuto. Por IP, 150 peticiones cada 15 minutos (holgado, porque en un centro todos comparten IP pública) |
| Sesión firmada | Token HMAC-SHA256 con `wp_salt('auth')` en una cookie `HttpOnly`, `Secure` y `SameSite=Strict` (esta última frena el CSRF) |
| Sin token en JavaScript | No se acepta en cabeceras ni se guarda en `localStorage` |
| Cerrar sesión revoca | Una versión de sesión por usuario invalida los tokens copiados |
| Baja inmediata | Cada petición comprueba que el email siga en su lista |
| HTTPS obligatorio | Sin HTTPS no se puede entrar, salvo en entornos `local` o `development` |

## Modo demo

En [WordPress Playground](../../README.md#demo-en-línea) no hay correo, así que el código se
muestra en pantalla. Solo ocurre si la constante `LC_DEMO` es `true`, y solo la define
`blueprint.json`. En una instalación real no se activa nunca, y hay una prueba que lo comprueba.

## Datos

Opciones: `lc_usuarios` (Lista Formularios), `lc_profesores` (Lista Semáforo, con el
horario) y `lc_sesiones` (versión de sesión por email). Los códigos y los contadores
son *transients* temporales.

## API

| Método | Ruta | Uso |
|---|---|---|
| POST | `/auth/login` | `email`: envía el código si tiene acceso |
| POST | `/auth/verify` | `email`, `codigo`: abre la sesión (cookie) |
| GET | `/auth/me` | Quién soy, rol (`completo` o `semaforo`) y profesor asociado |
| POST | `/auth/logout` | Cierra y revoca la sesión |

## Código y pruebas

- `includes/class/auth.php`: listas, códigos, límites, token y cookie.
- `includes/class/rest-auth.php`: rutas. `assets/js/app.js`: login en dos pasos.
- `tests/auth-test.php`: 20 casos.
