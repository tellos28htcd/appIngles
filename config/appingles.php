<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marca de la plataforma
    |--------------------------------------------------------------------------
    | Se usa cuando no hay una escuela (tenant) activa: login, panel del
    | Super Administrador. Los colores se inyectan como tokens --brand-*.
    */

    'brand' => [
        'name' => env('APP_NAME', 'AppIngles'),
        'monogram' => 'Ai',
        'primary' => env('BRAND_PRIMARY', '#4361EE'),
        'accent' => env('BRAND_ACCENT', '#FFC23D'),
    ],

    'timezone' => env('APP_DISPLAY_TIMEZONE', 'America/Mexico_City'),

    /*
    |--------------------------------------------------------------------------
    | Super Administrador inicial
    |--------------------------------------------------------------------------
    | Lo crea SuperAdminSeeder. Sin contraseña en .env, el seeder genera una
    | aleatoria y la muestra una sola vez en consola.
    */

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Administrador AppIngles'),
        'email' => env('SUPER_ADMIN_EMAIL', 'admin@appingles.com'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    'login' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],

];
