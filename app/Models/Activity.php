<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Actividad / código de evaluación (BA, 1P, M, WS, 2P, WP, SP, PR). */
#[Fillable(['school_id', 'base_id', 'number', 'code', 'description', 'minutes', 'is_active'])]
class Activity extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'minutes' => 'integer',
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

    public function canBeDeleted(): bool
    {
        return true;
    }
}
