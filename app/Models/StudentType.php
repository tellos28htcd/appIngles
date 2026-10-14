<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Tipo de alumno: nuevo, traspaso, colaborador, reasignado… */
#[Fillable(['school_id', 'base_id', 'code', 'name', 'is_active'])]
class StudentType extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! Student::withoutGlobalScope('school')->where('student_type_id', $this->id)->exists();
    }
}
