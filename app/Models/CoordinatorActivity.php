<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Actividad del coordinador con sus horas autorizadas al mes (HA). Si "se elige teacher"
 * (p. ej. Evaluación de Sesión) se programa con el teacher que está trabajando a esa hora; si
 * además "ocupa la hora del teacher" (Revisión de Planeaciones), esa hora deja de estar
 * disponible para clases y clubs y en el panel se marca con su letra (R). Sin teacher es
 * "Actividad Coordinación".
 */
#[Fillable(['school_id', 'base_id', 'name', 'authorized_hours', 'requires_teacher', 'uses_teacher_hour', 'panel_mark', 'sort_order', 'is_active'])]
class CoordinatorActivity extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * Actividades iniciales: [nombre, HA, se elige teacher, ocupa la hora del teacher, letra en el panel].
     * Sin teacher = "Actividad Coordinación".
     */
    public const DEFAULTS = [
        ['Evaluación de Club', 2, true, false, null],
        ['Evaluación de Sesión', 2, true, false, null],
        ['Programación de Clubs', 4, false, false, null],
        ['Elaboración de club en Canva', 4, false, false, null],
        ['Elaboración de clubs', 1, false, false, null],
        ['Preparación de junta Mensual', 2, false, false, null],
        ['Junta mensual', 1, false, false, null],
        ['Preparación de retroalimentación', 1, true, false, null],
        ['Retroalimentación', 3, true, false, null],
        ['Registro de resultados EA', 3, true, false, null],
        ['Registro de resultados REA', 2, true, false, null],
        ['Revisión de Planeaciones', 8, true, true, 'R'],
        ['Revisión de WP', 4, true, false, null],
        ['Registro de resultados WP', 2, true, false, null],
    ];

    protected function casts(): array
    {
        return [
            'authorized_hours' => 'float',
            'requires_teacher' => 'boolean',
            'uses_teacher_hour' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** ¿Se programa con un teacher? (siempre que ocupa su hora). */
    public function needsTeacher(): bool
    {
        return $this->requires_teacher || $this->uses_teacher_hour;
    }

    /** Si ya tiene horas programadas, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! CoordinatorSession::withoutGlobalScope('school')->where('coordinator_activity_id', $this->id)->exists();
    }
}
