# Módulo: Teachers → Horario, incidencias y disponibilidad de horarios

Decisiones del 16-oct-2026 a partir de *Creacion de Agenda.xlsx*, hoja "H de Teacher".

## Menú
Teachers pasa a ser módulo con: **Teachers** (lista y ficha), **Disponibilidad de horarios** (panel) e **Incidencias**.
Editan: Administrador de escuela y Coordinador académico. Consulta: quien tenga la opción en Roles y permisos.

## Ficha del teacher
- **Nombre corto** (MELISSA…) y **número de panel** (orden de su columna, único por escuela). Sin número no aparece en el panel.
- **Lista de espera**: teacher especial (alumnos en espera de lugar); usa el aula 9 y no cuenta para límites de horas.
- Estatus nuevo **Inactivo** (no trabaja por ahora en la escuela; no aparece en el panel).
- El correo es opcional: sin correo no tiene usuario; al capturarlo se crea y recibe la invitación.

## Horario base (Teachers → editar → Horario base)
Cuadrícula día × hora (horas del catálogo de horarios de la escuela). Tope **48 h por semana** (Lista de espera sin tope).
Las horas por día de la contratación (tiempo completo 8, medio tiempo 4, hora clase hasta 8) se muestran como referencia;
el Excel tiene días de 9 y 10 horas, por eso no bloquean. Al guardar, las semanas armadas desde hoy se recalculan.

## Panel de disponibilidad
Igual a la hoja "H de Teacher": por día una columna por teacher (número y nombre corto), por hora el **número de aula**
con su color (paleta `--color-room-1…9`, como el Excel), horas por día, leyenda (asignadas / a cumplir) y gráfica del
promedio por hora como el Excel (x̄T): aulas asignadas de la semana entre 5, o entre 6 si ese sábado hay aulas a esa
hora (7 con domingo); sin Lista de espera; ancho fijo ~600 px. Clic en el nombre del teacher (columna o leyenda) abre su **horario base** en una
ventana; al guardar se recalculan las semanas armadas desde hoy y se actualiza el panel. Selector de mes y
semana = histórico. Una hora con incidencia que nadie cubre se muestra **A** (ausente) en naranja.
- Cada semana se arma con el botón **Armar semana**: horario base + aulas de la **última semana armada** (aunque se haya
  saltado alguna). El listado de semanas del mes se forma solo; el contenido, solo con el botón.
- El aula se asigna o cambia con un clic en la celda. Un aula no tiene dos teachers a la misma hora (RN-05).
- Cada turno del catálogo tiene sus **días** (lunes a domingo): sus horas solo aparecen esos días en el horario base y
  en el panel. El domingo aparece si la escuela trabaja domingos o tiene un turno activo con domingo. Los días festivos no tienen disponibilidad (RN-22) y su encabezado dice
  "Día festivo: …". Al armar una semana se generan los festivos oficiales del año si faltan. Si se agrega, cambia,
  desactiva o borra un festivo, las semanas ya armadas se recalculan solas (las horas se conservan ocultas con su aula).

## Incidencias
Tipos: Permiso con goce, Permiso sin goce, Vacaciones, Incapacidad, Falta (no llegó), Evento imprevisto y **Cubre horas**.
Fechas, día completo u horas, y **motivo obligatorio**. Las horas con incidencia quedan vacías en el panel (la celda se
conserva oculta con su aula). "Cubre horas" pasa esas horas a quien cubre, en la misma aula; quien cubre debe estar libre
y no pasar de 48 h en la semana (por día sí puede pasar de 8). Al guardar o borrar, el panel se recalcula.
- **Fecha de ingreso** del teacher (Acceso al sistema, obligatoria): aparece en el panel desde ese día; antes no tiene
  columna ni horas (tampoco en semanas anteriores).
- **Baja** y **Reingreso** (una fecha y motivo): cambian el estatus del teacher (Baja con su fecha, sin acceso / Activo).
  El panel decide quién trabaja cada día con esas fechas: antes de la baja conserva sus horas; desde la baja sale y
  desde el reingreso regresa. No se editan; solo se borra la última (el estatus regresa al anterior).

## Tablas
`teacher_schedule_blocks` (horario base), `teacher_incidents`, `availability_weeks` (semanas armadas = histórico),
`teacher_availabilities` (celdas: teacher, fecha, bloque, aula; `is_absent` + `held_classroom_id` para incidencias;
`covered_teacher_id` si cubre). Campos nuevos en `teachers`: `short_name`, `panel_number`, `is_waitlist`.
El catálogo "Salones" se llama ahora **Aulas**.

## Para la agenda (reservaciones)
- La agenda lee directo `teacher_availabilities` (filas con `is_absent = 0` y aula): ahí ya están aplicadas las
  incidencias y los días festivos. No debe volver a consultarlos.
- Al recalcular (incidencia, festivo u horario base) cada hora **conserva su id**: se actualiza, no se borra y se vuelve
  a crear. Las reservaciones se pueden ligar a esa fila.
- Solo se reserva en semanas armadas (RN-27). Según Mi escuela (`SelfBooking`): "No permitir" = solo recepción;
  "Día anterior" = el alumno reserva hasta el día anterior y recepción también puede agendar; "A cualquier hora" = el alumno.
- Si una incidencia quita horas con alumnos reservados, las reservaciones pasan automáticamente a quien cubre, en la
  misma aula (RN-28).
