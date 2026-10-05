# Módulo: Roles y permisos (Plataforma)

Decisiones del 5-oct-2026.

## Actores
- **Administrador plataforma**: único que administra roles, permisos y menú.

## Funciones
1. **Catálogo de roles**: los 8 roles base (no se eliminan) y roles de escuela personalizados. Nombre, descripción, estado, usuarios y opciones asignadas.
2. **Asignación de módulos por rol**: el administrador marca los módulos y submódulos de cada rol. Eso define el menú del rol y las rutas que puede abrir (RN de acceso). Marcar un módulo marca todos sus submódulos. "Inicio" siempre está incluido.
3. **Roles nuevos**: siempre de escuela (`custom_*`); se eliminan solo si no tienen usuarios.
4. **Menú**: nombre y orden de cada opción. Ícono, ruta y si está activo o "Próximamente" los define el código.

## Reglas
- Los permisos son **iguales para todas las escuelas**.
- El rol *Administrador plataforma* tiene acceso total por código: no se edita, no se desactiva, no se elimina.
- Un rol desactivado impide el inicio de sesión de sus usuarios.
- Todo cambio de datos, permisos o menú queda en `audit_logs` (quién, cuándo, antes y después).
- Los seeders nunca sobrescriben lo editado en pantalla: solo crean lo que falta y asignan opciones nuevas.
