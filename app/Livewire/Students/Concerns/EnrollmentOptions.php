<?php

namespace App\Livewire\Students\Concerns;

use App\Models\Activity;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\SalesAdvisor;
use App\Models\StudentPlan;
use Livewire\Attributes\Computed;

/**
 * Opciones de la inscripción (asesor, plan, libro, lección y actividad de
 * inicio) desde los catálogos de la escuela. Requiere la propiedad $enrollment.
 */
trait EnrollmentOptions
{
    /** @return array<int, string> */
    #[Computed]
    public function advisorOptions(): array
    {
        return SalesAdvisor::query()->where('school_id', auth()->user()->school_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->enrollment->sales_advisor_id))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function planOptions(): array
    {
        return StudentPlan::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (StudentPlan $plan) => [$plan->id => $plan->name.' · '.trans_choice('students.enrollment.installments_count', $plan->installments)])
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function bookOptions(): array
    {
        return Book::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)->orderBy('level')->get()
            ->mapWithKeys(fn (Book $book) => [$book->id => __('students.enrollment.book_option', ['level' => $book->level, 'name' => $book->name])])
            ->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function lessonOptions(): array
    {
        if ($this->enrollment->start_book_id === null) {
            return [];
        }

        return Lesson::query()->ofCatalog(auth()->user()->school_id)->where('book_id', $this->enrollment->start_book_id)
            ->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function activityOptions(): array
    {
        return Activity::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)->orderBy('number')->get()
            ->mapWithKeys(fn (Activity $activity) => [$activity->id => "{$activity->number}. {$activity->code} · {$activity->description}"])
            ->all();
    }
}
