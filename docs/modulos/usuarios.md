# Módulo: Usuarios

Fuente: solicitud del 5-oct-2026, `Información general.xlsx` (*Catálogo Usuarios* y *Datos de Usuario*).

## Actores
- **Administrador plataforma**: ve y administra usuarios de todas las escuelas.
- **Administrador escuela**: ve y administra solo los usuarios de su escuela.

## Historias de usuario
1. Como administrador quiero listar usuarios y filtrarlos por escuela (solo Super Admin), rol y estado, con búsqueda y registros por página.
2. Como administrador quiero dar de alta un usuario (nombre(s), apellidos, correo, rol, escuela, observaciones) y que reciba un correo para crear su contraseña.
3. Como administrador quiero editarlo, activarlo o desactivarlo, y reenviar la invitación si no la usó.
4. Como administrador quiero eliminar un usuario que no tiene registros en ningún módulo.

## Reglas
- RN-16: solo el rol *Administrador plataforma* va sin escuela; cualquier otro rol exige exactamente una escuela. Correo único en toda la plataforma.
- RN-24: eliminar solo si no tiene registros en ningún módulo (iniciar sesión no cuenta); si no, desactivar.
- RN-25: nadie captura contraseñas; enlace por correo, válido 72 horas, reenviable.
- El administrador de escuela no puede ver, crear ni asignar el rol de plataforma, ni tocar usuarios de otra escuela.
- Nadie puede eliminarse ni desactivarse a sí mismo, ni cambiar su propio rol.
- No se puede eliminar ni desactivar al último Administrador plataforma activo.

## Datos (tabla `users`)
`school_id` (nulo solo para plataforma), `role_id`, `first_name`, `last_name`, `second_last_name`, `name` (nombre completo, para búsqueda), `email` (único), `notes`, `status`, `last_login_at`, `password` (nulo hasta que el usuario la crea).
