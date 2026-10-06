# Módulo: Catálogos académicos

Fuente: `Información general.xlsx` (pestaña *Catálogos*) y decisiones del 5-oct-2026.

## Pantallas
- **Plataforma → Catálogos base** (Super Admin): la base que recibe cada escuela.
- **Configuración → Catálogos académicos** (Administrador escuela): la copia de su escuela, con aviso de
  **novedades del catálogo base** para incorporarlas cuando quiera.

Pestañas: Turnos y horarios · Libros y lecciones · Actividades · Clubes.

## Datos
`school_id` nulo = catálogo base; con escuela = su copia. `base_id` = registro base del que viene la copia.

| Tabla | Campos |
|---|---|
| `shifts` | nombre (Matutino, Vespertino, Sabatino), orden, activo |
| `schedule_slots` | número, desde, hasta, turno, activo (base: bloques de 1 h de 07:00 a 21:00) |
| `books` | nivel, nombre (Beginners, Intermediate, Advanced), activo |
| `lessons` | libro, número de actividad, nombre, tipo (lección / check point / verb in context), orden, activo. Cada libro: Lesson 1–7, Check Point C1 (15), Verb in context 1 (16), Lesson 8–14, Check Point C2 (17), Verb in context 2 (18) |
| `activities` | número de actividad, código (BA, 1P, M, WS, 2P, WP, SP, PR), descripción, minutos, activo |
| `clubs` + `book_club` | nombre, descripción, activo + horas por nivel |
| `schools.club_min_lesson_number` | "¿A partir de cuál lección el alumno puede participar en clubes?" |

## Reglas
- Al dar de alta una escuela se le copia la base activa (`CopyBaseCatalogs`).
- La escuela edita, desactiva o agrega en su copia; no afecta la base ni a otras escuelas.
- Novedades = registros base activos que la escuela aún no tiene; se incorporan solo los que elija. Nunca se sobrescribe lo ajustado.
- Códigos de actividad, números de horario y niveles son únicos dentro de cada catálogo.
- No se elimina lo que está en uso (turno con horarios, libro con lecciones); se puede desactivar.
- Aislamiento: el *global scope* `BelongsToSchool` limita toda consulta a la escuela del usuario.
- Todo cambio queda en `audit_logs`; las copias masivas se registran como un solo evento.
