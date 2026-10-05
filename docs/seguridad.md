# Seguridad de AppIngles

Controles implementados y lo que hay que configurar al desplegar en AlmaLinux.
Cada control tiene su prueba automática en `tests/Feature` (sobre todo `SecurityTest`).

## 1. Controles en el sistema

| Riesgo | Control | Dónde |
|---|---|---|
| Inyección SQL | Solo Eloquent / Query Builder con parámetros; nada de SQL armado con datos del usuario | Todo el código |
| XSS (código malicioso en pantallas) | Blade escapa todo con `{{ }}`; no se imprime HTML del usuario con `{!! !!}`; **Content-Security-Policy con nonce**: un `<script>` inyectado no se ejecuta | `SecurityHeaders`, vistas |
| Archivos maliciosos | Logos solo PNG/JPG/WebP (sin SVG), máx. 1 MB, validados por contenido y guardados con nombre aleatorio en minúsculas | `SchoolForm` |
| CSRF (acciones falsificadas desde otro sitio) | Token CSRF en todo formulario y petición de Livewire (`PreventRequestForgery`) | Grupo `web` |
| Clickjacking | `X-Frame-Options: DENY` y `frame-ancestors 'none'` | `SecurityHeaders` |
| Robo de sesión | Sesión **cifrada** en BD, cookie `HttpOnly` + `SameSite=Lax` (+ `Secure` en producción), ID regenerado al iniciar sesión e invalidado al salir | `.env`, `config/session.php` |
| Sesiones viejas tras cambio de contraseña | `auth.session`: al cambiar la contraseña se cierran las sesiones en otros dispositivos | `routes/web.php` |
| Fuerza bruta | 5 intentos de login por correo+IP por minuto; recuperación de contraseña limitada; 300 peticiones/min por usuario | `AuthenticateUser`, `AppServiceProvider` |
| Contraseñas débiles | Mín. 10 caracteres con mayúsculas, minúsculas y números; en producción se rechazan contraseñas filtradas (Have I Been Pwned, solo se envía un prefijo del hash) | `Password::defaults()` |
| Contraseñas conocidas por terceros | Nadie captura contraseñas: el usuario la crea desde un enlace de un solo uso (72 h) | `UserInvitation` |
| Acceso entre escuelas (tenants) | Todo listado y acción filtra por la escuela del usuario; un ID ajeno responde 404/403 | `User::scopeVisibleTo`, Policies |
| Escalamiento de privilegios | Policies en **cada acción** de Livewire (no solo al abrir la pantalla); nadie cambia su propio rol; el admin de escuela no asigna el rol de plataforma | Policies, `UserForm` |
| Manipulación de datos en el navegador | IDs sensibles con `#[Locked]`; Livewire firma el estado del componente; las acciones vuelven a buscar el registro con su permiso | Componentes |
| Asignación masiva | `#[Fillable]` en modelos; en desarrollo falla si llega un campo no permitido | Modelos, `AppServiceProvider` |
| Rutas por URL directa | Middleware `menu.access`: un rol sin el módulo asignado recibe 403 en todas las rutas del módulo | `EnsureMenuAccess` |
| Usuarios desactivados / escuela suspendida | No pueden entrar; si estaban dentro, se cierra su sesión en la siguiente petición | `EnsureUserIsActive` |
| Auditoría | Bitácora de accesos (`login_logs`) y de cambios (`audit_logs`), solo inserción, se conservan para siempre | Modelos `LoginLog`, `AuditLog` |
| Cabeceras | `nosniff`, `Referrer-Policy`, `Permissions-Policy` (sin cámara/micrófono/ubicación), `COOP`, HSTS en HTTPS | `SecurityHeaders` |

## 2. Obligatorio al desplegar en producción (`.env`)

```
APP_ENV=production
APP_DEBUG=false            # nunca true: mostraría código y datos
APP_URL=https://…           # siempre HTTPS
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
LOG_LEVEL=warning
```

Y en el servidor:
- **HTTPS** con certificado válido (Let's Encrypt) y redirección de HTTP a HTTPS.
- El *document root* del servidor web apunta **solo** a `public/`; `.env`, `storage/` y `vendor/` nunca accesibles por URL.
- `composer install --no-dev --optimize-autoloader` (sin herramientas de desarrollo como Boost) y `npm run build`.
- `php artisan config:cache route:cache view:cache`.
- Permisos: el usuario del servidor web solo escribe en `storage/` y `bootstrap/cache/`.
- Usuario de base de datos propio con permisos solo sobre la BD de AppIngles (nunca `root`), contraseña robusta, MariaDB sin acceso desde fuera del servidor.
- Firewall (`firewalld`): solo puertos 80/443 (y SSH restringido).
- Respaldos automáticos de la BD y de `storage/app`, cifrados y probados.
- Mantener PHP, MariaDB y dependencias actualizadas (`composer audit`, `npm audit`).

## 3. Pendiente / siguientes pasos
- Autenticación en dos pasos (2FA) para Super Admin y administradores de escuela.
- Alpine en modo CSP estricto (`csp_safe`) para quitar `'unsafe-eval'` de la política.
- Fuentes servidas desde el propio servidor (hoy desde Google Fonts).
- Auditoría de consultas a información sensible del expediente (RN-12) cuando exista el módulo.
