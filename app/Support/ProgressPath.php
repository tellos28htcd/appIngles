<?php

namespace App\Support;

use App\Enums\LessonType;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Ruta de avance del alumno (como la tabla del Excel): Libro (por nivel) → Lección (en su
 * orden: 1–7, Check Point C1, Verb in context 1, 8–14, …) → Actividad (1–7; los Check
 * Points llevan 8). Cada hora de asesoría corresponde a una actividad de esta ruta (LLA).
 */
final class ProgressPath
{
    /** Actividades por lección; los Check Points tienen una más (PR, presentación). */
    public const ACTIVITIES_PER_LESSON = 7;

    /** @var Collection<int, array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string}> */
    private Collection $steps;

    public function __construct(School $school)
    {
        $books = Book::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)->orderBy('level')->get();
        $lessons = Lesson::withoutGlobalScope('school')->whereIn('book_id', $books->modelKeys())->where('is_active', true)
            ->orderBy('sort_order')->orderBy('number')->get()->groupBy('book_id');

        $this->steps = $books->flatMap(fn (Book $book) => $lessons->get($book->id, collect())->flatMap(function (Lesson $lesson) use ($book) {
            $count = $lesson->type === LessonType::CheckPoint ? self::ACTIVITIES_PER_LESSON + 1 : self::ACTIVITIES_PER_LESSON;

            return collect(range(1, $count))->map(fn (int $activity) => [
                'book_id' => $book->id, 'level' => (int) $book->level, 'lesson_id' => $lesson->id, 'lesson_number' => (int) $lesson->number,
                'activity_number' => $activity, 'label' => "L{$book->level}-L{$lesson->number}-A{$activity}",
            ]);
        }))->values();
    }

    /** @return Collection<int, array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string}> */
    public function steps(): Collection
    {
        return $this->steps;
    }

    /** Posición de una actividad en la ruta (o null si no está). */
    public function indexOf(?int $lessonId, ?int $activityNumber): ?int
    {
        if ($lessonId === null || $activityNumber === null) {
            return null;
        }

        $index = $this->steps->search(fn (array $step) => $step['lesson_id'] === $lessonId && $step['activity_number'] === $activityNumber);

        return $index === false ? null : $index;
    }

    /** @return array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string}|null */
    public function at(int $index): ?array
    {
        return $this->steps->get($index);
    }

    /** Primera actividad de una lección (para el inicio del alumno si solo trae lección). */
    public function firstOfLesson(?int $lessonId): ?int
    {
        $index = $this->steps->search(fn (array $step) => $step['lesson_id'] === $lessonId);

        return $index === false ? null : $index;
    }
}
