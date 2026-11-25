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

El cupo de clubs (presenciales y en línea) y de aulas virtuales es la **capacidad de cada aula** en Configuración →
Aulas (sin capacidad, el cupo por sesión). Se ofrecen todas las aulas virtuales con teacher programado a esa hora.
(Los campos "Cupo de alumnos por club", "Alumnos por aula virtual" y "Aulas virtuales por horario" se quitaron el
18-nov-2026.)

## Reglas

- **Semana armada:** solo se reserva en semanas ya armadas en el panel de disponibilidad.
- **Horas futuras:** solo se reservan horas que todavía no empiezan, en la zona horaria de la escuela.
- **Práctica presencial:**
  - Se ofrecen las horas del panel con aula, sin club ni actividad de coordinación, y sin la columna "Lista de espera".
  - El aula la asigna el **balanceo por pesos** (ver abajo): la de menor carga entre las que aceptan al alumno.
  - Si todas están llenas y "Agendar en lista de espera" está activo, el alumno entra a la **lista de espera** y lo ve así en Mi agenda.
- **Práctica en línea:** primero híbrida en un aula física; llenas, un aula virtual del catálogo (ver "Balanceo por pesos").
- **Club:**
  - Se ofrecen los clubs programados.
  - El nivel del alumno debe estar en la abreviatura del club.
  - En el primer libro, el alumno debe ir en la lección mínima para clubs o más adelante.
  - Cupo: la capacidad del aula del club (física o virtual).
- **Dos horas a la vez** (19-nov-2026; ajustado 23-nov): con el botón "Nueva reservación" (recepción) se ven todas
  las horas del día y se marcan **hasta 2 opciones de horas distintas**; con clic en una celda del calendario solo sale esa hora (otra opción de la misma hora reemplaza a la
  anterior). Se reservan juntas: si una no se puede, no se reserva ninguna. Botones: Reservar blanco y Cerrar gris claro.
- **Tipos de reservación** (23-nov-2026; `BookingKind`). En "Nueva reservación" primero se elige **Presencial / En
  línea** y luego el tipo:

  | Tipo | Quién | Lugar | Peso | En línea | Límite | Kardex |
  |---|---|---|---|---|---|---|
  | Práctica | Recepción y alumno | Aula de práctica (balanceo) | Sí | Sí | Horas por día | A/B/C o F, mueve la ruta |
  | Club | Recepción y alumno | Club programado | — | Si el club es en línea | Clubs por día | F |
  | Mentoría especial | Recepción | Un lugar en el aula de práctica | 0 (no se muestra) | Sí | Horas por día y **3 por semana** | No se califica |
  | Entrega de material | Recepción | Un lugar en el aula de práctica | 0 | **No** | Horas por día | No se califica |
  | Presentación 1 a 1 | Recepción, **solo cuando le toca su A7** (lecciones 15 y 17) o tiene una pendiente | Un aula **vacía** que queda solo para el alumno (con su teacher) | 0 | Sí | Horas por día | A/B/C, es su actividad de la ruta |
  | Presentación en club | Recepción, igual que la 1 a 1 | Un lugar del club de esa hora (sin revisar nivel) | — | Si el club es en línea | Clubs por día; **una sola presentación por club** | A/B/C, es su actividad de la ruta |
- **Quién reserva:** recepción siempre puede. El alumno (si "Reservaciones del alumno" no está en "No permitir"):
  - Solo **Práctica y Club**; los demás tipos los pide en Recepción.
  - Solo **a partir del día siguiente** (fijo para todas las escuelas); el mismo día, mensaje de que lo haga con Recepción.
  - Con clic en una celda, esa hora; con su botón **"+ Nueva reservación"**, elige el día y hasta 2 horas.
  - Su calendario no muestra números de cupo; lo lleno sale en tono tenue; puede entrar a lista de espera.
  - Cancela con un **motivo de su menú** (catálogo Motivos de cancelación, casilla "Lo puede elegir el alumno"; no
    cuentan como falta ni asistencia): Por mas tiempo en el trabajo, Por viaje de trabajo, Por estudio, Por enfermedad,
    Por accidente, Por trafico, Por vacaciones, Otra.
  - Arriba ve "Tu siguiente actividad" en grande; ya no ve "Puedes reservar hasta 2 h por día" (Recepción sí).
- **Teacher en la Agenda:** solo ve el listado del día con **sus** reservaciones (sin calendario ni filtro de teacher).
- **Botón "Calificaciones"** en cada reservación de Práctica, Club o Presentación (Teacher, Recepción, Coordinador y
  Administrador): abre la ventana de Kardex → Calificaciones sin salir de la Agenda.
- **Por día:**
  - No se permiten dos reservaciones a la misma hora.
  - Se respetan el máximo de prácticas y el máximo de clubs por día de Mi Instituto.
  - **Horas por día según la asistencia de los últimos 7 días** (asistió ÷ (asistió + faltó)):
    - Índice ≥ 0.8, o alumno sin historial: 2 horas.
    - Índice menor: 1 hora.
    - Solo cuentan las prácticas (práctica, mentoría, entrega de material y presentación 1 a 1); **los clubs no cuentan** (su único tope es el máximo de clubs por día de Mi Instituto, si se llena).
  - **Caso especial** (expediente, 25-nov-2026): "Máximo de horas de práctica" por día (1–12; lo captura quien edita el expediente). Si tiene número, reemplaza la regla de 2/1 horas y el máximo de Mi Instituto; en "Nueva reservación" se eligen a la vez hasta ese número.
- **Presentaciones de la ruta** (25-nov-2026): ver docs/modulos/kardex.md. Cuando la siguiente actividad es presentación, la práctica sale deshabilitada ("Le toca su presentación …") y se agenda como Presentación (1 a 1 presencial o en línea, o en el club de la hora), que lleva esa actividad. En "Nueva reservación" Recepción puede **"Dejar pendiente y continuar"**. El alumno ve el aviso en Agendar y en Mi avance.
- **Actividad (LLA):**
  - Cada práctica lleva la actividad siguiente de la ruta de avance (`App\Support\ProgressPath`): libros por nivel → lecciones en su orden → actividades 1–7 (8 en Check Points).
  - Parte de la última práctica reservada; si no hay, del punto de inicio de la ficha de inscripción.
  - La 2.ª hora del día toma la actividad que sigue.
- **Motivos de cancelación:** catálogo con base en Plataforma y copia por escuela (Configuración → Catálogos académicos → Motivos de cancelación). Cada motivo dice si cuenta como asistencia, como falta o no cuenta. Base: No se presentó (falta), Aviso para cancelar (no cuenta), No terminó la actividad y Asistió, decidió cancelar (asistencia). Cuando el alumno cancela él mismo elige uno de los marcados "Lo puede elegir el alumno".

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
- Calificaciones y avance del Kardex: ver docs/modulos/kardex.md.
- Mover las reservaciones al teacher que cubre (RN-28).

## Ajustes (nov-2026)

- **Práctica** (antes "Asesoría"): al elegir la hora se escoge **Presencial** o **En línea**; las opciones son botones (Tab para recorrer, Enter para elegir).
- **En línea / híbrida (reemplazado por "Balanceo por pesos": ahora primero híbrida y luego virtual):** primero un aula virtual del catálogo de Aulas (las marcadas "Virtual" sin club en línea ni reserva a esa hora), cada una con su capacidad de lugares. Llenas (o si la escuela tiene 0 aulas virtuales), el alumno entra en línea a la clase de un teacher presencial con lugar ("Aula X (híbrida, en línea)") y ocupa un lugar del cupo por sesión de esa aula. El calendario muestra cuántos van presencial y cuántos en línea.
- **Tolerancia:** **Recepción y el Administrador** agendan mientras la clase no termine (p. ej. el alumno ya está en la clase de las 10 y a las 10:40 no estaba en la agenda; 25-nov-2026); el resto del personal, hasta 10 minutos después de que empezó; el alumno solo antes de que empiece.
- **Portal del alumno:** una sola opción "Agendar" (prácticas y clubs). "Agendar club" queda desactivada en el menú (su código se conserva).
- **Ventanas:** todas las ventanas de captura se mueven arrastrando su título.
- **Listado del día:** letra más grande; filtros por hora, aula y teacher (se recuerdan en la sesión); cada renglón con el color tenue de su aula (como en el panel).
- **Cambiar reservación** (solo recepción; botón "Cambiar" o clic en el alumno): otra aula disponible a esa hora (con lugar; el teacher es el que da clase en esa aula según el panel; en aula virtual, el que la atiende) y/o pasar el lugar a otro alumno (no puede tener esa hora; respeta su límite por día; se recalcula su actividad). En clubs solo se cambia el alumno. Queda en el historial ("changed").
  - **Presencial ↔ en línea** (19-nov-2026): la sección "Cambiar presencial / en línea o actividad" muestra la modalidad
    actual ("Ahora: Presencial") y la otra opción de esa hora con lugar, para corregir cuando el alumno o recepción
    eligieron por error. Solo **Recepción y el Administrador de la escuela** (`BookingPolicy::changeActivity`).
- **Lista de espera (aula de espera):** cuando presencial y en línea se llenan, se ofrece "Lista de espera" si el panel tiene a esa hora la columna "Lista de espera" (su aula, p. ej. Aula 9), aunque "Agendar en lista de espera" de Mi Instituto esté apagado.
- **Pasar el lugar:** en "Cambiar reservación" solo se puede pasar a un alumno de la lista de espera de ese día y hora; la reservación del alumno que sale se cancela con el motivo elegido (por omisión el que cuenta como falta) y el de la lista toma su aula y teacher.
- **Cambiar actividad:** en "Cambiar reservación" se elige otra opción de la misma hora con lugar (club ↔ práctica, presencial ↔ en línea); el alumno se vuelve a acomodar y libera su lugar anterior.
- **Corregir alumno:** si se reservó al alumno equivocado, se busca y elige el correcto (con sus validaciones). Pasar el lugar a la lista de espera se conserva.
- **Asistencia por error:** doble clic en "Asistió" regresa la reservación a "Reservada".
- "En línea" se muestra en morado en el listado; "+ Nueva reservación" es un botón flotante siempre visible.
- **Listado compacto (nov-2026):** cada renglón en una línea. Sin botones "Cambiar" ni "Asistió": clic en el **aula** o en el **alumno** abre "Cambiar reservación"; clic en **Reservada** marca Asistió; doble clic en **Asistió** regresa a Reservada. Solo queda "Cancelar".
- **Lista de espera (arriba, en la leyenda):** clic en "Lista de espera" muestra los alumnos en lista de espera del día elegido, con fecha y hora.
- **Teachers excluidos:** las prácticas no se acomodan con los teachers que el alumno excluyó en su expediente (hasta 3); ver docs/modulos/alumnos.md.
- **Coordinador evaluando:** a la fecha y hora de una Evaluación de Club (Horas Coordinador), los teachers con cargo de Coordinador académico no reciben alumnos: sus lugares no cuentan en el calendario, ni al reservar, ni para aula virtual o híbrida, ni en "Cambiar".

## Balanceo por pesos (nov-2026)

- **Pesos newton:** Catálogos académicos → pestaña "Pesos newton". **Tabla general** (como el Excel del instituto): renglón por lección (Lesson 1–7, Check Point C1 (15), Verb in context 1 (16), Lesson 8–14, Check Point C2 (17), Verb in context 2 (18)) × actividades 1–7 (BA, 1P, M, WS, 2P, WP, SP), con T Min (suma de los pesos) y T Hrs (÷ 60). Lo que se cambia aplica a esa lección en los 3 libros. Oscuro = la actividad no se hace en esa lección (según el catálogo de Prácticas). Se guarda en `lessons.activity_weights`. Valores (nov-2026): Lesson BA 4, 1P 7, M 10, WS 9, 2P 15, WP 11, SP 18; Check Point BA 6, M 11, WP 180, SP 50; Verb in context M 7, WP 6, SP 9.
- **Ruta de avance = catálogo de Prácticas** (Excel `Cat_Practicas.xlsx`, tabla `practices`): 336 prácticas (3 libros) con libro, lección, actividad, **orden**, puntos acumulados y nombre propio (A6 del Check Point: DIRECT P. / CON. EXP.). La práctica que sigue se asigna por el **orden**: el Check Point se abre (A1 BA, A3 M), sigue todo el Verb in context (A3 M, A6 WP, A7 SP) y se cierra (A6 DIRECT P./CON. EXP., A7 SP). Sin avance, el alumno empieza en el orden 1 (o en el punto de inicio de su ficha, si lo tiene); la 2.ª práctica del día toma el orden siguiente. El avance es lo más adelantado entre lo calificado en el Kardex y la última práctica reservada (no cancelada ni sin calificación). Nombre de la práctica: `L1-L1-A2-1P`, `L1-CP1-A6-DIRECT P.`, `L1-VIC1-A3-M` (en el listado del día sin la palabra "Práctica"). Se consulta en Catálogos académicos → Prácticas.
- **Carga de un teacher** = suma de los pesos de las actividades de sus alumnos a esa hora, cada una con el peso de **la lección del alumno** (sin pesos, cuenta 1 por alumno). Se busca que las cargas queden parejas (alrededor del promedio).
- **Aulas:**
  - El aula audiovisual (marcada para clubs) nunca recibe prácticas mientras tenga esa marca.
  - Física solo presencial: el cupo por sesión de Mi Instituto.
  - Física híbrida (con alumnos en línea): máximo 3 (sin pasar el cupo por sesión) y a lo más 2 en línea.
  - En línea: primero híbrida en un aula física; llenas, un aula virtual del catálogo.
  - **Los de línea se juntan** (19-nov-2026): el alumno en línea va al aula que ya es híbrida (hasta 2 en línea + 1
    presencial); solo si no cabe se abre otra híbrida, la de menos alumnos. Así las demás aulas físicas conservan su
    cupo presencial completo (p. ej. 2 en línea + 1 presencial en un aula y 5 presenciales en la otra).
  - (nov-2026) Se eliminó la actividad PR y la regla "uno a uno"; también las actividades 8 y 9 (DP, CE).
- **Al agendar:** el alumno va al aula de menor carga que lo acepta; después se **rebalancea** la hora.
- **Rebalanceo** (al agendar, cancelar, pasar de la lista de espera o cambiar de actividad): se mueven alumnos (o se intercambian) solo de aula y teacher, misma fecha y hora: primero para quedar con el menor número de aulas híbridas y luego mientras bajen las diferencias de carga (nunca abre otra híbrida). Físicas e híbridas se balancean juntas; las virtuales entre ellas. Si al agendar o al pasar de la lista de espera no hay lugar, primero se rebalancea la hora y se vuelve a intentar.
  - No se mueve a quien ya asistió, ni a un aula con un teacher que el alumno excluyó en su expediente; se respetan las reglas de aulas.
  - Se deja de balancear **3 minutos antes** de la clase. La tolerancia de 10 minutos de recepción para agendar después de empezar sigue igual.
  - Cada movimiento queda en el historial ("Cambió de aula por balanceo") y el alumno recibe un aviso en su cuenta (ventana al entrar, hasta "Entendido"); en Mi agenda ve su aula nueva.
  - "Cambiar reservación" (manual) no rebalancea; el siguiente rebalanceo de esa hora puede volver a mover al alumno.
- **Código:** `App\Support\RoomBalancer` (reglas de aulas y búsqueda del balance), `AgendaService::placeSession()` y `rebalance()`, aviso `App\Notifications\BookingMoved` y ventana `App\Livewire\BookingNotices`.
- **Listado del día con pesos:** columna "Peso" (peso newton de la actividad en la lección del alumno) y, en el renglón de cada hora, la carga total de cada aula con su teacher. Solo los ven Administrador de escuela y Recepción (`BookingPolicy::viewWeights`). Si un aula física tiene alumnos en línea a esa hora, todos sus renglones dicen "Aula N" y, en letra chica, "(híbrida, presencial)" o "(híbrida, en línea)".
- **Aula virtual con teacher programado:** un aula virtual solo se ofrece (al agendar, en el calendario y en "Cambiar") si en la disponibilidad de horario hay un teacher programado en esa aula a esa hora; él la atiende. Sin teacher programado, el alumno en línea solo puede ir híbrido en un aula física. Ya no se toma a un teacher presencial para atenderla.
- **Aula audiovisual para prácticas:** en Configuración → Aulas, el aula audiovisual tiene el botón "Habilitar para prácticas" (`classrooms.allows_practice`). Habilitada, recibe alumnos de práctica (máximo 5, o el cupo por sesión si es menor), salvo a las horas con un club en esa aula o una reserva para otra actividad.
- **Cancelada:** en el listado su peso se muestra "—" y no cuenta en la carga del aula; la lista de espera muestra su peso en gris (no cuenta hasta tener aula).
- **Horas Coordinador en la agenda:** lo programado en Horas Coordinador aparece en el calendario semanal (en la celda de su día y hora: actividad, club evaluado y teacher; la Evaluación de Club en el pastel de su club) y en el listado del día (bloque "Horas Coordinador"). Solo lo ven Administrador de escuela, Recepción, Teacher y Coordinador académico (`BookingPolicy::viewCoordination`); el alumno no lo ve (Mi agenda no lo muestra).
