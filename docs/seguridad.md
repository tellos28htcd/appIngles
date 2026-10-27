# Seguridad de AppIngles

Controles implementados y lo que hay que configurar al desplegar en AlmaLinux.
Cada control tiene su prueba automática en `tests/Feature` (sobre todo `SecurityTest`).

## 1. Controles en el sistema

| Riesgo | Control | Dónde |
|---|---|---|
| Inyección SQL | Solo Eloquent / Query Builder con parámetros; nada de SQL armado con datos del usuario | Todo el código |
| XSS (código malicioso en pantallas) | Blade escapa todo con `{{ }}`; no se imprime HTML del usuario con `{!! !!}`; **Content-Security-Policy con nonce**: un `<script>` inyectado no se ejecuta | `SecurityHeaders`, vistas |
| Archivos maliciosos | Logos y fotos solo PNG/JPG/WebP (sin SVG), máx. 1–2 MB; además se **abre el archivo y se verifica que sea una imagen real** (un archivo con código renombrado a .jpg se rechaza aunque el navegador diga que es imagen); se guardan en almacenamiento privado con nombre aleatorio y se sirven solo con permiso y `nosniff` | `RealImage`, `SchoolForm`, `StudentForm`, `TeacherForm` |
| Descargas desde enlaces (SSRF) | Fotos importadas: solo HTTPS, sin usuario ni puerto, sin IP escrita, solo direcciones públicas; la conexión se **fija a la IP validada** (un cambio de DNS no la lleva a la red interna); máx. 3 redirecciones, cada una validada; solo imágenes de hasta 5 MB | `DownloadStudentPhoto` |
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
| Auditoría | Bitácora de accesos (`login_logs`) y de cambios (`audit_logs`), solo inserción, se conservan para siempre; contraseñas y rutas privadas nunca se guardan; consulta en Plataforma y Configuración → Bitácora | Modelos `LoginLog`, `AuditLog`, trait `Auditable` |
| Cabeceras | `nosniff`, `Referrer-Policy`, `Permissions-Policy` (sin cámara/micrófono/ubicación), `COOP`, `CORP same-origin`, `X-Permitted-Cross-Domain-Policies: none`, HSTS en HTTPS | `SecurityHeaders` |
| Código externo | La CSP solo permite scripts propios con nonce (ningún dominio externo ni `unsafe-inline`); una prueba recorre las pantallas y falla si aparece un `<script>` sin nonce o de otro dominio | `SecurityHardeningTest` |
| Pantallas sin sesión | Una prueba recorre **todas** las rutas GET y falla si alguna abre sin iniciar sesión | `SecurityHardeningTest` |
| Sesión por omisión | Si falta la variable en el `.env`, la sesión se cifra igual y la cookie es `Secure` en producción | `config/session.php` |
| Dependencias | `composer audit` y `npm audit` sin vulnerabilidades (revisión del 26-oct-2026: se actualizaron Livewire 4.4.8, Symfony 8.1 y Vite) | `composer.lock`, `package-lock.json` |

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
- Repetir `composer audit` y `npm audit` antes de cada despliegue.
- Autenticación en dos pasos (2FA) para Super Admin y administradores de escuela.
- Alpine en modo CSP estricto (`csp_safe`) para quitar `'unsafe-eval'` de la política.
- Fuentes servidas desde el propio servidor (hoy desde Google Fonts).
- Auditoría de consultas a información sensible del expediente (RN-12) cuando exista el módulo.
