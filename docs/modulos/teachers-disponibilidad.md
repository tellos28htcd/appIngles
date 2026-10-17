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
promedio de teachers por hora de lunes a viernes (x̄T). Selector de mes y semana = histórico.
- La primera semana se arma con el horario base; las siguientes, con las aulas de la semana anterior.
- El aula se asigna o cambia con un clic en la celda. Un aula no tiene dos teachers a la misma hora (RN-05).
- Domingo solo si la escuela trabaja domingos. Los días festivos no tienen disponibilidad (RN-22).

## Incidencias
Tipos: Permiso con goce, Permiso sin goce, Vacaciones, Incapacidad, Falta (no llegó), Evento imprevisto y **Cubre horas**.
Fechas, día completo u horas, y **motivo obligatorio**. Las horas con incidencia quedan vacías en el panel (la celda se
conserva oculta con su aula). "Cubre horas" pasa esas horas a quien cubre, en la misma aula; quien cubre debe estar libre
y no pasar de 48 h en la semana (por día sí puede pasar de 8). Al guardar o borrar, el panel se recalcula.

## Tablas
`teacher_schedule_blocks` (horario base), `teacher_incidents`, `availability_weeks` (semanas armadas = histórico),
`teacher_availabilities` (celdas: teacher, fecha, bloque, aula; `is_absent` + `held_classroom_id` para incidencias;
`covered_teacher_id` si cubre). Campos nuevos en `teachers`: `short_name`, `panel_number`, `is_waitlist`.
El catálogo "Salones" se llama ahora **Aulas**.
