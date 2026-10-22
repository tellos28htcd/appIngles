# Módulo: Programación mensual de clubs

Decisiones del 21-oct-2026.

## Catálogos
- **Clubes** (Catálogos base y de la escuela): además de las horas por nivel, cada club tiene sus **abreviaturas**
  (`clubs.codes`, JSON): código (C12, B23, T3…), niveles que cubre, color del panel (1–9) y activa. Las abreviaturas no
  se repiten entre los clubes de la escuela; cada instituto pone las que use. Iniciales: C12/C23, M12/M23, S12/S23,
  B12/B23, F12/F23 y T3.
- **Aulas**: "Aula audiovisual (para clubs)" (`classrooms.is_club_room`). Puede haber varias. Cada una tiene
  **periodos sin disponibilidad** (`classroom_closures`: fechas, todo el día o de una hora a otra, motivo).

## Programación (Teachers → Programación de clubs)
Calendario del mes: una cuadrícula por semana (días × horas del catálogo). En cada hora se agrega el club
(abreviatura), el teacher y si es presencial o en línea; desde la misma celda se cambia o se elimina. Opción
"Repetir en las semanas siguientes del mes" (avisa las semanas que no se pudieron).

Reglas (`App\Actions\Clubs\ScheduleClub`):
- El teacher debe tener esa hora: horario base o "Cubre horas", sin ausencia, baja, fecha de ingreso posterior ni otro
  club (`AvailabilityPlanner::availableTeacherIds`). La Lista de espera no da clubs.
- Cada club ocupa un aula audiovisual activa, libre y fuera de sus periodos sin disponibilidad: a la misma hora caben
  tantos clubs como aulas audiovisuales (presencial o en línea, un teacher cada uno).
- No hay clubs en días festivos ni en horas cuyo turno no aplica ese día.
- La hora del club **deja de estar disponible para clases normales**: en el panel la celda queda ligada al club
  (`teacher_availabilities.club_session_id`) con el aula audiovisual y muestra la abreviatura; el aula no se cambia ahí.
  Si el teacher falta y alguien lo cubre, el club pasa a quien cubre. Un club cuyo teacher ya no tiene la hora en una
  semana armada se marca en rojo para revisarlo.

## Tablas
`club_sessions` (school_id, club_id, code, teacher_id, classroom_id, date, schedule_slot_id, modality, user_id; únicos
aula+fecha+hora y teacher+fecha+hora), `classroom_closures`, `clubs.codes`, `classrooms.is_club_room`,
`teacher_availabilities.club_session_id`.

## Permisos
Edita: Administrador de escuela y Coordinador académico (los que editan el panel). Consulta: Recepción.

## Pendiente
Reservación de alumnos a los clubs (Agenda): niveles de la abreviatura, cupo del aula y "Cantidad máxima de club por
alumno" de Mi Instituto. Copiar la programación del mes anterior e impresión del calendario.
