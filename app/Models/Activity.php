<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Actividad / código de evaluación (BA, 1P, M, WS, 2P, WP, SP, PR). */
#[Fillable(['school_id', 'base_id', 'number', 'code', 'description', 'minutes', 'is_one_to_one', 'is_active'])]
class Activity extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'minutes' => 'integer',
            'is_one_to_one' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Códigos de la escuela por número de actividad (se consulta una sola vez por petición).
     *
     * @return array<int, string> número => código (BA, 1P, M…)
     */
    public static function codesFor(int $schoolId): array
    {
        return once(fn (): array => self::withoutGlobalScope('school')->where('school_id', $schoolId)->pluck('code', 'number')->all());
    }

    /**
     * Números de actividad "uno a uno" de la escuela (PR, presentación: el teacher no recibe a nadie más).
     *
     * @return list<int>
     */
    public static function oneToOneFor(int $schoolId): array
    {
        return once(fn (): array => self::withoutGlobalScope('school')->where('school_id', $schoolId)->where('is_active', true)
            ->where('is_one_to_one', true)->pluck('number')->map(fn ($number) => (int) $number)->values()->all());
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
