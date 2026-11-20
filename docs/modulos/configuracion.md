# Configuración

Todo lo que el administrador ajusta desde el menú **Liga Convivencia** del escritorio de
WordPress. Solo pueden hacerlo usuarios con permiso `manage_options`.

## Páginas del menú

| Página | Pestañas | Qué se configura |
|---|---|---|
| **Clases** | Clases | Nombre, activo y escudo (imagen) de cada clase |
| **Competición** | Deportes, Biblioteca, Semáforo, Partes | Deportes y actividades (modo y puntos), puntos de los gomets y de los partes |
| **Calendario** | Temporadas, Trimestres | Cursos escolares y sus trimestres |
| **Horario** | Horario | Importar el horario y ver la cobertura (ver [horario.md](horario.md)) |
| **Alumnos** | Alumnos | Alta, edición y borrado (ver [alumnado.md](alumnado.md)) |
| **Acceso** | Lista Semáforo, Lista Formularios, App profesorado, App Semáforo | Quién entra (ver [acceso.md](acceso.md)) y las [apps](apps.md) |
| **Informes IA** | Semanal, Mensual | Clave de API e informes (ver [informes-ia.md](informes-ia.md)) |

## Activo en lugar de eliminar

Clases, deportes y actividades tienen la casilla **Activo**. Desmarcarla los quita del
panel pero **conserva su historial en la clasificación**. Eliminar la fila, o renombrar una
clase, rompe el vínculo, porque los registros se enlazan por el nombre exacto. Para
dejar de usar algo, desmarca *Activo*.

## Temporadas y trimestres

- Una **temporada** es un curso escolar (`label`, `inicio`, `fin`). La que no tiene fecha
  de fin es la **activa**: es lo que muestra "General" en la clasificación.
- Los **trimestres** pertenecen a una temporada. El filtro trimestral ofrece los de la
  temporada activa.
- El asistente **Empezar nueva temporada** cierra la temporada en curso el día anterior
  a la nueva, aplica de una vez qué sigue activo y crea los trimestres del nuevo curso.

## Puntuaciones

Los valores llevan **signo**: `-3` resta y `3` suma. Al cambiar los del Semáforo se
recalculan todas las semanas guardadas. Los de Partes y los de enfrentamientos de
Biblioteca se aplican al consultar, así que también afectan al histórico.

## Opciones en la base de datos

| Opción | Contenido |
|---|---|
| `lc_config_clases` | `[{nombre, activo, escudo}]` |
| `lc_config_deportes`, `lc_config_actividades` | `[{value, label, modo, activo, …}]` |
| `lc_config_semaforo`, `lc_config_partes` | Puntos por tipo |
| `lc_config_temporadas`, `lc_config_trimestres` | Calendario |
| `lc_db_version` | Versión del esquema. Si cambia, se relanza el activador sin reactivar el plugin |

## Código

- `includes/class/admin.php`: menú, pestañas, sanitización y asistente de temporada.
- `includes/class/competiciones.php`: lectura de la configuración con valores por defecto.
- `includes/class/activator.php`: tablas y migraciones de versiones anteriores.
- `assets/js/admin-competiciones.js`: filas dinámicas de las tablas de configuración.
