# Convenciones de código

## Estilo
- PSR-12 con Laravel Pint (`./vendor/bin/pint`).
- Nombres de código en inglés (clases, métodos, tablas, columnas); textos de interfaz y mensajes en español, usando archivos de idioma (`lang/es`) para facilitar cambios.

## Estructura sugerida
```
app/
  Actions/        # una acción de negocio por clase (CreateInvoice, ApprovePayment)
  Enums/          # estados y tipos
  Livewire/       # componentes agrupados por módulo
  Models/
  Policies/
  Services/       # integraciones externas
resources/views/
  components/     # componentes Blade reutilizables
  livewire/       # vistas de componentes, por módulo
database/
  migrations/ factories/ seeders/
tests/
  Feature/ Unit/
```
Si Livewire 4 del proyecto usa otra organización de componentes, seguir la del proyecto.

## Nombres
- Tablas en plural snake_case; modelos en singular PascalCase; llaves foráneas `modelo_id`.
- Componentes Livewire por módulo y acción: `Invoices/Index`, `Invoices/Form`.
- Rutas con nombre: `invoices.index`, `invoices.create`.

## Mayúsculas y minúsculas (despliegue en Linux / AlmaLinux)

En Windows y macOS el sistema de archivos ignora mayúsculas; en Linux **no**: `Logo.png` y `logo.png` son archivos distintos. Un error que no aparece en local rompe producción. Por eso:

**Regla general: todo va en minúsculas**, sin espacios ni acentos ni `ñ`:
- Vistas Blade y componentes: kebab-case (`resources/views/livewire/students/student-form.blade.php`, `components/ui/button.blade.php`).
- Assets: CSS, JS, imágenes, fuentes e íconos (`public/images/logo-default.svg`).
- Archivos de idioma y configuración (`lang/es/students.php`, `config/tenancy.php`).
- Migraciones, seeders de datos y archivos SQL.
- URLs y segmentos de ruta (`/alumnos/expediente`), nombres de ruta (`students.index`).
- Base de datos: nombre de la BD, tablas, columnas, índices y llaves en snake_case minúscula. MariaDB en Linux distingue mayúsculas en nombres de tabla (`lower_case_table_names=0`).
- Archivos subidos por usuarios: guardar siempre con nombre generado en minúsculas (`$file->hashName()`) y extensión en minúscula; el nombre original va en una columna, nunca en la ruta.
- Carpetas de `storage/` y discos (`storage/app/private/companies/{id}/documents`).
- Variables de `.env` en MAYÚSCULAS (es convención de entorno, no archivo); sus valores de ruta en minúsculas.

**Única excepción obligatoria (PSR-4):** las clases PHP y las carpetas que forman su namespace van en PascalCase y deben coincidir **exactamente** con el nombre de la clase (`app/Models/Student.php` → `App\Models\Student`, `app/Livewire/Students/Index.php`). Ponerlas en minúsculas rompe el autoload de Composer en Linux. Aplica a `app/`, `tests/`, `database/factories/` y `database/seeders/`.

**Referencias con el mismo caso exacto:** `view('livewire.students.index')`, `asset('images/logo.svg')`, `@vite`, `use App\Models\Student` deben escribirse idénticos al archivo real.

**Verificación antes de entregar:**
- `composer dump-autoload --optimize --strict-psr` no debe reportar clases mal nombradas.
- `git config core.ignorecase false` en el repositorio, para que Git detecte cambios solo de mayúsculas.
- Para renombrar solo el caso de un archivo usar `git mv archivo.php Archivo.php` (en Windows un renombrado normal no se registra).

## Git y entrega
- Commits pequeños y descriptivos (convencional: `feat:`, `fix:`, `refactor:`).
- Cada entrega incluye cómo probarla.
