# Agenda (fase 1)

Reservación de horas de **práctica** y **club**, presenciales o en línea. Las hacen
recepción (Agenda) y el alumno (Mi aprendizaje → Agendar clase / Agendar club).

## Pantallas

| Pantalla | Ruta | Quién |
|---|---|---|
| Agenda del día | `/agenda` (`agenda.index`) | Roles con la opción "Agenda". Los teachers solo consultan. |
| Mi agenda | `/mi-agenda` (`my-agenda.index`) | Alumno ligado a su usuario. |
| Agendar club | `/mi-agenda/clubs` (`my-agenda.clubs`) | Igual que Mi agenda, pero solo muestra clubs. |

- **Calendario semanal** (en Agenda y en Mi agenda): horas × días, como Programación de clubs. Cada celda muestra los lugares libres/cupo de práctica presencial, en línea y de cada club (abreviatura), "Lista de espera" o "Lleno". Clic en la celda: agendar en ese día y hora. En Agenda, clic en el día: su listado.
- **Agenda del día:** las reservaciones se agrupan por hora y se ordenan por aula → teacher → alumno, con su actividad (LLA) o club.
  - Recepción agenda: elige al alumno y una opción; el aula la asigna el sistema.
  - Desde aquí se marca la asistencia, se cancela con motivo y se pasa a un aula al alumno de la lista de espera cuando se libera un lugar ("Lugares liberados").
- **Mi agenda:** el alumno elige un día (hoy + 6), ve los lugares disponibles y reserva.
  - Ve sus próximas reservaciones con su aula, aula virtual o lista de espera, y la actividad que le toca.
  - Puede cancelar una reservación; el motivo queda como "Aviso para cancelar".

## Mi Instituto → Operación

| Campo | Uso |
|---|---|
| Cupo de alumnos por sesión | Cupo de cada aula en práctica. Si el aula tiene menos cupo, se usa el del aula. |
| Cupo de alumnos por club | Clubs presenciales. Vacío = el cupo del aula. |
| Alumnos por aula virtual | Prácticas y clubs en línea. |
| Aulas virtuales por horario | Aulas para práctica en línea a cada hora; 0 = sin práctica en línea. |

## Reglas

- **Semana armada:** solo se reserva en semanas ya armadas en el panel de disponibilidad.
- **Horas futuras:** solo se reservan horas que todavía no empiezan, en la zona horaria de la escuela.
- **Práctica presencial:**
  - Se ofrecen las horas del panel con aula, sin club ni actividad de coordinación, y sin la columna "Lista de espera".
  - El aula se asigna **al azar** entre las que tienen lugar.
  - Si todas están llenas y "Agendar en lista de espera" está activo, el alumno entra a la **lista de espera** y lo ve así en Mi agenda.
- **Práctica en línea:** cupo = aulas virtuales por horario × alumnos por aula virtual, solo a horas en las que hay teachers en el panel. Se asigna "Aula virtual N".
- **Club:**
  - Se ofrecen los clubs programados.
  - El nivel del alumno debe estar en la abreviatura del club.
  - En el primer libro, el alumno debe ir en la lección mínima para clubs o más adelante.
  - Cupo: por club (presencial) o por aula virtual (en línea).
- **Quién reserva:** recepción siempre puede. El alumno depende de "Reservaciones del alumno":
  - No permitir: no reserva.
  - Día anterior: reserva a partir del día siguiente.
  - A cualquier hora: reserva cualquier hora futura.
- **Por día:**
  - No se permiten dos reservaciones a la misma hora.
  - Se respetan el máximo de prácticas y el máximo de clubs por día de Mi Instituto.
  - **Horas por día según la asistencia de los últimos 7 días** (asistió ÷ (asistió + faltó)):
    - Índice ≥ 0.8, o alumno sin historial: 2 horas.
    - Índice menor: 1 hora.
- **Actividad (LLA):**
  - Cada práctica lleva la actividad siguiente de la ruta de avance (`App\Support\ProgressPath`): libros por nivel → lecciones en su orden → actividades 1–7 (8 en Check Points).
  - Parte de la última práctica reservada; si no hay, del punto de inicio de la ficha de inscripción.
  - La 2.ª hora del día toma la actividad que sigue.
- **Motivos de cancelación:** catálogo con base en Plataforma y copia por escuela (Configuración → Catálogos de alumnos → Motivos de cancelación). Cada motivo dice si cuenta como asistencia, como falta o no cuenta. Base: No se presentó (falta), Aviso para cancelar (no cuenta), No terminó la actividad y Asistió, decidió cancelar (asistencia). Cuando el alumno cancela él mismo se usa el primer motivo activo que no cuenta.

- **Historial:** todo movimiento (reservó, entró a lista de espera, pasó a aula, canceló, asistencia) se guarda en `booking_events` con usuario y hora.

## Tablas

- `bookings`: escuela, alumno, fecha, hora, tipo y modalidad.
  - Lugar: celda del panel o club, teacher, aula, aula virtual o lista de espera.
  - Estado, motivo de cancelación y actividad (libro, lección, número).
  - Si la agendó recepción y quién la hizo.
- `booking_events`: historial de movimientos.

## Pendiente

- Significado de los valores AxU = 0 del catálogo de avance.
- Factor de proyección para la 2.ª hora.
- Límite de tiempo para cancelar.
- Calificaciones A–D y avance automático del Kardex (fase 2).
- Mover las reservaciones al teacher que cubre (RN-28).

## Ajustes (nov-2026)

- **Práctica** (antes "Asesoría"): al elegir la hora se escoge **Presencial** o **En línea**; las opciones son botones (Tab para recorrer, Enter para elegir).
- **En línea / híbrida:** primero un aula virtual del catálogo de Aulas (las marcadas "Virtual" sin club en línea ni reserva a esa hora; "Aulas virtuales por horario" limita cuántas, 0 = todas), cada una con "Alumnos por aula virtual" lugares (o su capacidad). Llenas (o si la escuela tiene 0 aulas virtuales), el alumno entra en línea a la clase de un teacher presencial con lugar ("Aula X (híbrida, en línea)") y ocupa un lugar del cupo por sesión de esa aula. El calendario muestra cuántos van presencial y cuántos en línea.
- **Tolerancia:** recepción puede agendar una hora hasta 10 minutos después de que empezó (si hay cupo, p. ej. por una cancelación); el alumno solo antes de que empiece.
- **Portal del alumno:** una sola opción "Agendar" (prácticas y clubs). "Agendar club" queda desactivada en el menú (su código se conserva).
- **Ventanas:** todas las ventanas de captura se mueven arrastrando su título.
- **Listado del día:** letra más grande; filtros por hora, aula y teacher (se recuerdan en la sesión); cada renglón con el color tenue de su aula (como en el panel).
- **Cambiar reservación** (solo recepción; botón "Cambiar" o clic en el alumno): otra aula disponible a esa hora (con lugar; el teacher es el que da clase en esa aula según el panel; en aula virtual, el que la atiende) y/o pasar el lugar a otro alumno (no puede tener esa hora; respeta su límite por día; se recalcula su actividad). En clubs solo se cambia el alumno. Queda en el historial ("changed").
- **Lista de espera (aula de espera):** cuando presencial y en línea se llenan, se ofrece "Lista de espera" si el panel tiene a esa hora la columna "Lista de espera" (su aula, p. ej. Aula 9), aunque "Agendar en lista de espera" de Mi Instituto esté apagado.
- **Pasar el lugar:** en "Cambiar reservación" solo se puede pasar a un alumno de la lista de espera de ese día y hora; la reservación del alumno que sale se cancela con el motivo elegido (por omisión el que cuenta como falta) y el de la lista toma su aula y teacher.
- **Cambiar actividad:** en "Cambiar reservación" se elige otra opción de la misma hora con lugar (club ↔ práctica, presencial ↔ en línea); el alumno se vuelve a acomodar y libera su lugar anterior.
- **Corregir alumno:** si se reservó al alumno equivocado, se busca y elige el correcto (con sus validaciones). Pasar el lugar a la lista de espera se conserva.
- **Asistencia por error:** doble clic en "Asistió" regresa la reservación a "Reservada".
- "En línea" se muestra en morado en el listado; "+ Nueva reservación" es un botón flotante siempre visible.
- **Listado compacto (nov-2026):** cada renglón en una línea. Sin botones "Cambiar" ni "Asistió": clic en el **aula** abre "Cambiar reservación"; clic en **Reservada** marca Asistió; doble clic en **Asistió** regresa a Reservada. Solo queda "Cancelar".
- **Lista de espera (arriba, en la leyenda):** clic en "Lista de espera" muestra los alumnos en lista de espera del día elegido, con fecha y hora.
- **Teachers excluidos:** las prácticas no se acomodan con los teachers que el alumno excluyó en su expediente (hasta 3); ver docs/modulos/alumnos.md.
- **Coordinador evaluando:** a la fecha y hora de una Evaluación de Club (Horas Coordinador), los teachers con cargo de Coordinador académico no reciben alumnos: sus lugares no cuentan en el calendario, ni al reservar, ni para aula virtual o híbrida, ni en "Cambiar".
