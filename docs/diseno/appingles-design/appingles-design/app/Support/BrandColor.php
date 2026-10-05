<?php

namespace App\Support;

/**
 * Calcula el color de texto legible (blanco o tinta) sobre los colores de marca
 * de cada escuela. Contraste mínimo WCAG 4.5:1.
 */
final class BrandColor
{
    public const INK = '#141826';

    public static function onColor(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $lin = fn (float $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $L = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($L + 0.05) >= 4.5 ? '#FFFFFF' : self::INK;
    }

    /** Los botones primarios usan primary-600 (86 % marca + 14 % negro). */
    public static function onPrimary(string $hex): string
    {
        [$r, $g, $b] = self::rgb($hex);
        $d = fn (int $c) => (int) round($c * 0.86);

        return self::onColor(sprintf('#%02x%02x%02x', $d($r), $d($g), $d($b)));
    }

    /** Para validar en Configuración: primary-700 sobre blanco debe ser >= 4.5. */
    public static function contrastOnWhite(string $hex, float $darken = 0.30): float
    {
        [$r, $g, $b] = self::rgb($hex);
        $k = 1 - $darken;
        $lin = fn (float $c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $L = 0.2126 * $lin($r * $k) + 0.7152 * $lin($g * $k) + 0.0722 * $lin($b * $k);

        return round(1.05 / ($L + 0.05), 2);
    }

    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return array_map('hexdec', str_split($hex, 2));
    }
}
