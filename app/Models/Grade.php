<?php

namespace App\Models;

use App\Enums\GradeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Calificación del Kardex (catálogo con base en Plataforma y copia por escuela): A, B, C (actividades 4–7)
 * y F, la firma de las actividades 1–3 y de los clubs.
 */
#[Fillable(['school_id', 'base_id', 'code', 'name', 'type', 'sort_order', 'is_active'])]
class Grade extends Model
{
    use Auditable, BelongsToSchool;

    /** Base: código => [nombre, tipo]. */
    public const DEFAULTS = [
        'A' => ['Excelente', GradeType::Score],
        'B' => ['Bien', GradeType::Score],
        'C' => ['Suficiente', GradeType::Score],
        'F' => ['Firma', GradeType::Signature],
    ];

    protected function casts(): array
    {
        return [
            'type' => GradeType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Ya usada en el Kardex: solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! KardexEntry::withoutGlobalScope('school')->where('grade_id', $this->id)->exists();
    }

    public function auditLabel(): string
    {
        return $this->code.' · '.$this->name;
    }
}
