<?php

namespace App\Models;

use App\Enums\ReasonAudience;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Motivo del Kardex (catálogo con base en Plataforma y copia por escuela): la observación de cada práctica al
 * calificar, por qué una reservación se queda sin calificación o por qué se califica un día después. Cada motivo
 * dice quién lo usa: el teacher, el personal (Recepción, Administración y Coordinación académica) o ambos.
 */
#[Fillable(['school_id', 'base_id', 'name', 'audience', 'sort_order', 'is_active'])]
class UngradedReason extends Model
{
    use Auditable, BelongsToSchool;

    /** nombre => quién lo usa */
    public const DEFAULTS = [
        'Pendiente de completar' => ReasonAudience::Teacher,
        'Pendiente de revisar' => ReasonAudience::Teacher,
        'Repetir' => ReasonAudience::Teacher,
        'Sección A completada' => ReasonAudience::Teacher,
        'Revisar correcciones' => ReasonAudience::Teacher,
        'Completar correcciones' => ReasonAudience::Teacher,
        'Material extra' => ReasonAudience::All,
        'Planeación de club' => ReasonAudience::Staff,
        'Planeación de field trip' => ReasonAudience::Staff,
        'Field trip externo' => ReasonAudience::Staff,
        'Field trip interno' => ReasonAudience::Staff,
        'Reporte de Alumno' => ReasonAudience::Staff,
        'Junta academica' => ReasonAudience::Staff,
        'Capacitacion' => ReasonAudience::Staff,
        'Contestar libro' => ReasonAudience::Staff,
        'Convivencia' => ReasonAudience::Staff,
        'Solicitud de permisos para field trip' => ReasonAudience::Staff,
        'Compra de material para Field trip' => ReasonAudience::Staff,
    ];

    protected function casts(): array
    {
        return [
            'audience' => ReasonAudience::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Activos que ese usuario puede elegir (los del teacher o los del personal, más los de ambos).
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsableBy(Builder $query, User $user): void
    {
        $query->where('is_active', true)->whereIn('audience', ReasonAudience::valuesFor($user));
    }

    /** Ya usado en una reservación o en el Kardex: solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return $this->school_id === null || (! Booking::withoutGlobalScope('school')->where('ungraded_reason_id', $this->id)->exists()
            && ! KardexEntry::withoutGlobalScope('school')->where('late_reason_id', $this->id)->orWhere('observation_reason_id', $this->id)->exists());
    }
}
