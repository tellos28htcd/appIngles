# Módulo: Alumnos (expediente, tutores e importación)

Decisiones del 13-oct-2026 a partir de *IQN4 AA Concentrado Alumnos V5.0-okCLA.xlsx*.

## Tablas (todas con `school_id`)
- `students`: matrícula, nombre(s), apellidos, celular, teléfono fijo, domicilio por partes (calle, núm. ext., núm.
  int., colonia, municipio y estado del INEGI, C.P.; `address` conserva la línea original y `address_needs_review`
  marca los que no se pudieron separar), correo (opcional), fecha de nacimiento, tipo de alumno, origen de la
  información, acepta publicidad, estilo de aprendizaje (visual, auditivo, kinestésico), foto privada, estatus,
  fechas del curso (inicio, baja, graduación), observaciones, `user_id` (nulo mientras no tenga correo).
- `student_enrollments`: ficha de inscripción; un alumno puede tener varias (reinscripción, convenio de extensión).
  Número de contrato, asesor y si aplica comisión, plan y mensualidades, fechas de la ficha y de reinscripción,
  servicios (ampliación de plazo con sus días, Preparación TOEFL, tarjeta dorada), aplica material con su serie y
  tipo (F = Financiero, C = Contable), y libro, lección y actividad de inicio. Las columnas antiguas `starts_on`,
  `ends_on`, `withdrawn_on` y `graduated_on` ya no se usan.
- `student_absences`: solicitudes de ausencia (fecha inicial y final, días naturales) con su motivo; se imprimen.
- `student_status_changes`: histórico de estatus (solo inserción) con fecha, usuario y motivo. Aquí quedan las bajas
  con su motivo (p. ej. "devolvió el material"; antes en la pestaña *Serie reasignada*).
- `guardians` + `guardian_student`: un tutor puede tener varios alumnos de la escuela. No todos los alumnos tienen tutor.
- `student_survey_answers`: respuestas a la encuesta.

Catálogos con base en Plataforma y copia por escuela: `student_plans`, `information_sources`, `student_types`,
`advisor_positions`, `survey_questions` (encuesta editable). `sales_advisors` es solo de cada escuela (incluye
INTERCAMBIO; TARJETA DORADA se descartó como asesor).

## Reglas
- **Matrícula (RN-17):** PREFIJO (4) + año (2) + mes (2) + consecutivo (4), p. ej. `QRON26080506`. Prefijo en
  `schools.enrollment_prefix` (Escuelas, solo Super Admin); consecutivo continuo por escuela en
  `schools.last_enrollment_sequence`. Una matrícula de otra escuela de la franquicia se captura tal cual y no consume
  consecutivo; una propia mayor hace que el siguiente continúe desde ahí. No cambia después del alta.
- **Estatus:** Activo, En pausa, Curso finalizado, Graduado, Baja, Reasignado. Baja, pausa y reasignación piden motivo.
  Solo Activo o En pausa pueden entrar al sistema.
- **Usuario del alumno:** se crea (rol Alumno) cuando tiene correo; **nunca se envía correo al dar de alta ni al
  importar**. El acceso se manda con "Enviar acceso" (lista o expediente).
- **Tutor:** su usuario (rol Padre/Tutor) se crea a mano con "Dar acceso" en Alumnos → Tutores, solo cuando lo solicita.
  Verá únicamente a sus tutorados (portal "Mis alumnos", pendiente).
- Alumno y Padre/Tutor no se asignan desde Usuarios.
- Un alumno con inscripciones no se elimina: se da de baja con su motivo.
- La contraseña de la plataforma externa (columna CAP del Excel) no se importa.
- **Ciclo del programa:** 365 días desde la última reinscripción o, si no hay, desde el inicio del curso.
  Fin del curso = inicio del ciclo + 365 + días de ampliación de plazo (de la ficha vigente). Las ausencias no lo recorren.
- **Solicitud de ausencia:** máximo 60 días naturales por ciclo; el conteo se reinicia con cada reinscripción.
  Datos generales muestra los días del ciclo y el acumulado.
- **Planes:** A = Contado; se quitó "Contado – 1 mensualidad"; se agregaron C 4 y 5 meses, D 7 meses, F 9 y 11 meses.
- Domicilios históricos: `php artisan students:split-addresses [--school=] [--dry-run]` separa la línea en partes
  (solo los que aún no tienen calle); los dudosos quedan marcados "Revisar domicilio" y se limpian al guardar el alumno.

## Permisos
- Alta, edición, cambio de estatus, tutores, importación y envío de acceso: Administrador de escuela y Servicios escolares.
- Consulta: los roles con la opción "Expedientes" (Coordinador académico, Mentor, Recepción).
- Catálogos de alumnos: Administrador de escuela (Configuración) y Super Admin (Catálogos base).

## Importación
1. Alumnos → Importar → *Descargar plantilla* (.xlsx con listas de los catálogos de la escuela, hoja Asesores,
   hoja Valores e Instrucciones; las preguntas de la encuesta van al final).
2. Subir la plantilla llena: revisión por fila (errores = no se importa la fila; avisos = se importa).
3. Importar: se reconoce al alumno por su matrícula (volver a subir actualiza, no duplica). Un correo con problema no
   impide importar: el alumno queda sin correo. Las fotos con enlace https (p. ej. Google Drive público) se descargan en
   segundo plano: requiere `php artisan queue:work` corriendo (en producción, con supervisor).

El Excel histórico de Querétaro Norte se convirtió a la plantilla (`plantilla-alumnos-queretaro-norte.xlsx`).
