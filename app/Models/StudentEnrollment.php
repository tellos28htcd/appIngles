<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Inscripción (contrato) del alumno. Puede tener varias: reinscripción o
 * convenio de extensión. Los datos de cobro van en Cobranza.
 */
#[Fillable([
    'school_id', 'student_id', 'contract_number', 'sales_advisor_id', 'student_plan_id', 'installments',
    'registered_on', 'starts_on', 'ends_on', 'withdrawn_on',
    'includes_term_extension', 'extension_days', 'includes_toefl', 'includes_gold_card',
    'material_serial', 'material_type', 'material_assigned', 'commission_applies',
    'start_book_id', 'start_lesson_id', 'start_activity_id', 'reenrolled_on', 'graduated_on',
])]
class StudentEnrollment extends Model
{
    use Auditable, BelongsToSchool;

    protected function casts(): array
    {
        return [
            'installments' => 'integer',
            'extension_days' => 'integer',
            'registered_on' => 'date',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'withdrawn_on' => 'date',
            'reenrolled_on' => 'date',
            'graduated_on' => 'date',
            'includes_term_extension' => 'boolean',
            'includes_toefl' => 'boolean',
            'includes_gold_card' => 'boolean',
            'material_assigned' => 'boolean',
            'commission_applies' => 'boolean',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SalesAdvisor, $this> */
    public function salesAdvisor(): BelongsTo
    {
        return $this->belongsTo(SalesAdvisor::class);
    }

    /** @return BelongsTo<StudentPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(StudentPlan::class, 'student_plan_id');
    }

    /** @return BelongsTo<Book, $this> */
    public function startBook(): BelongsTo
    {
        return $this->belongsTo(Book::class, 'start_book_id');
    }

    /** @return BelongsTo<Lesson, $this> */
    public function startLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'start_lesson_id');
    }

    /** @return BelongsTo<Activity, $this> */
    public function startActivity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'start_activity_id');
    }

    public function auditLabel(): string
    {
        return __('students.enrollment.label', ['number' => $this->contract_number ?? '#'.$this->id]);
    }

    /** @return list<string> Servicios incluidos: term_extension, toefl, gold_card. */
    public function services(): array
    {
        return array_keys(array_filter([
            'term_extension' => $this->includes_term_extension,
            'toefl' => $this->includes_toefl,
            'gold_card' => $this->includes_gold_card,
        ]));
    }
}
