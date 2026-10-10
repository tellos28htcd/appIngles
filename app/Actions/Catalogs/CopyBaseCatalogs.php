<?php

namespace App\Actions\Catalogs;

use App\Enums\HolidayType;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Book;
use App\Models\ChargeConcept;
use App\Models\Classroom;
use App\Models\Club;
use App\Models\Holiday;
use App\Models\Lesson;
use App\Models\PaymentMethod;
use App\Models\ScheduleSlot;
use App\Models\School;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Copia a una escuela los registros activos del catálogo base que aún no
 * tiene (por base_id). Nunca modifica lo que la escuela ya ajustó.
 * Sirve al dar de alta la escuela y para "Incorporar novedades".
 */
final class CopyBaseCatalogs
{
    /** Orden de copia: primero los registros de los que dependen otros. */
    public const CATALOGS = [
        'shifts' => Shift::class,
        'schedule_slots' => ScheduleSlot::class,
        'books' => Book::class,
        'lessons' => Lesson::class,
        'activities' => Activity::class,
        'clubs' => Club::class,
        'classrooms' => Classroom::class,
        'payment_methods' => PaymentMethod::class,
        'charge_concepts' => ChargeConcept::class,
        'holidays' => Holiday::class,
    ];

    /** Catálogos donde el nombre es único por escuela: si ya existe uno igual, se vincula en vez de duplicar. */
    private const UNIQUE_BY_NAME = [Classroom::class, PaymentMethod::class, ChargeConcept::class];

    /**
     * @param  array<string, list<int>>|null  $only  catálogo => IDs base a copiar (null = todo lo pendiente)
     * @return int registros copiados
     */
    public function handle(School $school, ?array $only = null): int
    {
        $copied = DB::transaction(fn () => AuditLog::muted(function () use ($school, $only): int {
            $total = 0;

            foreach (self::CATALOGS as $catalog => $model) {
                $ids = $only === null ? null : ($only[$catalog] ?? []);

                if ($ids === []) {
                    continue;
                }

                foreach (self::pending($school, $model, $ids) as $base) {
                    if ($this->copy($school, $base) !== null) {
                        $total++;
                    }
                }
            }

            return $total;
        }));

        if ($copied > 0) {
            AuditLog::record($school, 'catalogs_copied', null, ['records' => $copied]);
        }

        return $copied;
    }

    /**
     * Registros base activos que la escuela todavía no tiene.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @param  list<int>|null  $ids
     * @return Collection<int, TModel>
     */
    public static function pending(School $school, string $model, ?array $ids = null): Collection
    {
        $copiedBaseIds = $model::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->whereNotNull('base_id')
            ->pluck('base_id');

        return $model::withoutGlobalScope('school')
            ->whereNull('school_id')
            ->where('is_active', true)
            ->whereNotIn('id', $copiedBaseIds)
            // Los festivos oficiales los genera cada escuela; de la base solo se copian días propios.
            ->when($model === Holiday::class, fn (Builder $query) => $query->where('type', HolidayType::School))
            ->when($ids !== null, fn (Builder $query) => $query->whereKey($ids))
            ->orderBy('id')
            ->get();
    }

    private function copy(School $school, Model $base): ?Model
    {
        $attributes = [
            ...$base->only($base->getFillable()),
            'school_id' => $school->id,
            'base_id' => $base->id,
        ];

        // Las dependencias se apuntan a la copia de la escuela (si existe).
        if ($base instanceof ScheduleSlot) {
            $attributes['shift_id'] = $this->schoolCopyId($school, Shift::class, $base->shift_id);
        }

        if ($base instanceof Lesson) {
            $attributes['book_id'] = $this->schoolCopyId($school, Book::class, $base->book_id);
        }

        // Si la escuela no tiene el turno o libro del que depende, no se puede copiar todavía.
        foreach (['shift_id', 'book_id'] as $dependency) {
            if (array_key_exists($dependency, $attributes) && $attributes[$dependency] === null) {
                return null;
            }
        }

        if (in_array($base::class, self::UNIQUE_BY_NAME, true)) {
            $existing = $base::withoutGlobalScope('school')
                ->where('school_id', $school->id)
                ->where('name', $base->getAttribute('name'))
                ->first();

            if ($existing !== null) {
                $existing->update(['base_id' => $base->id]);

                return $existing;
            }
        }

        $copy = $base::withoutGlobalScope('school')->create($attributes);

        if ($base instanceof Club) {
            $hours = [];

            foreach ($base->books()->withoutGlobalScope('school')->get() as $book) {
                $schoolBookId = $this->schoolCopyId($school, Book::class, $book->id);

                if ($schoolBookId !== null) {
                    $hours[$schoolBookId] = ['hours' => $book->pivot->hours];
                }
            }

            $copy->books()->sync($hours);
        }

        return $copy;
    }

    /** @param class-string<Model> $model */
    private function schoolCopyId(School $school, string $model, int $baseId): ?int
    {
        return $model::withoutGlobalScope('school')
            ->where('school_id', $school->id)
            ->where('base_id', $baseId)
            ->value('id');
    }
}
