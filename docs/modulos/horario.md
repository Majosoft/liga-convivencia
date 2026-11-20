# Horario y profesorado

El horario del centro dice **qué profesor está con qué clase a cada hora**. De él
salen las clases que ve cada profesor en "Mis gomets" y a qué clase cuenta cada gomet
del [Semáforo](semaforo.md).

## Cómo se usa (admin)

**Importar el horario**: *Liga Convivencia → Horario*. Se pega un CSV con esta cabecera:

```csv
profesor,dia,hora,materia,aula,grupo,n
AEM,1,2,LE,BIBL,2ºBDS,
```

| Columna | Significado |
|---|---|
| `profesor` | Iniciales del profesor |
| `dia` | 1 (lunes) a 5 (viernes) |
| `hora` | 1 a 6 |
| `materia`, `aula` | Como en el horario del centro |
| `grupo` | Grupo o desdoble (`3ºADI` pertenece a la clase `3ºA`) |
| `n` | En sesiones que mezclan grupos, cuántos grupos más incluye |

Importar **sustituye el horario entero**. Los gomets ya puestos se conservan si su
sesión sigue existiendo (mismo profesor, día, hora y grupo).

**Profesorado**: *Acceso → Lista Semáforo*. Iniciales, nombre y **email** de cada
profesor. Sin email, ese profesor no puede entrar.

## Cómo se reparte cada sesión entre las clases

1. La clase de una sesión son los 3 primeros caracteres del grupo (`3ºADI` → `3ºA`).
2. En la hora de **religión** de un curso, todas las sesiones mezcladas (católica,
   evangélica, atención educativa) cubren a todas sus clases.
3. Si a una hora una clase no tiene sesión propia, sus alumnos están en las sesiones
   mezcladas de su curso (optativas de A y B), y esas sesiones también la cubren.
4. Las materias **C1, C0 y AL** y el profesor **APOYO** no ponen gomet.

La página Horario muestra la **cobertura** de cada clase (horas cubiertas de 30 y los
huecos), para detectar errores en el CSV.

## Datos

| Dónde | Contenido |
|---|---|
| Tabla `lc_horario` | Una sesión: `profesor`, `dia`, `hora`, `materia`, `aula`, `grupo`, `n`, `clases` (las que cubre, separadas por comas), `cuenta` (si pone gomet) |
| Opción `lc_profesores` | `[{iniciales, nombre, email}]` |
| `data/horario.csv`, `data/profesores.csv` | Datos de **demostración** (profesorado ficticio) que se cargan al activar |
| `data/privado/` | Datos reales del centro. **Fuera de git**. Si existen, se cargan estos |

## Código

- `includes/class/horario.php`: `importar()`, `cobertura()`, `comprobar_cobertura()`,
  `seed()` y los gomets.
- `includes/class/admin-profesorado.php`: páginas Horario y Lista Semáforo.
