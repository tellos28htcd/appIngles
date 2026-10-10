# Módulo: Bitácora / Auditoría

Decisiones del 9-oct-2026. Solo consulta: nadie edita ni borra la bitácora y se conserva para siempre (RN-12).

## Pantallas
- **Plataforma → Bitácora** (`/bitacora`): solo el Super Administrador. Todas las escuelas y la plataforma; filtro por escuela.
- **Configuración → Bitácora** (`/configuracion/bitacora`): el Administrador de escuela (otro rol solo si se le asigna en
  Roles y permisos). Siempre y solo su escuela. Si entra el Super Admin, se le envía a la de Plataforma.

Cada una con dos pestañas:
1. **Cambios** (`audit_logs`): fecha, usuario, módulo, acción, registro. Filtros: búsqueda (usuario, correo o registro),
   módulo, acción y fechas. El detalle muestra campo / antes / después.
2. **Accesos** (`login_logs`): inicios de sesión correctos, contraseña incorrecta, usuario o escuela inactivos, bloqueo por
   intentos y cierres de sesión. Filtros: búsqueda (correo o nombre), evento y fechas.

Por defecto se muestran los últimos 30 días. **Exportar CSV** descarga lo filtrado (abre en Excel con acentos).

## Qué se registra
- Altas, cambios y bajas de: usuarios, escuelas, teachers, roles y permisos, menú, catálogos académicos y de configuración.
- Eventos: activación/desactivación, cambio de permisos, copia de catálogos, invitación enviada.
- La escuela de cada evento es la del registro afectado (para una escuela, ella misma); lo de la plataforma queda sin escuela
  y no lo ven las escuelas. Lo que el Super Admin cambie dentro de una escuela sí lo ve esa escuela.

## Reglas
- Nunca se guardan contraseñas, tokens ni la ruta privada de la foto: solo que cambiaron ("(protegido)").
- No se registran el último acceso (ya está en Accesos) ni los consecutivos de folio.
- Las copias masivas de catálogos se registran como un solo evento.
- Pendiente: auditoría de **consultas** a información sensible (quién vio un expediente) junto con el módulo Alumnos.
