<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Plan de pago de la inscripción (clave A–F, X, contado) y su número de mensualidades. */
#[Fillable(['school_id', 'base_id', 'code', 'name', 'installments', 'is_active'])]
class StudentPlan extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'installments' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! StudentEnrollment::withoutGlobalScope('school')->where('student_plan_id', $this->id)->exists();
    }
}
