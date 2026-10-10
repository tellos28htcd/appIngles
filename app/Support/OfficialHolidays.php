<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Días de descanso obligatorio en México (Ley Federal del Trabajo, art. 74).
 * Las fechas "lunes" cambian cada año, por eso se calculan.
 */
final class OfficialHolidays
{
    /**
     * @return list<array{key: string, date: Carbon, name: string}>
     */
    public static function forYear(int $year): array
    {
        $holidays = [
            ['new_year', Carbon::create($year, 1, 1)],
            ['constitution', self::nthMonday($year, 2, 1)],
            ['benito_juarez', self::nthMonday($year, 3, 3)],
            ['labor_day', Carbon::create($year, 5, 1)],
            ['independence', Carbon::create($year, 9, 16)],
            ['revolution', self::nthMonday($year, 11, 3)],
            ['christmas', Carbon::create($year, 12, 25)],
        ];

        // Transmisión del Poder Ejecutivo Federal: 1 de octubre cada seis años (2024, 2030…).
        if ($year >= 2024 && ($year - 2024) % 6 === 0) {
            $holidays[] = ['executive_transition', Carbon::create($year, 10, 1)];
        }

        usort($holidays, fn (array $a, array $b) => $a[1] <=> $b[1]);

        return array_map(fn (array $holiday) => [
            'key' => $holiday[0],
            'date' => $holiday[1]->startOfDay(),
            'name' => __("settings.official_holidays.{$holiday[0]}"),
        ], $holidays);
    }

    private static function nthMonday(int $year, int $month, int $nth): Carbon
    {
        $first = Carbon::create($year, $month, 1);
        $firstMonday = $first->isMonday() ? $first : $first->copy()->next(Carbon::MONDAY);

        return $firstMonday->addWeeks($nth - 1);
    }
}
