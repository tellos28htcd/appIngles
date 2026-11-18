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
        'lesson' => [1 => 4, 2 => 7, 3 => 10, 4 => 9, 5 => 15, 6 => 11, 7 => 18],
        'checkpoint' => [1 => 6, 3 => 11, 6 => 180, 7 => 50],
        'verb_in_context' => [3 => 7, 6 => 6, 7 => 9],
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

    /** Código corto de la lección en el nombre de la práctica: L1…L14, CP1/CP2 (Check Points) y VIC1/VIC2 (Verb in context). */
    public function shortCode(): string
    {
        return self::shortCodesFor($this->book_id)[$this->id] ?? "L{$this->number}";
    }

    /**
     * Códigos cortos de las lecciones de un libro (los Check Points y Verb in context se numeran en el orden del libro).
     *
     * @return array<int, string>
     */
    public static function shortCodesFor(int $bookId): array
    {
        return once(function () use ($bookId): array {
            $counters = [];

            return self::withoutGlobalScope('school')->where('book_id', $bookId)->orderBy('sort_order')->orderBy('number')->get(['id', 'number', 'type'])
                ->mapWithKeys(function (Lesson $lesson) use (&$counters): array {
                    $prefix = match ($lesson->type) {
                        LessonType::CheckPoint => 'CP', LessonType::VerbInContext => 'VIC', default => null
                    };

                    if ($prefix === null) {
                        return [$lesson->id => "L{$lesson->number}"];
                    }

                    $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

                    return [$lesson->id => $prefix.$counters[$prefix]];
                })->all();
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
