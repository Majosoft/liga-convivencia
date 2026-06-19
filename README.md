# Plugin Liga Creus - Estructura Modular

## Descripción
Plugin de WordPress para gestión de competiciones escolares con sistema de autenticación por email autorizado.

## Estructura del Proyecto

```
Plugin-Liga-Creus/
├── liga-creus.php              # Archivo principal del plugin
├── assets/                      # Recursos frontend
│   ├── css/
│   │   └── app.css             # Estilos principales
│   └── js/
│       ├── app.js              # JavaScript principal
│       ├── helpers.js          # Utilidades JavaScript
│       ├── ui-semaforo.js      # Módulo Semáforo
│       ├── ui-deportes.js      # Módulo Deportes
│       └── ui-partes.js        # Módulo Partes
├── includes/                    # Lógica del plugin
│   ├── helpers.php             # Utilidades PHP
│   └── class/                  # Clases del plugin
│       ├── activator.php       # Activación y creación de tablas
│       ├── auth.php            # Sistema de autenticación
│       ├── admin.php            # Panel de administración
│       ├── assets.php           # Gestión de recursos
│       ├── rest-auth.php        # API REST - Autenticación
│       ├── rest-semaforo.php    # API REST - Semáforo
│       ├── rest-deportes.php    # API REST - Deportes
│       └── rest-partes.php      # API REST - Partes
└── README.md                   # Este archivo
```

## Módulos

### 1. Autenticación (`LC_Auth`)
- Gestión de tokens JWT
- Validación de emails autorizados
- Sistema de cookies seguras

### 2. Administración (`LC_Admin`)
- Panel de configuración en WordPress
- Gestión de emails autorizados
- Configuración del plugin

### 3. API REST
- **Autenticación**: Login, logout, verificación de sesión
- **Semáforo**: Gestión de puntos por clase y semana
- **Deportes**: Registro de partidos y resultados
- **Partes**: Control de partes disciplinarias

### 4. Frontend
- **JavaScript modular**: Cada funcionalidad en su propio archivo
- **CSS responsive**: Diseño moderno y adaptable
- **Shortcode**: `[lc_panel]` para mostrar el formulario

## Funcionalidades

### Semáforo
- Registro de puntos base y delta por clase/semana
- Cálculo automático de totales
- Historial de puntuaciones

### Deportes
- Registro de partidos (fútbol, baloncesto, ping pong, matapollos)
- Sistema de puntuación 3-1-0
- Gestión de equipos y marcadores

### Partes
- Control de partes disciplinarias por clase
- Registro semanal de incidencias
- Seguimiento de comportamiento

## Instalación

1. Subir la carpeta del plugin a `/wp-content/plugins/`
2. Activar el plugin desde el panel de WordPress
3. Configurar emails autorizados en `Ajustes > Liga Creus`
4. Usar el shortcode `[lc_panel]` en cualquier página

## Desarrollo

### Estructura Modular
El plugin está dividido en módulos independientes que facilitan:
- **Mantenimiento**: Cada funcionalidad en su archivo
- **Debugging**: Errores localizados por módulo
- **Escalabilidad**: Fácil añadir nuevas funcionalidades
- **Testing**: Pruebas unitarias por módulo

### Convenciones
- **Clases**: Prefijo `LC_` para evitar conflictos
- **Archivos**: Nombres descriptivos y organizados
- **JavaScript**: Funciones globales para cada módulo
- **CSS**: Clases con prefijo `lc-`

## API Endpoints

### Autenticación
- `POST /wp-json/liga-creus/v1/auth/login`
- `GET /wp-json/liga-creus/v1/auth/me`
- `POST /wp-json/liga-creus/v1/auth/logout`

### Semáforo
- `GET /wp-json/liga-creus/v1/semaforo`
- `POST /wp-json/liga-creus/v1/semaforo`

### Deportes
- `GET /wp-json/liga-creus/v1/deportes`
- `POST /wp-json/liga-creus/v1/deportes`

### Partes
- `GET /wp-json/liga-creus/v1/partes`
- `POST /wp-json/liga-creus/v1/partes`

## Base de Datos

### Tablas Creadas
- `wp_lc_semaforo`: Puntuaciones del semáforo
- `wp_lc_deportes`: Partidos y resultados deportivos
- `wp_lc_partes`: Registro de partes disciplinarias

## Seguridad

- Autenticación por JWT con HMAC
- Validación de emails autorizados
- Sanitización de todas las entradas
- Nonces de WordPress para CSRF
- Cookies seguras (HttpOnly, Secure)

## Versión
0.2.0 - Estructura modular refactorizada
