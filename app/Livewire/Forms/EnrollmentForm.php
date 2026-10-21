<?php

namespace App\Livewire\Forms;

use App\Enums\CourseEndFrom;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPlan;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Inscripción (contrato) del alumno: asesor, plan, fechas, servicios incluidos,
 * material y punto de inicio (libro, lección, actividad). Sin datos de cobro.
 */
class EnrollmentForm extends Form
{
    public ?StudentEnrollment $enrollment = null;

    public string $contract_number = '';

    public ?int $sales_advisor_id = null;

    public ?int $student_plan_id = null;

    public ?int $installments = null;

    /** Fecha de contrato. */
    public string $registered_on = '';

    public string $material_delivered_on = '';

    public bool $includes_toefl = false;

    public bool $includes_gold_card = false;

    public string $material_serial = '';

    /** F = Financiero, C = Contable. */
    public string $material_type = '';

    public bool $material_assigned = true;

    public bool $commission_applies = true;

    public ?int $start_book_id = null;

    public ?int $start_lesson_id = null;

    public ?int $start_activity_id = null;

    public string $reenrolled_on = '';

    /** Inicio del curso, fecha de baja y de graduación se capturan en datos generales del alumno. */
    private const DATES = ['registered_on', 'material_delivered_on', 'reenrolled_on'];

    public const MATERIAL_TYPES = ['F', 'C'];

    public function setEnrollment(?StudentEnrollment $enrollment): void
    {
        $this->reset();
        $this->enrollment = $enrollment;

        if ($enrollment === null) {
            $this->registered_on = now(config('appingles.timezone'))->toDateString();

            return;
        }

        $this->fill([
            'contract_number' => (string) $enrollment->contract_number,
            'material_serial' => (string) $enrollment->material_serial,
            'material_type' => (string) $enrollment->material_type,
            ...$enrollment->only([
                'sales_advisor_id', 'student_plan_id', 'installments', 'start_book_id', 'start_lesson_id', 'start_activity_id',
                'includes_toefl', 'includes_gold_card', 'material_assigned', 'commission_applies',
            ]),
            ...collect(self::DATES)->mapWithKeys(fn (string $field) => [$field => (string) $enrollment->{$field}?->toDateString()])->all(),
        ]);
    }

    /** Al elegir plan se proponen sus mensualidades. */
    public function applyPlanInstallments(): void
    {
        $plan = $this->student_plan_id ? StudentPlan::find($this->student_plan_id) : null;

        if ($plan !== null) {
            $this->installments = $plan->installments;
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $schoolId = auth()->user()->school_id;
        $inSchool = fn (string $table) => Rule::exists($table, 'id')->where('school_id', $schoolId);

        return [
            'contract_number' => ['nullable', 'string', 'max:30'],
            'sales_advisor_id' => ['nullable', 'integer', $inSchool('sales_advisors')],
            'student_plan_id' => ['nullable', 'integer', $inSchool('student_plans')],
            'installments' => ['nullable', 'integer', 'min:0', 'max:60'],
            'registered_on' => ['nullable', 'date'],
            // Obligatoria si la escuela cuenta el fin del curso desde la entrega de material.
            'material_delivered_on' => [Rule::requiredIf(School::find($schoolId)?->course_end_from === CourseEndFrom::MaterialDelivery), 'nullable', 'date'],
            'includes_toefl' => ['boolean'],
            'includes_gold_card' => ['boolean'],
            'material_serial' => ['nullable', 'string', 'max:40'],
            'material_type' => ['nullable', Rule::in(self::MATERIAL_TYPES)],
            'material_assigned' => ['boolean'],
            'commission_applies' => ['boolean'],
            'start_book_id' => ['nullable', 'integer', $inSchool('books')],
            'start_lesson_id' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('school_id', $schoolId)->where('book_id', $this->start_book_id)],
            'start_activity_id' => ['nullable', 'integer', $inSchool('activities')],
            'reenrolled_on' => ['nullable', 'date'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return collect(array_keys($this->rules()))
            ->mapWithKeys(fn (string $field) => [$field => Str::lower(__("students.enrollment.fields.$field"))])
            ->all();
    }

    /** Valida sin guardar (el alta del alumno valida todo antes de escribir). */
    public function check(): void
    {
        $this->validate();
    }

    public function save(Student $student): StudentEnrollment
    {
        $data = $this->validate();
        $data = array_map(fn ($value) => $value === '' ? null : $value, $data);
        $data['contract_number'] = $data['contract_number'] === null ? null : Str::upper(trim($data['contract_number']));
        // Sin material no hay serie ni tipo.
        if (! $data['material_assigned']) {
            $data['material_serial'] = null;
            $data['material_type'] = null;
        }

        $enrollment = $this->enrollment ?? new StudentEnrollment(['school_id' => $student->school_id, 'student_id' => $student->id]);
        $enrollment->fill($data)->save();

        $this->enrollment = $enrollment;

        return $enrollment;
    }
}
