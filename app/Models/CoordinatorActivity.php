<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Actividad del coordinador con sus horas autorizadas al mes (HA). Si "ocupa la hora del
 * teacher" (p. ej. Revisión de Planeaciones), se programa con un teacher: esa hora deja de
 * estar disponible para clases y clubs y en el panel se marca con su letra (R).
 */
#[Fillable(['school_id', 'base_id', 'name', 'authorized_hours', 'uses_teacher_hour', 'panel_mark', 'sort_order', 'is_active'])]
class CoordinatorActivity extends Model
{
    use Auditable, BelongsToSchool;

    /** Actividades iniciales: [nombre, HA, ocupa la hora del teacher, letra en el panel]. */
    public const DEFAULTS = [
        ['Evaluación de Club', 2, false, null],
        ['Programación de Clubs', 4, false, null],
        ['Elaboración de club en Canva', 4, false, null],
        ['Elaboración de clubs', 1, false, null],
        ['Preparación de junta Mensual', 2, false, null],
        ['Junta mensual', 1, false, null],
        ['Preparación de retroalimentación', 1, false, null],
        ['Retroalimentación', 3, false, null],
        ['Registro de resultados EA', 3, false, null],
        ['Registro de resultados REA', 2, false, null],
        ['Revisión de Planeaciones', 8, true, 'R'],
        ['Revisión de WP', 4, false, null],
        ['Registro de resultados WP', 2, false, null],
    ];

    protected function casts(): array
    {
        return [
            'authorized_hours' => 'float',
            'uses_teacher_hour' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Si ya tiene horas programadas, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! CoordinatorSession::withoutGlobalScope('school')->where('coordinator_activity_id', $this->id)->exists();
    }
}
