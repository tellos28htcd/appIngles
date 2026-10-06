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

    public function canBeDeleted(): bool
    {
        return true;
    }
}
