# Módulo: Menú (Plataforma)

Decisiones del 5-oct-2026. Solo el **Administrador plataforma**.

## Tablas
- `menu_items`: el menú en sí (módulos y submódulos): nombre, ícono, pantalla (`route_name`), orden,
  `status` (activo / Próximamente, lo define el código), `is_enabled` (lo decide el Super Admin) e `is_system` (viene del código).
- `menu_item_role`: qué rol ve cada opción. Se administra en **Roles y permisos**; al crear una opción aquí se pueden elegir sus roles.

## Funciones
1. **Agregar** módulos (con ícono del sistema de diseño) y submódulos (solo dentro de módulos que no tienen pantalla propia).
   Sin pantalla programada aparecen como "Próximamente"; si ya existe una pantalla sin opción en el menú, se puede enlazar.
2. **Renombrar y ordenar** módulos y submódulos.
3. **Desactivar por completo**: la opción (y, si es módulo, todos sus submódulos) desaparece del menú de todos, incluido el
   Super Admin, y sus pantallas responden 403. Se puede reactivar.
4. **Eliminar** solo opciones agregadas desde esta pantalla y sin submódulos.

## Reglas
- Inicio, Plataforma y Menú no se desactivan ni eliminan (evita que el Super Admin pierda el acceso).
- Los seeders nunca sobrescriben nombre, orden, desactivación ni opciones agregadas en pantalla.
- Todo cambio queda en `audit_logs`.
