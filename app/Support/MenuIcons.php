<?php

namespace App\Support;

/**
 * Íconos del sistema de diseño que se pueden asignar a un módulo del menú.
 * Cada clave debe existir en resources/views/components/ui/icon.blade.php.
 */
final class MenuIcons
{
    public const KEYS = [
        'home', 'calendar', 'student', 'users', 'clipboard', 'receipt', 'chart',
        'book', 'chat', 'mail', 'heart', 'building', 'settings', 'sparkles',
    ];

    /** @return array<string, string> clave => nombre legible */
    public static function options(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => __("menu.icons.$key")])->all();
    }
}
