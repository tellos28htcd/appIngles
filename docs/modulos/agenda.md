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
- **En línea / híbrida:** primero un aula virtual (Aulas virtuales por horario × Alumnos por aula virtual). Llenas (o si la escuela tiene 0 aulas virtuales), el alumno entra en línea a la clase de un teacher presencial con lugar ("Aula X (híbrida, en línea)") y ocupa un lugar del cupo por sesión de esa aula. El calendario muestra cuántos van presencial y cuántos en línea.
- **Tolerancia:** recepción puede agendar una hora hasta 10 minutos después de que empezó (si hay cupo, p. ej. por una cancelación); el alumno solo antes de que empiece.
- **Portal del alumno:** una sola opción "Agendar" (prácticas y clubs). "Agendar club" queda desactivada en el menú (su código se conserva).
- **Ventanas:** todas las ventanas de captura se mueven arrastrando su título.
