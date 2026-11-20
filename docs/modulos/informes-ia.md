# Informes de IA

Cada semana y cada mes, **Claude** (Anthropic) redacta un informe de la Liga para el
administrador: qué ha pasado, qué va bien, qué clases conviene vigilar y qué hacer
después. Se lee en *Liga Convivencia → Informes IA* y solo lo ven los administradores.

## Cómo se usa

1. Conseguir una clave de API en console.anthropic.com. Lo recomendable es
   definirla en `wp-config.php`:
   ```php
   define('LC_ANTHROPIC_API_KEY', 'sk-ant-...');
   ```
   También se puede pegar en la página *Informes IA → Ajustes*.
2. Marcar **Generar automáticamente cada semana y cada mes**.
3. O pulsar **Generar ahora** en la pestaña Semanal o Mensual. Tarda alrededor de un
   minuto: hay que recargar la página.

Cada informe muestra el periodo, la fecha, el modelo, los tokens y el **coste aproximado**
(unos céntimos). Los anteriores quedan en el lateral.

## Qué hace por dentro

1. **Tarea diaria** (WP-Cron `lc_informes_ia`). Si falta el informe de la semana pasada
   lo genera. Si no, comprueba el del mes pasado. Uno por ejecución.
2. **Datos.** Reutiliza el cálculo de la [clasificación](clasificacion.md) para el periodo,
   el periodo anterior y la temporada, y añade el recuento de gomets por color.
3. **Llamada a la API de mensajes** con `wp_remote_post`. El plugin no usa Composer, y el
   SDK oficial de PHP lo exige junto con PHP 8.1+:
   - modelo `claude-opus-5-5`, `output_config.effort: "medium"`, `max_tokens: 16000`;
   - `fallbacks: "default"` (cabecera `anthropic-beta: server-side-fallback-2026-07-01`):
     si los filtros de seguridad rechazan la petición, la API la repite con el modelo de
     respaldo recomendado;
   - se gestionan los errores HTTP, los rechazos (`stop_reason: "refusal"`) y los cortes
     por longitud.
4. **Prompt.** Secciones fijas (Resumen, Clasificación y cambios, Lo que va bien, Clases a
   vigilar, Propuestas), límite de palabras, español de España y la regla de no
   inventar causas.
5. **Se guarda** en `lc_informes` y se muestra convirtiendo el Markdown a HTML **después
   de escapar** el texto.

## Privacidad

- **Solo se envían datos agregados por clase**, mediante una lista blanca de campos
  (`filas_por_clase()`): puntos por competición y número de partes y de gomets.
  **Ningún nombre de alumno ni de profesor** sale del sitio. Hay una prueba que lo comprueba.
- La clave nunca se muestra en el admin, que solo indica si está guardada.
- El texto generado lleva el aviso de que es IA y hay que revisarlo antes de compartirlo.

## Datos

Tabla `lc_informes`: `tipo` (`semanal` o `mensual`), `iso_year`, `num` (semana o mes),
`periodo`, `estado` (`generando`, `ok` o `error`), `contenido`, `error`, `modelo`,
`tokens_in`, `tokens_out`. Clave única `(tipo, iso_year, num)`.
Opciones: `lc_ia_api_key` (si no está en `wp-config.php`) y `lc_ia_activo`.

## Código y pruebas

- `includes/class/informes-ia.php`: toda la funcionalidad.
- `tests/informes-ia-test.php`: 25 casos (periodos con cambio de año, lista blanca,
  escapado y petición a la API con respuestas simuladas).
