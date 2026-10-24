# Módulo: Horas Coordinador

Decisiones del 23-oct-2026.

## Catálogo (Catálogos académicos → Actividades de coordinación)
`coordinator_activities` (base en Plataforma y copia por escuela): nombre, **horas autorizadas al mes (HA)**,
"Se elige teacher", "Ocupa la hora del teacher" y la letra que se ve en el panel. Iniciales: las 13 actividades del
Excel más **Evaluación de Sesión** (2 h). Sin teacher ("Actividad Coordinación"): Programación de Clubs, Elaboración
de club en Canva, Elaboración de clubs, Preparación de junta Mensual y Junta mensual. Todas las demás llevan teacher;
solo **Revisión de Planeaciones** ocupa su hora (letra **R**).

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
- Las que llevan teacher sin ocupar su hora (Evaluación de Sesión, de Club, retroalimentación, registros…): el teacher
  que está trabajando a esa hora (la coordinadora entra a su clase); su hora no cambia ni se ve en el panel.
- Las de planeación de clubs y juntas se programan como "Actividad Coordinación" (sin teacher).
- Las horas del mes de una actividad no pasan de sus HA: al llegar ya no se agregan más; si se pasan (p. ej. al bajar
  las HA) se marcan en rojo.

## Tablas
`coordinator_activities`, `coordinator_sessions` (school_id, coordinator_activity_id, teacher_id nulo, date,
schedule_slot_id, repeats, user_id; único escuela+fecha+hora), `teacher_availabilities.coordinator_session_id`.

## Permisos
Ven y editan: Coordinador académico y Administrador del instituto.
