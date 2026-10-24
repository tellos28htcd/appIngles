# Módulo: Programación mensual de clubs

Decisiones del 21-oct-2026.

## Catálogos
- **Clubes** (Catálogos base y de la escuela): además de las horas por nivel, cada club tiene sus **abreviaturas**
  (`clubs.codes`, JSON): código (C12, B23, T3…), niveles que cubre, color del panel (1–9) y activa. Las abreviaturas no
  se repiten entre los clubes de la escuela; cada instituto pone las que use. Iniciales: C12/C23, M12/M23, S12/S23,
  B12/B23, F12/F23 y T3.
- **Aulas**: "Aula virtual" (`classrooms.is_virtual`; sin marcar = física) y "Aula para clubs (audiovisual)"
  (`is_club_room`, físicas para los presenciales); los clubs en línea usan cualquier aula virtual. Las aulas para clubs tienen **reservas para otra actividad** (`classroom_closures`: fechas, todo el
  día o de una hora a otra, actividad), una por una, con su historial; en la programación aparecen como
  "Reservada para otra actividad" al recorrer los meses.

## Programación (Teachers → Programación de clubs)
Calendario del mes: una cuadrícula por semana (días × horas del catálogo). En cada hora se agrega el club
(abreviatura), el teacher y si es presencial o en línea; desde la misma celda se cambia o se elimina. Opción
"Repetir en las semanas siguientes del mes" al agregar o al editar (avisa las semanas que no se pudieron). Arriba,
pestañas "Resumen por teacher" (horas del mes y del mes anterior) y "Resumen por tipo" (horas por teacher y
abreviatura); la leyenda muestra las horas programadas de cada abreviatura.

Reglas (`App\Actions\Clubs\ScheduleClub`):
- El teacher debe tener esa hora: horario base o "Cubre horas", sin ausencia, baja, fecha de ingreso posterior ni otro
  club (`AvailabilityPlanner::availableTeacherIds`). La Lista de espera no da clubs.
- Cada club ocupa un aula para clubs activa, libre y sin reserva: el presencial, una física; el en línea, una virtual.
  A la misma hora caben tantos presenciales como aulas físicas y tantos en línea como aulas virtuales, cada uno con
  otro teacher (p. ej. 1 presencial + 2 en línea).
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
