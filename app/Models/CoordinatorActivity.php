<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Actividad del coordinador con sus horas autorizadas al mes (HA). Si "se elige teacher"
 * (p. ej. Evaluación de Sesión) se programa con el teacher que está trabajando a esa hora; si
 * además "ocupa la hora del teacher" (Revisión de Planeaciones), esa hora deja de estar
 * disponible para clases y clubs y en el panel se marca con su letra (R). Sin teacher es
 * "Actividad Coordinación".
 */
#[Fillable(['school_id', 'base_id', 'name', 'authorized_hours', 'per_teacher', 'requires_teacher', 'uses_teacher_hour', 'panel_mark', 'table_column', 'sort_order', 'is_active'])]
class CoordinatorActivity extends Model
{
    use Auditable, BelongsToSchool;

    /**
     * Actividades iniciales: [nombre, HA, por teacher, se elige teacher, ocupa la hora del teacher, letra, columna].
     * Sin teacher = "Actividad Coordinación". "Por teacher": las HA son de cada teacher (Evaluación de Club y de Sesión).
     * Columna de la tabla HA/HP: 1 = izquierda, 2 = derecha.
     */
    public const DEFAULTS = [
        ['Carpetas mensuales de Oscar', 2, false, false, false, null, 1],
        ['Reporte mensual CN de Oscar', 2, false, false, false, null, 1],
        ['Evaluación de Club', 2, true, true, false, null, 1],
        ['Evaluación de Sesión', 2, true, true, false, null, 1],
        ['Programación de Clubs', 4, false, false, false, null, 2],
        ['Elaboración de club en Canva', 4, false, false, false, null, 2],
        ['Elaboración de clubs', 1, false, false, false, null, 2],
        ['Preparación de junta Mensual', 2, false, false, false, null, 2],
        ['Junta mensual', 1, false, false, false, null, 2],
        ['Preparación de retroalimentación', 1, false, true, false, null, 2],
        ['Retroalimentación', 3, false, true, false, null, 2],
        ['Registro de resultados EA', 3, false, true, false, null, 2],
        ['Registro de resultados REA', 2, false, true, false, null, 2],
        ['Revisión de Planeaciones', 8, false, true, true, 'R', 2],
        ['Revisión de WP', 4, false, true, false, null, 2],
        ['Registro de resultados WP', 2, false, true, false, null, 2],
    ];

    protected function casts(): array
    {
        return [
            'authorized_hours' => 'float',
            'per_teacher' => 'boolean',
            'requires_teacher' => 'boolean',
            'table_column' => 'integer',
            'uses_teacher_hour' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** ¿Se programa con un teacher? (siempre que ocupa su hora). */
    public function needsTeacher(): bool
    {
        return $this->requires_teacher || $this->uses_teacher_hour || $this->per_teacher;
    }

    /** Actividad por teacher que se programa desde sus clubs (p. ej. "Evaluación de Club"). */
    public function evaluatesClubs(): bool
    {
        return $this->per_teacher && str_contains(Str::lower(Str::ascii($this->name)), 'club');
    }

    /** Si ya tiene horas programadas, solo se desactiva. */
    public function canBeDeleted(): bool
    {
        return ! CoordinatorSession::withoutGlobalScope('school')->where('coordinator_activity_id', $this->id)->exists();
    }
}
