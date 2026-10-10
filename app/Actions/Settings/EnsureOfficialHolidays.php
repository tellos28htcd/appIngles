<?php

namespace App\Actions\Settings;

use App\Enums\HolidayType;
use App\Models\AuditLog;
use App\Models\Holiday;
use App\Models\School;
use App\Support\OfficialHolidays;

/**
 * Agrega a la escuela los festivos oficiales del año que aún no tiene.
 * Nunca reactiva los que la escuela desactivó.
 */
final class EnsureOfficialHolidays
{
    public function handle(School $school, int $year): int
    {
        $existing = Holiday::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('type', HolidayType::Official)
            ->inYear($year)
            ->pluck('official_key')
            ->all();

        $created = 0;

        AuditLog::muted(function () use ($school, $year, $existing, &$created): void {
            foreach (OfficialHolidays::forYear($year) as $holiday) {
                if (in_array($holiday['key'], $existing, true)) {
                    continue;
                }

                Holiday::withoutGlobalScope('school')->create([
                    'school_id' => $school->id,
                    'starts_on' => $holiday['date'],
                    'ends_on' => $holiday['date'],
                    'name' => $holiday['name'],
                    'type' => HolidayType::Official,
                    'official_key' => $holiday['key'],
                    'is_active' => true,
                ]);
                $created++;
            }
        });

        return $created;
    }
}
