# Módulo: Horas Coordinador

Decisiones del 23-oct-2026.

## Catálogo (Catálogos académicos → Actividades de coordinación)
`coordinator_activities` (base en Plataforma y copia por escuela): nombre, **horas autorizadas al mes (HA)**,
"Ocupa la hora del teacher" y la letra que se ve en el panel. Iniciales: las 13 actividades del Excel; solo
**Revisión de Planeaciones** ocupa la hora del teacher (letra **R**).

## Programación (Teachers → Horas Coordinador)
Calendario del mes igual al de clubs (una cuadrícula por semana, renglones alternos). Arriba, la tabla del catálogo
con **HA** y **HP** (horas programadas en el mes) y el indicador "Todo coincide"; pestañas colapsadas "Resumen por
teacher" y "Resumen por actividad" (mes elegido y mes anterior). Clic en el + de un día y hora: Actividad y Teacher;
opción "Repetir en las semanas siguientes del mes" (se conserva al editar).

Reglas (`App\Actions\Coordination\ScheduleCoordinatorHour`):
- Una actividad del coordinador por hora; no en festivos ni en horas cuyo turno no aplica.
- Si la actividad ocupa la hora del teacher (Revisión de Planeaciones): solo teachers con esa hora libre (horario
  base o "Cubre horas", sin ausencia, club ni otra actividad). Esa hora deja de servir para clases y clubs; en el panel
  se ve su letra en blanco sobre negro (`teacher_availabilities.coordinator_session_id`, sin aula).
- Las demás actividades se programan como "Actividad Coordinación" (sin teacher) y no se ven en el panel.
- Las horas del mes de una actividad no pasan de sus HA: al llegar ya no se agregan más; si se pasan (p. ej. al bajar
  las HA) se marcan en rojo.

## Tablas
`coordinator_activities`, `coordinator_sessions` (school_id, coordinator_activity_id, teacher_id nulo, date,
schedule_slot_id, repeats, user_id; único escuela+fecha+hora), `teacher_availabilities.coordinator_session_id`.

## Permisos
Ven y editan: Coordinador académico y Administrador del instituto.
