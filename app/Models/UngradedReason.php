<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Motivo del Kardex (catálogo con base en Plataforma y copia por escuela): por qué una reservación se queda
 * sin calificación o por qué se califica un día después (Revisar, Pendiente, Otro), siempre con comentario.
 */
#[Fillable(['school_id', 'base_id', 'name', 'sort_order', 'is_active'])]
class UngradedReason extends Model
{
    use Auditable, BelongsToSchool;

    public const DEFAULTS = ['Revisar', 'Pendiente', 'Otro'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Ya usado en una reservación o en el Kardex: solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return $this->school_id === null || (! Booking::withoutGlobalScope('school')->where('ungraded_reason_id', $this->id)->exists()
            && ! KardexEntry::withoutGlobalScope('school')->where('late_reason_id', $this->id)->exists());
    }
}
