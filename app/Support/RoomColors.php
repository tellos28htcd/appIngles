<?php

namespace App\Support;

/**
 * Colores del panel de disponibilidad: cada aula (y cada número de teacher en
 * la leyenda) tiene su color, como en el Excel. Tokens --color-room-1…9 en
 * app.css; después del 9 se repiten. Las clases van completas para que
 * Tailwind las detecte.
 */
final class RoomColors
{
    private const CLASSES = [
        1 => 'bg-room-1 text-white',
        2 => 'bg-room-2 text-white',
        3 => 'bg-room-3 text-white',
        4 => 'bg-room-4 text-white',
        5 => 'bg-room-5 text-white',
        6 => 'bg-room-6 text-ink-900',
        7 => 'bg-room-7 text-white',
        8 => 'bg-room-8 text-white',
        9 => 'bg-room-9 text-white',
    ];

    public static function for(int $number): string
    {
        return self::CLASSES[(($number - 1) % count(self::CLASSES)) + 1];
    }
}
