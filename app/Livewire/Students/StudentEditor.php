<?php

namespace App\Livewire\Students;

use App\Actions\Students\ChangeStudentStatus;
use App\Actions\Students\EnrollmentNumbers;
use App\Actions\Students\SyncStudentUser;
use App\Enums\StudentStatus;
use App\Livewire\Forms\EnrollmentForm;
use App\Livewire\Forms\StudentForm;
use App\Models\InformationSource;
use App\Models\School;
use App\Models\State;
use App\Models\Student;
use App\Models\StudentType;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta y edición del alumno. En el alta se captura también su primera
 * inscripción; después las inscripciones se manejan desde el expediente.
 * Nunca envía correo al alumno.
 */
#[Layout('layouts.app')]
class StudentEditor extends Component
{
    use Concerns\EnrollmentOptions;
    use WithFileUploads;

    public StudentForm $form;

    public EnrollmentForm $enrollment;

    public function mount(?Student $student = null): void
    {
        if ($student?->exists) {
            $this->authorize('update', $student);
            $this->form->setStudent($student->load('user'));
        } else {
            $this->authorize('create', Student::class);
            $this->enrollment->setEnrollment(null);
            $this->form->student_type_id = StudentType::query()->ofCatalog(auth()->user()->school_id)->where('code', 'ALUMN')->value('id');
            // Por defecto, el estado y municipio de la escuela.
            $school = School::findOrFail(auth()->user()->school_id);
            $this->form->state_id = $school->state_id;
            $this->form->municipality_id = $school->municipality_id;
        }
    }

    public function updatedFormStateId(): void
    {
        $this->form->municipality_id = null;
    }

    /** @return array<int, string> */
    #[Computed]
    public function states(): array
    {
        return State::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function municipalities(): array
    {
        return $this->form->municipalityOptions();
    }

    public function updatedEnrollmentStudentPlanId(): void
    {
        $this->enrollment->applyPlanInstallments();
    }

    public function updatedEnrollmentStartBookId(): void
    {
        $this->enrollment->start_lesson_id = null;
    }

    public function save(EnrollmentNumbers $numbers, ChangeStudentStatus $changeStatus, SyncStudentUser $syncUser): void
    {
        $creating = $this->form->student === null;

        $creating
            ? $this->authorize('create', Student::class)
            : $this->authorize('update', $this->form->student);

        // En el alta se valida todo antes de escribir nada.
        if ($creating) {
            $this->form->check();
            $this->enrollment->check();
        }

        $student = $this->form->save($numbers, $changeStatus, $syncUser,
            $creating ? fn (Student $student) => $this->enrollment->save($student) : null);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __($creating ? 'students.messages.created' : 'students.messages.updated', ['number' => $student->enrollment_number]),
        ]);

        $this->redirectRoute('students.show', $student, navigate: true);
    }

    /** Siguiente matrícula que se asignará si no se captura una (solo informativa). */
    #[Computed]
    public function nextNumber(): string
    {
        $school = School::findOrFail(auth()->user()->school_id);

        return app(EnrollmentNumbers::class)->format($school, now(config('appingles.timezone')), $school->last_enrollment_sequence + 1);
    }

    /** @return array<int, string> */
    #[Computed]
    public function typeOptions(): array
    {
        return StudentType::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)->orderBy('name')
            ->get()->mapWithKeys(fn (StudentType $type) => [$type->id => "{$type->code} · {$type->name}"])->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function sourceOptions(): array
    {
        return InformationSource::query()->ofCatalog(auth()->user()->school_id)->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Preguntas activas de la encuesta agrupadas por sección. @return Collection<string, Collection<int, \App\Models\SurveyQuestion>> */
    #[Computed]
    public function surveySections(): Collection
    {
        return $this->form->questions()->groupBy('section');
    }

    public function render(): View
    {
        $editing = $this->form->student !== null;

        return view('livewire.students.student-editor', [
            'editing' => $editing,
            'statuses' => collect(StudentStatus::cases())->mapWithKeys(fn (StudentStatus $s) => [$s->value => $s->label()])->all(),
            'photoPreview' => $this->form->photo?->isPreviewable() ? $this->form->photo->temporaryUrl() : null,
        ])->title(__($editing ? 'students.edit_title' : 'students.create_title'));
    }
}
