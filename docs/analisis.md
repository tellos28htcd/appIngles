# Análisis general — Plataforma SaaS multiempresa para academias de inglés

> Documento de contexto para Claude Code. Describe el sistema completo a nivel general.
> Cada módulo se detallará después con su propio documento (`docs/modulos/<modulo>.md`).
> **Regla de trabajo:** no programar ningún módulo hasta recibir su información particular y confirmar el análisis con el responsable del proyecto.

---

## 1. Cómo usar este documento

1. Léelo completo y resume lo que entendiste.
2. Lista dudas, contradicciones y huecos (ya hay algunos en la sección 12; añade los que detectes).
3. Propón el modelo de datos general y confirma el orden de módulos (sección 11).
4. **No escribas código** hasta que el responsable apruebe el análisis.
5. Cuando llegue la información de un módulo, haz primero su análisis corto (historias de usuario, reglas de negocio, tablas) y espera aprobación; después migraciones, modelos, componentes, pruebas.

Los textos de interfaz van en español (archivos `lang/es`); el código (clases, tablas, columnas, rutas) va en inglés.

---

## 2. Contexto y objetivo

Sistema web **SaaS multiempresa**: una única instancia desplegada atiende a múltiples escuelas de inglés, academias o profesores independientes (cada una es un *tenant*). El primer cliente identificado es **IQ English**, pero el sistema **no debe tener nada específico de ese cliente fijo en el código**: nombre, logotipo y colores son configuración del tenant.

Objetivos del sistema:

- Registrar la información de alumnos y sus procesos (expediente digital que sustituye el Excel actual).
- Consultar datos en tiempo real.
- Generar reportes e indicadores automáticos.
- Enviar notificaciones automáticas.
- Coordinar el trabajo de teachers, administrativos y padres/tutores.
- Diseño **modular**: debe poder crecer con módulos futuros (estadísticas avanzadas, notificaciones personalizadas, etc.).

### Modelo de operación de la escuela (regla de negocio central)

- Cada **grupo (sesión de clase)** tiene un cupo **configurable por escuela, con un máximo de 6 alumnos** (valor por defecto sugerido: 5).
- Cada alumno debe terminar el curso en **1 año**; si ya tiene conocimiento del idioma, **avanza de nivel**. Si se cumple el año sin terminar, se firma un **convenio de extensión con pago previo**.
- Un teacher da clase a todos los alumnos del grupo, y **cada uno puede tener un nivel distinto** (básico, medio, avanzado). Por eso se lleva un **registro individual** por alumno.
- **Operación diaria:** cada día se crean los grupos con su horario, salón y teacher asignado. La **recepción anota en la agenda las reservaciones** de los alumnos en esas sesiones; esa agenda es la fuente de verdad. **No hay grupos fijos permanentes**: el alumno reserva sesiones.
- Las sesiones tienen **duración variable** (por ejemplo 1 h, 1.5 h o 2 h) y puede haber clase **cualquier día, incluido el domingo**, según las reservaciones.

---

## 3. Stack técnico (decidido)

| Capa | Tecnología |
| --- | --- |
| Lenguaje | PHP 8.4+ |
| Framework | Laravel |
| Interfaz reactiva | Livewire 4 |
| Estilos | Tailwind CSS |
| Interacciones de cliente | Alpine.js (viene incluido con Livewire; no instalarlo aparte) |
| Base de datos | **MariaDB** (local: WAMP, MariaDB 11.5) |

- La propuesta original menciona JavaScript, jQuery, HTML5 y PHP; **el stack de esta tabla es el vigente** y reemplaza esa mención. No usar jQuery.
- Verificar siempre las versiones reales en `composer.json` y `package.json`, y consultar la documentación oficial si hay duda sobre Livewire 4.
- Navegadores: Chrome, Firefox, Safari, Edge y móviles. **Mobile-first**, sobre todo para alumnos y teachers.
- Entorno: desarrollo en local; despliegue final en servidor **Linux AlmaLinux**. Evitar rutas o configuración fija en el código (todo en `.env`), cuidar mayúsculas/minúsculas en nombres de archivo y llevar registro de las extensiones PHP requeridas.

---

## 4. Arquitectura multi-tenant

**Estrategia (según la propuesta): una sola base de datos con campo discriminador `school_id` (tenant) en las tablas transaccionales.** La tabla de tenants es `schools` (una escuela = un plantel).

- Reforzar con **Global Scopes** de Eloquent (por ejemplo, un trait `BelongsToSchool` que aplique el filtro y asigne `school_id` al crear) y con **Policies** para garantizar por código que una escuela nunca acceda a datos de otra.
- Los datos de cada escuela (alumnos, profesores, calificaciones, finanzas) están estrictamente aislados.
- Cada escuela configura su identidad visual (white-label): logotipo, colores corporativos y, opcionalmente, subdominio o dominio personalizado (ej. `tuacademia.dominio.com`).
- **Una cuenta por academia.** No se modelan sucursales: si una academia tiene otro plantel, se registra como **una cuenta (tenant) nueva**.
- **Usuarios por academia.** Cada academia es independiente: un usuario pertenece a una sola escuela. Si un teacher trabaja en dos academias, cada una lo da de alta por separado. El correo es único **dentro de cada escuela**, no de forma global.
- **Moneda y zona horaria por escuela**, con valores definitivos de inicio: **MXN** y **America/Mexico_City**. Las fechas se guardan en UTC y se muestran en la zona horaria de la escuela.
- Las actualizaciones se despliegan de forma centralizada para todos los tenants.
- El Super Administrador es el único que puede operar fuera del alcance de un tenant; hacerlo de forma explícita y auditable (nunca desactivando el scope por accidente).
- Pruebas obligatorias de aislamiento: un usuario de la empresa A no debe poder leer ni modificar registros de la empresa B por ninguna ruta (listados, búsquedas, descargas de documentos, reportes, rutas con ID directo).

---

## 5. Roles y niveles de acceso

Tres niveles operativos:

### 5.1 Super Administrador (dueño del SaaS)
- Control global de la plataforma.
- **Por ahora hay un solo plan.** La gestión de varios planes (ej. Básico, Pro, Enterprise), las pasarelas de pago y la facturación recurrente quedan para más adelante; el modelo debe permitir agregarlas sin reescribir.
- Monitoreo de uso de recursos y métricas globales.
- Alta, habilitación y suspensión de escuelas (tenants).

### 5.2 Administrador de la escuela (tenant admin)
- Director o dueño de cada academia.
- Configura su identidad visual.
- Gestiona su personal (alta de profesores, recepcionistas), turnos y reportes financieros de su escuela.
- Configura salones, catálogos y usuarios.

### 5.3 Usuarios finales
Roles mencionados en la propuesta: **administrador, recepción, administración, teacher, alumno, finanzas** (y asistente en seguridad del expediente).

- **Teacher:** asistencia, calificaciones/evaluaciones, planificación de clases, comunicación con alumnos. **Solo ve alumnos de sus grupos.**
- **Alumno:** horarios, avance, materiales, pagos de colegiaturas, calificaciones, reservación de clases.
- **Padres/tutores:** pueden ver el progreso de su hijo y los pagos pendientes, "si lo requieren" (por definir cómo se modelan; ver sección 12).
- **Recepción:** puede marcar asistencia y reservar clases en lugar del alumno.

El administrador de la escuela ve todos los usuarios y toda la información de su escuela. Los permisos finos por rol se definirán en el módulo de usuarios.

---

## 6. Ciclo de vida del negocio SaaS

1. **Alta de empresa (onboarding):** **la escuela no se registra por su cuenta.** El Super Administrador de AppIngles registra la escuela con sus datos y genera su usuario administrador. A partir de ahí, la escuela hace su configuración (marca, salones, catálogos) y da de alta a sus propios usuarios.
2. **Suscripciones y cobranza del SaaS:** renovaciones automatizadas, suspensión temporal por falta de pago y avisos preventivos a los administradores de cada academia.
3. Capacidad de referencia mencionada en la propuesta: **múltiples empresas, hasta ~200 personas por instituto** (confirmar si es un límite por plan).

---

## 7. Módulos funcionales

### 7.1 Alta de empresa en el SaaS
Registro de tenants (solo lo hace el Super Administrador; no hay autorregistro), usuario administrador inicial, plan (por ahora uno solo), estado (activa/suspendida), identidad visual, moneda y zona horaria (MXN / America/Mexico_City por defecto). En su etapa de configuración, la escuela define además su **cupo de alumnos por sesión** (máx. 6).

### 7.2 Control de usuarios y catálogos
- Alta de usuarios con roles y permisos restringidos según responsabilidad; monitoreo y mantenimiento del sistema.
- **Catálogos** con la información básica para operar (niveles, salones, métodos de pago, conceptos de cargo, códigos de evaluación, **días festivos / sin clase**, etc.).

### 7.3 Alumnos – Expediente (base del sistema)
Registro único y centralizado de cada estudiante.

- **Matrícula:** formato `AI-{ID escuela}-{año}-{consecutivo de 4 dígitos}` (ej. `AI-12-2026-0147`); el consecutivo se reinicia cada año y es independiente por escuela.
- **Contenido:** datos personales (nombre, edad, teléfono, email, domicilio); inscripción (fecha de ingreso, nivel, fecha límite del curso de 1 año y convenios de extensión); contactos familiares (padres, apoderados, emergencia); pagos (cuotas, saldo, historial); progreso académico (calificaciones, asistencias, evaluaciones); notas del maestro; estatus; documentos (contratos, evaluación inicial, fotos).
- **Estatus:** activo, pausado, egresado, baja.
- **Funciones:** crear/editar alumno; cambio de estatus; **histórico de cambios** (quién y cuándo modificó qué); búsqueda tolerante a faltas de ortografía; filtros por nivel, grupo, tutor asignado, estado de pago y fecha de inscripción; vistas de alumnos activos, con pagos pendientes y próximos a terminar nivel.
- **Información de grupos (sesiones):** alumnos de la sesión (cupo configurable, máx. 6), distribución de niveles (ej. 2 básico, 2 medio, 1 avanzado), lugares disponibles, maestro y horario.
- **Reportes automáticos:** altas y bajas por mes, ingresos mensuales por cobranza, retención (finalizaron vs. se fueron), indicadores clave (alumnos activos, nivel promedio, ingreso total).
- **Seguridad:** acceso por rol, teachers limitados a sus grupos, **auditoría de quién vio qué información**, datos protegidos/cifrados. **Toda** la auditoría (cambios y consultas) **se guarda para siempre**, sin purga.

### 7.4 Seguimiento – avance y asistencia
Control diario de cada clase: quién vino, cómo le fue y en qué lección/nivel va.

- **Asistencia** en tiempo real desde celular, tablet o computadora (Presente / Ausente), para grupos de hasta 6 (según el cupo de la escuela).
- **Evaluación por clase** con códigos fijos (ver sección 8).
- **Seguimiento automático de niveles:** calcula en qué lección va cada alumno, indica si está listo para subir de nivel y avisa cuándo debe hacerlo.
- **Alertas automáticas:** más de 3 inasistencias → notificar al admin; acumulación de códigos `WP` o `PR` → sugerir acciones; identificar abandono en riesgo.
- **Reportes:** a alumnos/tutores (asistencia de la semana, desempeño, observaciones del teacher, por email, WhatsApp o portal); a administración (asistencia consolidada por grupo, alumnos en riesgo, listos para cambio de nivel, estadísticas de progresión).
- **Historial completo del alumno:** clases tomadas, con qué teacher, evaluaciones, tiempo en cada nivel y fechas exactas de cambio de nivel.
- Trabaja en conjunto con el módulo de Expediente.

### 7.5 Aprovechamientos – clases, actividades y pagos
Control de clases y cobranza.

- **Registro de clases y actividades:** cada clase tomada queda registrada (fecha, hora, teacher, grupo, nivel); actividades extra (talleres, eventos, tutorías); histórico por alumno.
- **Pagos:** fecha, monto, método y concepto; métodos: efectivo, transferencia, tarjeta crédito/débito, cheque; **recibos digitales automáticos**.
- **Estado de cuenta:** saldo (deuda o crédito), desglose (mensualidades, actividades extra, otros cargos), historial.
- **Cálculo automático de deuda:** monto adeudado, mensualidades atrasadas, días de atraso, proyección del siguiente mes.
- **Alertas de cobranza:** aviso 3 días antes del vencimiento; alerta inmediata al admin por pago vencido; escalamiento: **5 días de atraso → naranja, 10 días → rojo, 20 días → riesgo de cancelación de inscripción**.
- **Gestiones de cobranza (bitácora):** registro de cada gestión por alumno: recordatorios por WhatsApp o correo (enviados y programados), llamadas y su resultado, notas. **Interruptor por alumno** para activar o desactivar los recordatorios automáticos. Se muestra como línea de tiempo en el estado de cuenta (ver diseño).
- **Convenio de extensión:** si el alumno cumple el año sin terminar, se registra un convenio de extensión, que requiere un pago previo.
- **Reportes:** alumno (estado de cuenta, próximo vencimiento, método recomendado, historial de 6 meses); administración (cartera, morosos por antigüedad, ingresos del mes, proyección, tasa de morosidad).

### 7.6 Agenda – planificación y reservación de clases
Calendario que asegura que cada alumno tenga lugar con un maestro y salón disponibles en un horario que le funcione.

- **La agenda es el corazón de la operación diaria:** la recepción va anotando en ella las reservaciones de sesiones de los alumnos. No hay grupos fijos permanentes.
- **Sesiones de clase:** cada día se crean las sesiones (fecha, hora de inicio y fin, salón, teacher asignado y cupo de la sesión: por defecto el de la escuela, puede ser menor, **mínimo 1** (ej. clase privada), **máximo el de la escuela** (≤ 6)); ej. "Lunes 9:00–10:30 – Salón A – Teacher María – 5 lugares". La **duración es variable** (1 h, 1.5 h, 2 h…). Puede haber sesiones **cualquier día de la semana, incluido el domingo**. Se pueden ofrecer plantillas o copiar sesiones de otro día para agilizar la captura. Control de clases simultáneas por salón.
- **Disponibilidad:** vista de calendario; filtros por nivel, maestro y horario; indicador verde (hay lugares) / naranja (casi lleno) / rojo (lleno); notificación cuando se libera un lugar a quienes habían preguntado.
- **Reservación:** el alumno entra, ve horarios filtrados por su nivel, elige y confirma; **o la recepción lo hace por él** (configurable por escuela). El sistema asigna salón y maestro, registra al alumno y envía confirmación por email.
- **Cambios y cancelaciones:** cambio de horario (valida disponibilidad, libera lugar anterior, asigna el nuevo, notifica a maestro y admin); cancelación (libera lugar, lo ofrece a la **lista de espera**, el admin puede reasignar).
- **Control de maestros y salones:** disponibilidad, carga de trabajo; el sistema **impide** asignar dos clases al mismo maestro a la misma hora o usar un salón en dos clases simultáneas.
- **Reportes de ocupación:** ocupación por horario, horarios de baja demanda, carga por maestro, tendencias de demanda por nivel.
- **Asistencia en tiempo real** desde el salón, con evaluación rápida (códigos) al mismo tiempo. La propuesta indica "sincronización automática (no requiere conexión permanente)"; ver sección 12.

### 7.7 Módulos futuros (fuera del alcance inicial)
Estadísticas avanzadas, notificaciones personalizadas. El sistema debe poder agregarlos sin reescribir lo existente.

---

## 8. Glosario y reglas de negocio

### Códigos de evaluación de desempeño por clase (catálogo fijo)

| Código | Significado | Descripción |
| --- | --- | --- |
| 1P | Una participación | Participó una vez en la clase |
| 2P | Dos participaciones | Participó dos veces |
| M | Excelente / Mastery | Domina el tema |
| WS | Well Supported | Necesita apoyo, pero va por buen camino |
| SP | Satisfactory Performance | Desempeño satisfactorio |
| WP | Weak Performance | Desempeño débil, necesita refuerzo |
| PR | Pendiente de revisión | Necesita sesión adicional antes de avanzar |

### Reglas de negocio identificadas

| ID | Regla |
| --- | --- |
| RN-01 | El cupo de una sesión es configurable por escuela, con un máximo absoluto de 6 alumnos. |
| RN-02 | Cada alumno tiene registro individual de nivel y progreso, aunque comparta grupo con alumnos de otro nivel. |
| RN-03 | Un alumno debe completar el curso en 1 año; si ya domina el idioma, puede avanzar de nivel. Al cumplirse el año sin terminar, solo puede continuar con un convenio de extensión con pago previo. |
| RN-04 | Un maestro no puede tener dos clases al mismo tiempo. |
| RN-05 | Un salón no puede usarse en dos clases simultáneas. |
| RN-06 | Más de 3 inasistencias de un alumno notifican al administrador. |
| RN-07 | Acumular códigos `WP` o `PR` genera sugerencia de acciones. |
| RN-08 | Aviso de pago 3 días antes del vencimiento; vencido: alerta inmediata al admin. |
| RN-09 | Escalamiento de cobranza: 5 días atraso = naranja; 10 = rojo; 20 = riesgo de cancelación de inscripción. |
| RN-10 | El teacher solo ve alumnos de sus grupos (los alumnos reservados en las sesiones que tiene asignadas). |
| RN-11 | Ningún tenant puede acceder a datos de otro tenant. |
| RN-12 | Todo cambio en el expediente queda en histórico (quién, cuándo, qué); las consultas de información sensible se auditan. La auditoría se conserva para siempre. |
| RN-13 | Una escuela suspendida por falta de pago pierde acceso según la política que se defina (ver sección 12). |
| RN-14 | Las sesiones se crean por día (fecha, horario de duración variable, salón, teacher) y la recepción registra en ellas las reservaciones. No hay grupos fijos permanentes. Puede haber sesiones cualquier día, incluido el domingo. |
| RN-15 | Cada academia es una cuenta (tenant) independiente; una sucursal es una cuenta nueva. |
| RN-16 | Solo el Super Administrador no tiene escuela; todo otro usuario pertenece a **una sola** escuela. El correo es **único en toda la plataforma**: la misma persona en dos academias tiene dos usuarios con correos distintos. El login **solo pide correo y contraseña**; la escuela se resuelve internamente a partir del usuario, sin pantalla para elegirla. |
| RN-17 | Matrícula del alumno: `AI-{ID escuela}-{año}-{consecutivo 0000}`, con consecutivo anual por escuela. |
| RN-18 | Solo el Super Administrador da de alta escuelas y su usuario administrador inicial; no hay autorregistro. Por ahora existe un solo plan. |
| RN-19 | Moneda y zona horaria por escuela; valores de inicio MXN y America/Mexico_City. |
| RN-20 | Cada gestión de cobranza (recordatorio, llamada, nota) queda en una bitácora por alumno; los recordatorios automáticos se pueden desactivar por alumno. |
| RN-21 | Cada escuela define su cupo de alumnos por sesión durante su configuración inicial (máximo 6). Cada sesión puede tener un cupo menor al de la escuela, con un **mínimo de 1** (ej. clase privada): `1 ≤ cupo de la sesión ≤ cupo de la escuela ≤ 6`. |
| RN-22 | Los días del catálogo de festivos de la escuela no admiten sesiones y no cuentan para el cálculo de asistencia. |
| RN-23 | La leyenda "Con tecnología de AppIngles" se muestra siempre, junto con el logo de la escuela. |
| RN-24 | Un usuario solo se puede **eliminar** si no tiene registros en ningún módulo (haber iniciado sesión no cuenta); si los tiene, solo se puede desactivar. |
| RN-25 | Al dar de alta un usuario nadie captura su contraseña: el sistema le envía por correo un enlace para crearla. |
| RN-26 | Domicilio de la escuela con entidad federativa y municipio del catálogo oficial del INEGI. |

Niveles de idioma: la propuesta menciona niveles **CEFR (A1–C2)** para el control de niveles, y también usa "básico / medio / avanzado" para los grupos. Hay que definir cómo se relacionan (ver sección 12).

---

## 9. Requerimientos no funcionales

- **Seguridad:** autenticación robusta, autorización por Policies en cada acción (incluidas las acciones de Livewire), datos personales y de menores protegidos, documentos adjuntos en almacenamiento privado con acceso controlado por tenant y rol.
- **Notificaciones:** correo y WhatsApp mediante **colas** (queues) para recordatorios y avisos masivos.
- **Rendimiento:** sin N+1, paginación en listados, índices en columnas de búsqueda y filtro (incluyendo `school_id`).
- **Disponibilidad de información:** consulta en tiempo real, accesible desde cualquier dispositivo.
- **Escalabilidad:** sin límite fijo de usuarios y registros (depende de la capacidad del servidor).
- **Auditoría** de cambios y de consultas a información sensible.
- **Facturación del SaaS:** diseño preparado para pasarelas de pago y cobro recurrente (la pasarela concreta se define después).

---

## 10. Convenciones para el desarrollo

- Seguir el skill `laravel-livewire-fullstack` y el `CLAUDE.md` del proyecto.
- Toda tabla transaccional lleva `school_id` con llave foránea e índice; los modelos usan el trait/scope de tenant.
- Estados como enums de PHP; dinero en `decimal` (nunca float); fechas en UTC con presentación en zona horaria configurable por escuela.
- Migraciones reversibles, factories y seeders por módulo, pruebas de feature por módulo (incluyendo aislamiento entre tenants).
- Commits pequeños por módulo o sub-tarea; cada entrega explica cómo probarla.
- Configuración solo por `.env`; cuidar compatibilidad con AlmaLinux (mayúsculas en nombres de archivo, permisos de `storage`, extensiones PHP).

---

## 11. Orden de desarrollo propuesto

Cada módulo depende de los anteriores:

1. **Base del proyecto:** instalación, conexión a BD, layout, componentes de diseño, tema white-label.
2. **Autenticación (login).**
3. **Empresas (tenants):** modelo multi-tenant, scope global, alta de empresa, identidad visual.
4. **Usuarios, roles y permisos**, ligados a la empresa.
5. **Catálogos** (niveles, salones, métodos de pago, conceptos, códigos de evaluación, días festivos).
6. **Alumnos – Expediente.**
7. **Agenda** (sesiones diarias, reservaciones): antes que seguimiento, porque la asistencia se registra sobre sesiones.
8. **Seguimiento** (asistencia, evaluaciones, niveles, alertas).
9. **Aprovechamientos y pagos** (cargos, pagos, estado de cuenta, cobranza).
10. **Notificaciones y reportes consolidados.**
11. **Suscripciones y cobranza del SaaS** (planes, pasarela, suspensión).

Este orden es una propuesta: valídalo y ajústalo en el análisis inicial.

---

## 12. Dudas y puntos por definir (confirmar con el responsable antes de diseñar)

1. **Padres/tutores:** ¿tienen usuario propio con acceso al portal, o solo reciben avisos por correo/WhatsApp? ¿Un tutor puede tener varios hijos?
2. **Alumno menor de edad:** ¿qué datos del tutor son obligatorios?
3. **Roles:** la propuesta menciona tres niveles y a la vez seis roles (administrador, recepción, administración, teacher, alumno, finanzas). ¿Se fijan roles base o cada escuela define permisos propios?
4. **Niveles:** ¿cómo se relacionan básico/medio/avanzado con CEFR A1–C2? ¿Cuántas lecciones tiene cada nivel? ¿Los niveles y lecciones son catálogo por escuela o globales?
5. **Códigos de evaluación:** ¿cuáles cuentan para aprobar una lección o avanzar de nivel? ¿Qué combinación de códigos dispara "listo para subir de nivel"? ¿`1P` y `2P` se combinan con otros códigos en una misma clase? ¿Son fijos o editables por escuela?
6. **Asistencia:** aparece tanto en Seguimiento como en Agenda; definir una sola fuente de datos (se propone que viva sobre las sesiones de clase).
7. **Modo sin conexión:** la propuesta dice "no requiere conexión permanente". ¿Es requisito real (PWA con sincronización diferida) o basta con que funcione bien en móvil? Cambia bastante el diseño.
8. **Reservación:** ¿la hace el alumno, la recepción o ambos, y se configura por escuela? ¿Hay reglas (anticipación mínima, límite de cancelaciones)?
9. **Pagos:** ¿conciliación con pagos en línea (tarjeta) o solo registro manual? ¿Recargos por mora? ¿Cancelación automática a los 20 días o solo alerta?
10. **Comprobantes:** los "recibos digitales" ¿tienen validez fiscal (factura/CFDI) o son internos?
11. **Pasarela de pago del SaaS:** ¿cuál? ¿Planes con límites (alumnos, teachers, almacenamiento)?
12. **Suspensión de escuela:** ¿acceso de solo lectura, bloqueo total o periodo de gracia?
13. **Dominio personalizado:** ¿subdominio por escuela desde el inicio o más adelante?
14. **WhatsApp:** ¿API oficial (Cloud API), proveedor intermedio o enlace manual?
15. **Documentos adjuntos:** tipos permitidos, tamaño máximo y cuota por escuela.
16. **Idiomas de la interfaz:** ¿solo español o también inglés (alumnos)?
17. **Datos existentes:** ¿hay Excel de la escuela que haya que importar?
18. **Límite de "200 personas por instituto":** ¿es un límite del plan o una estimación? *(Por ahora hay un solo plan; ver RN-18.)*
19. **Meta de ingresos mensual:** el dashboard del diseño muestra "% de lo esperado". ¿Se calcula con los cargos programados del mes o con una meta que captura la escuela? **⏳ Pendiente: el responsable lo verificará con el cliente.**
20. **Pausa y plazo de 1 año:** ¿pausar al alumno congela el plazo del año? ¿El convenio de extensión es por un plazo fijo o variable, y con qué monto? **⏳ Pendiente: el responsable lo verificará con el cliente.**
21. ~~**Festivos**~~ → **Resuelto:** se agrega un catálogo de días festivos / sin clase por escuela (ver §12.1).
22. ~~**Leyenda "Con tecnología de AppIngles"**~~ → **Resuelto:** se muestra siempre, junto con el logo de la escuela (ver §12.1).
23. ~~**Cupo**~~ → **Resuelto:** cada escuela define su cupo durante su configuración; cada sesión puede tener menos lugares, mínimo 1 (ver §12.1 y RN-21).

---

## 12.1 Decisiones confirmadas (5 de octubre de 2026)

| Tema | Decisión |
| --- | --- |
| Grupos y agenda | Cada día se crean las sesiones (horario, salón, teacher) y la recepción anota las reservaciones; no hay grupos fijos permanentes. |
| Cupo | Cada escuela determina su cupo en su etapa de configuración (máximo 6); el sistema no impone un valor inicial. Cada sesión puede tener menos lugares que el cupo de la escuela, con un mínimo de 1 (ej. clase privada). |
| Festivos | Catálogo de días festivos / sin clase por escuela. Esos días no se agendan sesiones y no cuentan para el % de asistencia. |
| Leyenda de plataforma | "Con tecnología de AppIngles" aparece siempre, en todas las escuelas, y el logo de la escuela también se muestra siempre (login, barra lateral/superior). |
| Días de clase | Cualquier día, incluido el domingo, según las reservaciones. |
| Duración de sesión | Variable. |
| Regla de 1 año | Al cumplirse, convenio de extensión con pago previo. |
| Sucursales | No se modelan; cada sucursal es una cuenta nueva. |
| Usuarios | Por academia; la misma persona en dos academias tiene dos usuarios. |
| Matrícula | `AI-{ID escuela}-{año}-{consecutivo 0000}`. |
| Teacher asignado | Lo define la agenda: la recepción registra las reservaciones de sesiones. |
| Gestiones de cobranza | Se incluyen en el análisis (bitácora + interruptor de recordatorios automáticos). |
| Auditoría | Se guarda todo, para siempre. |
| Planes y alta de escuelas | Un solo plan; AppIngles registra la escuela y su administrador; la escuela configura y da de alta a sus usuarios. |
| Moneda y zona horaria | MXN y America/Mexico_City, definitivos como valores de inicio. |
| Base de datos | MariaDB (local: root sin contraseña, `localhost:3306`). |
| Rama principal | `main`. |

**Impacto en el diseño:** el diseño muestra cupo `n/5` y los umbrales del semáforo de cupo (0–3 verde, 4 naranja, 5 lleno). Con el cupo configurable, los umbrales se calculan a partir del cupo de cada sesión (lleno = cupo; último lugar = cupo − 1). El expediente del diseño muestra "Grupo: Lun/Mié 18:00"; con la operación por sesiones, ese dato se interpretará como el horario habitual o la próxima sesión reservada (se define en el módulo Expediente).

---

## 13. Fuera de este documento

La propuesta comercial original incluye costos, calendario de pagos, plan de trabajo en quincenas y condiciones del proyecto. **No se incluyen aquí** porque no son requerimientos técnicos. El plan de trabajo original es tentativo y sujeto a ajuste tras el análisis.
