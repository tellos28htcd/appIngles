<?php

namespace App\Models;

use App\Enums\LessonType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lección de un libro: Lesson, Check Point o Verb in context. */
#[Fillable(['school_id', 'base_id', 'book_id', 'number', 'name', 'type', 'activity_weights', 'sort_order', 'is_active'])]
class Lesson extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'type' => LessonType::class,
            'activity_weights' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** Pesos newton iniciales por tipo de lección (número de actividad => peso; sin la actividad = no se hace). */
    public const DEFAULT_WEIGHTS = [
        'lesson' => [1 => 5, 2 => 10, 3 => 40, 4 => 40, 5 => 15, 6 => 40, 7 => 20],
        'checkpoint' => [1 => 5, 3 => 40, 6 => 40, 8 => 240],
        'verb_in_context' => [6 => 40, 7 => 20],
    ];

    /**
     * Pesos de la lección: número de actividad => peso (solo las que se hacen); null si no tiene capturados.
     *
     * @return array<int, int>|null
     */
    public function weights(): ?array
    {
        if (! is_array($this->activity_weights)) {
            return null;
        }

        $weights = [];
        foreach ($this->activity_weights as $number => $weight) {
            if ($weight !== null && $weight !== '') {
                $weights[(int) $number] = (int) $weight;
            }
        }
        ksort($weights);

        return $weights;
    }

    /**
     * Pesos de todas las lecciones de una escuela (se consulta una vez por petición). Una lección sin pesos usa los
     * de otra del mismo tipo; "configured" es falso si la escuela no tiene ninguno (la ruta usa todas las actividades
     * y el balanceo cuenta alumnos).
     *
     * @return array{configured: bool, lessons: array<int, array<int, int>|null>}
     */
    public static function weightsFor(int $schoolId): array
    {
        return once(function () use ($schoolId): array {
            $lessons = self::withoutGlobalScope('school')->where('school_id', $schoolId)->orderBy('sort_order')->get(['id', 'type', 'activity_weights']);
            $templates = $lessons->filter(fn (Lesson $lesson) => $lesson->weights() !== null)->groupBy(fn (Lesson $lesson) => $lesson->type->value)
                ->map(fn ($group) => $group->first()->weights());

            return [
                'configured' => $templates->isNotEmpty(),
                'lessons' => $lessons->mapWithKeys(fn (Lesson $lesson) => [$lesson->id => $lesson->weights() ?? $templates->get($lesson->type->value)])->all(),
            ];
        });
    }

    /** @return BelongsTo<Book, $this> */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function canBeDeleted(): bool
    {
        return true;
    }
}
