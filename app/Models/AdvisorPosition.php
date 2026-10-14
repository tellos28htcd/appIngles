<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Puesto del asesor de ventas; rank = orden de jerarquía (1 = más alto). */
#[Fillable(['school_id', 'base_id', 'name', 'rank', 'is_active'])]
class AdvisorPosition extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! SalesAdvisor::withoutGlobalScope('school')->where('advisor_position_id', $this->id)->exists();
    }
}
