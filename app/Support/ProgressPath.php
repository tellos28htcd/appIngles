<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\Practice;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Ruta de avance del alumno: el catálogo de Prácticas (Excel Cat_Practicas.xlsx) en su orden, el consecutivo en
 * que se asignan en la agenda y se califican en el Kardex (p. ej. CP1 A1, CP1 A3, VIC1 A3…A7, CP1 A6, CP1 A7).
 * Cada hora de práctica corresponde a un paso de esta ruta (LLA).
 *
 * Si la escuela no tiene catálogo de prácticas, la ruta se arma lección por lección (en su orden) con sus
 * actividades 1–7 (o las que tengan peso newton).
 */
final class ProgressPath
{
    /** Actividades por lección cuando no hay catálogo de prácticas. */
    public const ACTIVITIES_PER_LESSON = 7;

    /** @var Collection<int, array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string, order: int, points: int}> */
    private Collection $steps;

    public function __construct(School $school)
    {
        $books = Book::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)->orderBy('level')->get()->keyBy('id');
        $lessons = Lesson::withoutGlobalScope('school')->whereIn('book_id', $books->keys())->where('is_active', true)
            ->orderBy('sort_order')->orderBy('number')->get();
        $codes = Activity::codesFor($school->id);

        $this->steps = Practice::existsFor($school->id)
            ? $this->fromCatalog($school, $books, $lessons->keyBy('id'), $codes)
            : $this->byLesson($school, $books, $lessons, $codes);
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Lesson>  $lessons
     * @param  array<int, string>  $codes
     * @return Collection<int, array<string, mixed>>
     */
    private function fromCatalog(School $school, Collection $books, Collection $lessons, array $codes): Collection
    {
        return Practice::withoutGlobalScope('school')->where('school_id', $school->id)->where('is_active', true)
            ->whereIn('lesson_id', $lessons->keys())->orderBy('sort_order')->orderBy('id')->get()
            ->map(function (Practice $practice) use ($books, $lessons, $codes): array {
                $book = $books[$practice->book_id];
                $lesson = $lessons[$practice->lesson_id];

                return [
                    'book_id' => $book->id, 'level' => (int) $book->level, 'lesson_id' => $lesson->id, 'lesson_number' => (int) $lesson->number,
                    'activity_number' => $practice->activity_number, 'order' => $practice->sort_order, 'points' => $practice->points,
                    'label' => Practice::labelFor((int) $book->level, $lesson, $practice->activity_number, $practice->name, $codes),
                ];
            })->values();
    }

    /**
     * @param  Collection<int, Book>  $books
     * @param  Collection<int, Lesson>  $lessons
     * @param  array<int, string>  $codes
     * @return Collection<int, array<string, mixed>>
     */
    private function byLesson(School $school, Collection $books, Collection $lessons, array $codes): Collection
    {
        $weights = Lesson::weightsFor($school->id);
        $order = 0;

        return $books->values()->flatMap(fn (Book $book) => $lessons->where('book_id', $book->id)->flatMap(function (Lesson $lesson) use ($book, $codes, $weights, &$order) {
            $lessonWeights = $weights['configured'] ? ($weights['lessons'][$lesson->id] ?? null) : null;
            $numbers = $lessonWeights !== null ? array_keys($lessonWeights) : range(1, self::ACTIVITIES_PER_LESSON);

            return collect($numbers)->map(function (int $activity) use ($book, $lesson, $codes, &$order): array {
                $order++;

                return [
                    'book_id' => $book->id, 'level' => (int) $book->level, 'lesson_id' => $lesson->id, 'lesson_number' => (int) $lesson->number,
                    'activity_number' => $activity, 'order' => $order, 'points' => $order,
                    'label' => Practice::labelFor((int) $book->level, $lesson, $activity, null, $codes),
                ];
            });
        }))->values();
    }

    /** @return Collection<int, array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string, order: int, points: int}> */
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

    /**
     * Posición más cercana cuando la actividad ya no está en la ruta (p. ej. una que dejó de hacerse en ese tipo
     * de lección): la última de la lección anterior a ella ($before) o la primera posterior.
     */
    public function nearestIndex(?int $lessonId, ?int $activityNumber, bool $before): ?int
    {
        $exact = $this->indexOf($lessonId, $activityNumber);
        if ($exact !== null || $lessonId === null || $activityNumber === null) {
            return $exact;
        }

        $candidates = $this->steps->filter(fn (array $step) => $step['lesson_id'] === $lessonId
            && ($before ? $step['activity_number'] < $activityNumber : $step['activity_number'] > $activityNumber));

        $index = $before ? $candidates->keys()->last() : $candidates->keys()->first();

        return $index ?? ($before ? null : $this->firstOfLesson($lessonId));
    }

    /** @return array{book_id: int, level: int, lesson_id: int, lesson_number: int, activity_number: int, label: string, order: int, points: int}|null */
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
