<?php

namespace App\Models;

use App\Enums\AttendanceEffect;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Motivo de cancelación de una reservación de la agenda (catálogo con base en Plataforma
 * y copia por escuela). "attendance" dice si cuenta como asistencia, falta o no cuenta.
 */
#[Fillable(['school_id', 'base_id', 'name', 'attendance', 'sort_order', 'is_active'])]
class CancellationReason extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'attendance' => AttendanceEffect::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Ya usado en una reservación: solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return $this->school_id === null || ! Booking::withoutGlobalScope('school')->where('cancellation_reason_id', $this->id)->exists();
    }
}
