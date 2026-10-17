<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Semana del panel de disponibilidad ya armada (starts_on = lunes). Queda como histórico. */
#[Fillable(['school_id', 'starts_on', 'user_id'])]
class AvailabilityWeek extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
        ];
    }
}
