# Módulo: Escuelas (tenants)

Fuente: `Información general.xlsx` (pestañas *Configuración Generales* y *Configuración del Plantel*) + diseño white-label + decisiones del 5-oct-2026.

## Actores
- **Administrador plataforma**: único que da de alta, edita, activa y suspende escuelas (RN-18).

## Historias de usuario
1. Como Super Admin quiero dar de alta una escuela con sus datos del plantel, domicilio, datos fiscales y folios iniciales.
2. Como Super Admin quiero configurar la operación del plantel (domingos, aulas, clubes, reservación propia, sesiones máximas).
3. Como Super Admin quiero definir su identidad visual (logo, color primario y de acento) y ver una advertencia si el color no tiene contraste suficiente.
4. Como Super Admin quiero listar, buscar, activar y suspender escuelas.

## Datos (tabla `schools`)
| Grupo | Campo (Excel) | Columna |
|---|---|---|
| Plantel | Clave del Instituto | `code` (único) |
| | Descripción | `name` |
| Domicilio | Calle, Num. Ext., Num. Int., Colonia, Código postal | `street`, `exterior_number`, `interior_number`, `neighborhood`, `postal_code` |
| | Entidad federativa / Localidad | `state_id` → `states`, `municipality_id` → `municipalities` (catálogo INEGI) |
| Contacto y fiscal | Teléfono, RFC, Razón social | `phone`, `rfc`, `legal_name` |
| Folios | Último folio serie A (fiscalizado) / serie B (no fiscalizado) | `last_folio_series_a`, `last_folio_series_b` |
| Marca | Color (+ diseño) | `brand_primary`, `brand_accent`, `logo_path` |
| Operación | Cupo por sesión (análisis RN-21), zona horaria, moneda | `session_capacity` (1–6), `timezone`, `currency` |
| Configuración del plantel | Trabaja los días domingo | `works_sundays` |
| | Programar horario para las aulas | `schedules_classrooms` |
| | Agendar sin asignar un aula | `books_without_classroom` |
| | Agendar clubes híbridos | `hybrid_clubs` |
| | El alumno requiere progreso para acceder a los contenidos | `requires_progress` |
| | Permitir que los alumnos reserven sus propias sesiones | `self_booking`: no permitir / solo 24 h antes / a cualquier hora |
| | ¿Las sesiones máximas se aplican por…? | `max_sessions_scope`: plantel / alumno |
| | Cantidad máxima de sesiones por alumno | `max_sessions` |
| | Si una actividad agendada no se aprueba | `failed_activity_policy`: eliminar / recorrer |
| Estado | — | `status`: activa / suspendida |

**Pendiente para el módulo de Catálogos:** "¿A partir de cuál lección el alumno puede participar en clubes?" necesita la tabla de lecciones; se agrega ahí como `club_min_lesson_id`.

## Reglas
- Clave única en la plataforma; RFC con formato válido (12 o 13 caracteres) cuando se captura.
- El municipio debe pertenecer a la entidad elegida.
- Color en hexadecimal; aviso (no bloqueo) si el contraste de primary-700 sobre blanco es < 4.5:1.
- Logo PNG/JPG/SVG/WebP, máx. 1 MB, guardado con nombre generado en minúsculas.

## Dudas abiertas
- Opciones de reservación: el Excel dice "antes de las 20:00 del día anterior / 2 h antes" y la imagen del sistema actual "solo 24 h antes / a cualquier hora". Se implementaron las de la imagen.
- "Cantidad máxima de sesiones por alumno": ¿por día, por semana o simultáneas?
