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
  servicios (Preparación TOEFL, tarjeta dorada; la ampliación de plazo pasó a movimientos del plazo), aplica material con su serie y
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
- Un alumno se elimina (con su ficha, usuario y encuesta) solo mientras no tenga historial: ausencias, movimientos
  del plazo o cambios de estatus además del alta (y, con Cobranza, pagos). Con historial se da de baja con su motivo.
- El usuario de un alumno o tutor no se elimina desde Usuarios: depende de su expediente.
- La contraseña de la plataforma externa (columna CAP del Excel) no se importa.
- **Plazo del curso (acumulado):** fin = fecha base + 365 + días de los movimientos del plazo + días de ausencia.
  La fecha base es la fecha de contrato o la de entrega de material de la primera ficha, según Mi escuela
  (`schools.course_end_from`); con "entrega de material" esa fecha es obligatoria en la ficha. Movimientos
  (`student_term_movements`): reinscripción (+365, se registra sola desde la fecha de reinscripción de la ficha),
  extensión de tiempo (hasta 120 días cada una), ajuste de días (solo suma) y mensualidad abierta (días estimados;
  al cerrarla con la fecha real se cuentan los días reales; Cobranza cobra mientras siga abierta). Ej.: 01/01/2026 →
  01/01/2027 → +120 = 01/05/2027 → +7 = 08/05/2027 → reinscripción +365 = 07/05/2028. Inicio del curso (Datos
  generales) se captura como referencia.
- **Solicitud de ausencia:** máximo 60 días naturales por ciclo (se suman al fin del curso); el conteo se reinicia en la fecha de cada reinscripción.
  Datos generales muestra los días del ciclo y el acumulado.
- **Ficha (cobro):** fecha de cobro (primer cobro; Cobranza calcula las demás mensualidades el mismo día de cada mes),
  razón social (a nombre de quién se factura) y cuenta bancaria de pago (Configuración → Cuentas bancarias, `bank_accounts`).
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
