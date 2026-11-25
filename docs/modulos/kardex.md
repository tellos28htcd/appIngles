# Módulo: Kardex

Decisiones del 18-nov-2026. Primer submódulo: **Calificaciones**.

## Catálogos (Configuración → Catálogos académicos; base en Plataforma y copia por escuela)
- **Calificaciones del Kardex** (`grades`): **A** Excelente, **B** Bien, **C** Suficiente (actividades 4–7) y
  **F** Firma (actividades 1–3 y clubs). La D no existe. Cada letra dice si es firma o calificación (`type`).
- **Motivos del Kardex** (`ungraded_reasons`, 19-nov-2026; reemplazan a Revisar, Pendiente y Otro). Cada motivo
  dice **quién lo usa** (`audience`): el **teacher** (Pendiente de completar, Pendiente de revisar, Repetir,
  Sección A completada, Revisar correcciones, Completar correcciones), el **personal** — Recepción, Administración
  y Coordinación académica — (Planeación de club, Planeación de field trip, Field trip externo, Field trip interno,
  Reporte de Alumno, Junta academica, Capacitacion, Contestar libro, Convivencia, Solicitud de permisos para field
  trip, Compra de material para Field trip) o **ambos** (Material extra). Se eligen como **observación de cada
  práctica al calificar** (opcional, `kardex_entries.observation_reason_id`; para el reporte de seguimiento del
  alumno), al dejar una reservación "Sin calificación" o al calificar días después. El teacher ve los suyos;
  Administración y Coordinación académica, los del personal; **Recepción, los dos** (25-nov-2026). "Ambos" lo ven todos.
- **Actividades** (Catálogos académicos) tiene la columna "Calificación en Kardex" (`activities.grade_type`):
  1–3 firma, 4–7 A/B/C; se puede cambiar por actividad.

## Calificaciones (Kardex → Calificaciones)
- Listado del día como en la Agenda: todos los reservados de cada hora (sin cancelados ni lista de espera).
  **Primero se registra la asistencia** ("Asistió") en la Agenda; sin ella no sale el botón **"Calificaciones"** (ni en
  la Agenda ni aquí) y no se puede calificar, solo dejar "Sin calificación" (23-nov-2026).
  Filtros por hora y teacher (en sesión). El **teacher solo ve a sus alumnos**.
- Clic en el alumno: ventana con su nombre, la práctica reservada y **3 prácticas**: la que indica el Kardex
  (la siguiente sin calificar según el **Orden** del catálogo de Prácticas; sin nada calificado, la de la
  reservación) y las 2 siguientes (si se acaba el libro, siguen las del siguiente). Cada una solo ofrece las letras
  de su tipo. Se guardan solo las calificadas, **en orden y sin huecos**. Al volver a abrir salen las 3 siguientes.
- Club y **Presentación** (1 a 1 o en club, 23-nov-2026): se firman con **F** una sola vez; quedan en el Kardex pero no
  mueven el avance de prácticas. Mentoría especial y Entrega de material no se califican (no salen en el listado).
- En la Agenda, cada reservación calificable tiene el botón **"Calificaciones"** que abre esta misma ventana.
- **Sin calificación**: motivo + comentario en la reservación (`bookings.ungraded_reason_id`, `ungraded_comment`);
  el Kardex no avanza y la práctica se vuelve a asignar.
- **Fechas**: hoy o hasta **4 días antes** (`KardexService::DAYS_BACK`); un día anterior pide motivo y comentario
  (`kardex_entries.late_reason_id`, `late_comment`). No se califica a futuro.
- **Corregir**: solo Coordinador académico y Administrador (cambiar la letra; quitar solo la **última** práctica
  calificada del alumno, para no romper el orden). Ellos pueden ir a días anteriores a los 4 para corregir.
- Si el teacher califica más de lo reservado, las siguientes reservaciones del alumno (sin calificar) se recorren a
  las prácticas que siguen en el Kardex.

## Agenda
La siguiente práctica del alumno es la que sigue a lo más avanzado entre lo **calificado en el Kardex** y lo
**reservado** (sin cancelar ni marcado sin calificación); sin nada, el punto de inicio de su ficha u orden 1.

## Alumno (Mi aprendizaje → Mi avance)
Su Kardex en modo lectura: práctica que sigue, puntos acumulados (los del catálogo de la última calificada),
prácticas calificadas de la ruta y la lista de calificaciones con fecha y teacher.

## Acceso
Teacher (solo sus alumnos), Recepción, Coordinador académico y Administrador. Tablas con `school_id` y pruebas de
aislamiento (`tests/Feature/Kardex/KardexGradesTest.php`).

## Pendiente
Kardex del alumno visto por el personal (desde el expediente), reportes de calificaciones y alertas de bajo
rendimiento (RN-07).
