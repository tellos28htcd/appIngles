# AppIngles — SaaS multiempresa para academias de inglés

## Contexto
- Análisis general: docs/analisis.md (léelo antes de planear cualquier módulo).
- Diseño aprobado: docs/diseno/ (todo componente y pantalla debe seguirlo).
- Información de cada módulo: docs/modulos/<modulo>.md

## Stack
PHP 8.4+, Laravel, Livewire 4, Tailwind CSS, Alpine.js, MySQL/MariaDB.
Verifica las versiones reales en composer.json y package.json.

## Reglas del proyecto
- Usa el skill laravel-livewire-fullstack en todo el trabajo de código.
- Multi-tenant: una BD con company_id, global scope y policies. Toda tabla
  transaccional lleva company_id. Cada módulo incluye pruebas de aislamiento.
- White-label: colores y marca por tokens, nunca colores fijos en el código.
- Trabaja un módulo a la vez. Antes de programar, haz el análisis corto del
  módulo y espera mi aprobación.
- Textos de interfaz en español (lang/es); código en inglés.
- Despliegue final en AlmaLinux: configuración solo en .env, cuida
  mayúsculas en nombres de archivo.

## Estado
Módulo actual: ninguno (fase de análisis).

<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
