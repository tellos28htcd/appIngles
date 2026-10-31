# Agenda (fase 1)

Reservación de horas de **asesoría** y **club**, presenciales o en línea. Las hacen
recepción (Agenda) y el alumno (Mi aprendizaje → Agendar clase / Agendar club).

## Pantallas

| Pantalla | Ruta | Quién |
|---|---|---|
| Agenda del día | `/agenda` (`agenda.index`) | Roles con la opción "Agenda". Los teachers solo consultan. |
| Mi agenda | `/mi-agenda` (`my-agenda.index`) | Alumno ligado a su usuario. |
| Agendar club | `/mi-agenda/clubs` (`my-agenda.clubs`) | Igual que Mi agenda, pero solo muestra clubs. |

- **Calendario semanal** (en Agenda y en Mi agenda): horas × días, como Programación de clubs. Cada celda muestra los lugares libres/cupo de asesoría presencial, en línea y de cada club (abreviatura), "Lista de espera" o "Lleno". Clic en la celda: agendar en ese día y hora. En Agenda, clic en el día: su listado.
- **Agenda del día:** las reservaciones se agrupan por hora y se ordenan por aula → teacher → alumno, con su actividad (LLA) o club.
  - Recepción agenda: elige al alumno y una opción; el aula la asigna el sistema.
  - Desde aquí se marca la asistencia, se cancela con motivo y se pasa a un aula al alumno de la lista de espera cuando se libera un lugar ("Lugares liberados").
- **Mi agenda:** el alumno elige un día (hoy + 6), ve los lugares disponibles y reserva.
  - Ve sus próximas reservaciones con su aula, aula virtual o lista de espera, y la actividad que le toca.
  - Puede cancelar una reservación; el motivo queda como "Aviso para cancelar".

## Mi Instituto → Operación

| Campo | Uso |
|---|---|
| Cupo de alumnos por sesión | Cupo de cada aula en asesoría. Si el aula tiene menos cupo, se usa el del aula. |
| Cupo de alumnos por club | Clubs presenciales. Vacío = el cupo del aula. |
| Alumnos por aula virtual | Asesorías y clubs en línea. |
| Aulas virtuales por horario | Aulas para asesoría en línea a cada hora; 0 = sin asesoría en línea. |

## Reglas

- **Semana armada:** solo se reserva en semanas ya armadas en el panel de disponibilidad.
- **Horas futuras:** solo se reservan horas que todavía no empiezan, en la zona horaria de la escuela.
- **Asesoría presencial:**
  - Se ofrecen las horas del panel con aula, sin club ni actividad de coordinación, y sin la columna "Lista de espera".
  - El aula se asigna **al azar** entre las que tienen lugar.
  - Si todas están llenas y "Agendar en lista de espera" está activo, el alumno entra a la **lista de espera** y lo ve así en Mi agenda.
- **Asesoría en línea:** cupo = aulas virtuales por horario × alumnos por aula virtual, solo a horas en las que hay teachers en el panel. Se asigna "Aula virtual N".
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
  - Se respetan el máximo de asesorías y el máximo de clubs por día de Mi Instituto.
  - **Horas por día según la asistencia de los últimos 7 días** (asistió ÷ (asistió + faltó)):
    - Índice ≥ 0.8, o alumno sin historial: 2 horas.
    - Índice menor: 1 hora.
- **Actividad (LLA):**
  - Cada asesoría lleva la actividad siguiente de la ruta de avance (`App\Support\ProgressPath`): libros por nivel → lecciones en su orden → actividades 1–7 (8 en Check Points).
  - Parte de la última asesoría reservada; si no hay, del punto de inicio de la ficha de inscripción.
  - La 2.ª hora del día toma la actividad que sigue.
- **Motivos de cancelación:**

  | Motivo | Cuenta como |
  |---|---|
  | No se presentó | Falta |
  | Aviso para cancelar | No cuenta |
  | No terminó la actividad | Asistencia |
  | Asistió, decidió cancelar | Asistencia |

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
