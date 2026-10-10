<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Salón / aula del plantel. Su capacidad es física; el cupo de una sesión es el menor entre ambos. */
#[Fillable(['school_id', 'base_id', 'name', 'capacity', 'description', 'is_active'])]
class Classroom extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Se podrá eliminar mientras ninguna sesión de Agenda lo use. */
    public function canBeDeleted(): bool
    {
        return true;
    }
}
