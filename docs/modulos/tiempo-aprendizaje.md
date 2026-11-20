# Tiempo de Aprendizaje

Premia a las clases que **menos salen de clase**: cuanto menos se usa el ticket de
baño en un mes, más puntos. Es la única competición **mensual**.

## Cómo se usa (panel, acceso completo)

1. Elegir la clase y el mes en el propio formulario. Este módulo no usa la semana de
   la barra de contexto.
2. Escribir los puntos del mes (número entero, 0 o más) y **Guardar**.
3. **Borrar mes** quita el registro de esa clase en ese mes.

Debajo se muestra el ranking de Tiempo de Aprendizaje de la temporada.

## Reglas

- Un registro por **clase y mes**. Guardar otra vez lo actualiza.
- Los puntos son libres: el centro decide la escala.
- Solo valen clases configuradas y meses del 1 al 12.
- **En la clasificación:** en la vista *Mensual* cuenta el mes completo. En los demás
  filtros, los meses se asignan al rango de semanas del periodo.
- Si una carga lenta llega cuando el profesor ya ha escrito los puntos, se descarta,
  para no sustituirlos por 0.

## Datos

Tabla `lc_tiempo_aprendizaje`: `clase`, `iso_year`, `iso_month`, `puntos`. Clave única
`(clase, iso_year, iso_month)`.

## API

| Método | Ruta | Parámetros |
|---|---|---|
| GET | `/tiempo-aprendizaje` | `clase`, `year`, `month` |
| POST | `/tiempo-aprendizaje` | `clase`, `year`, `month`, `puntos`, o `borrar: true` |

## Código

- `includes/class/rest-tiempo-aprendizaje.php`: lectura y guardado o borrado (`INSERT … ON DUPLICATE KEY UPDATE`).
- `assets/js/ui-tiempo-aprendizaje.js`: formulario mensual y ranking.
