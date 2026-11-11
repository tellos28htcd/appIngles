<?php

namespace App\Models;

use App\Enums\LessonType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Actividad / código de evaluación (BA, 1P, M, WS, 2P, WP, SP, PR). */
#[Fillable(['school_id', 'base_id', 'number', 'code', 'description', 'minutes', 'weight_lesson', 'weight_checkpoint', 'weight_verb_in_context', 'is_one_to_one', 'is_active'])]
class Activity extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'minutes' => 'integer',
            'weight_lesson' => 'integer',
            'weight_checkpoint' => 'integer',
            'weight_verb_in_context' => 'integer',
            'is_one_to_one' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Códigos de la escuela por número de actividad (se consulta una sola vez por petición).
     *
     * @return array<int, string> número => código (BA, 1P, M…)
     */
    public static function codesFor(int $schoolId): array
    {
        return once(fn (): array => self::withoutGlobalScope('school')->where('school_id', $schoolId)->pluck('code', 'number')->all());
    }

    /** Peso de la actividad en un tipo de lección (null = no se hace en ese tipo). */
    public function weightFor(LessonType $type): ?int
    {
        return $this->getAttribute('weight_'.$type->value);
    }

    /**
     * Pesos activos de la escuela por tipo de lección y número de actividad. Vacío si la escuela no tiene pesos
     * capturados (entonces la ruta usa todas las actividades y el balanceo cuenta alumnos).
     *
     * @return array{weights: array<string, array<int, int>>, one_to_one: list<int>}
     */
    public static function weightsFor(int $schoolId): array
    {
        return once(function () use ($schoolId): array {
            $activities = self::withoutGlobalScope('school')->where('school_id', $schoolId)->where('is_active', true)->orderBy('number')->get();
            $weights = [];

            foreach (LessonType::cases() as $type) {
                foreach ($activities as $activity) {
                    if ($activity->weightFor($type) !== null) {
                        $weights[$type->value][$activity->number] = $activity->weightFor($type);
                    }
                }
            }

            return ['weights' => $weights, 'one_to_one' => $activities->where('is_one_to_one', true)->pluck('number')->map(fn ($number) => (int) $number)->values()->all()];
        });
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
