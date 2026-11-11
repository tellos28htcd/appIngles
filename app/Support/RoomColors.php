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

    /** Fondo tenue con franja del color del aula (renglones de la agenda: se lee el texto encima). */
    private const TINTS = [
        1 => 'bg-room-1/10 border-l-4 border-l-room-1',
        2 => 'bg-room-2/10 border-l-4 border-l-room-2',
        3 => 'bg-room-3/10 border-l-4 border-l-room-3',
        4 => 'bg-room-4/10 border-l-4 border-l-room-4',
        5 => 'bg-room-5/10 border-l-4 border-l-room-5',
        6 => 'bg-room-6/10 border-l-4 border-l-room-6',
        7 => 'bg-room-7/10 border-l-4 border-l-room-7',
        8 => 'bg-room-8/10 border-l-4 border-l-room-8',
        9 => 'bg-room-9/10 border-l-4 border-l-room-9',
    ];

    /** Pastel del mismo tono (p. ej. la Evaluación de Club en Horas Coordinador con el color de su club). */
    private const PASTELS = [
        1 => 'bg-room-1/30 ring-1 ring-room-1/40',
        2 => 'bg-room-2/30 ring-1 ring-room-2/40',
        3 => 'bg-room-3/30 ring-1 ring-room-3/40',
        4 => 'bg-room-4/30 ring-1 ring-room-4/40',
        5 => 'bg-room-5/30 ring-1 ring-room-5/40',
        6 => 'bg-room-6/30 ring-1 ring-room-6/40',
        7 => 'bg-room-7/30 ring-1 ring-room-7/40',
        8 => 'bg-room-8/30 ring-1 ring-room-8/40',
        9 => 'bg-room-9/30 ring-1 ring-room-9/40',
    ];

    public static function for(int $number): string
    {
        return self::CLASSES[(($number - 1) % count(self::CLASSES)) + 1];
    }

    public static function tint(int $number): string
    {
        return self::TINTS[(($number - 1) % count(self::TINTS)) + 1];
    }

    public static function pastel(int $number): string
    {
        return self::PASTELS[(($number - 1) % count(self::PASTELS)) + 1];
    }
}
