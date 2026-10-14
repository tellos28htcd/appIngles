<?php

namespace App\Livewire\Students;

use App\Actions\Students\ChangeStudentStatus;
use App\Actions\Users\SendInvitation;
use App\Enums\StudentStatus;
use App\Livewire\Forms\EnrollmentForm;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\StudentAbsence;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Expediente del alumno: datos, inscripciones, histórico de estatus (con
 * motivo), ausencias, tutores y encuesta. Consultar requiere "Expedientes";
 * modificar, ser administrador de la escuela o Servicios escolares.
 */
#[Layout('layouts.app')]
class StudentShow extends Component
{
    use Concerns\EnrollmentOptions;

    #[Locked]
    public int $studentId;

    public EnrollmentForm $enrollment;

    public ?bool $editingEnrollment = null;

    public ?bool $changingStatus = null;

    public string $newStatus = '';

    public string $statusDate = '';

    public string $statusReason = '';

    public ?bool $addingAbsence = null;

    public ?int $absenceId = null;

    public string $absenceFrom = '';

    public string $absenceTo = '';

    public string $absenceReason = '';

    public ?bool $linkingGuardian = null;

    /** '' = tutor nuevo; un ID = tutor ya registrado en la escuela. */
    public string $guardianId = '';

    public string $guardianName = '';

    public string $guardianPhone = '';

    public string $guardianEmail = '';

    public string $guardianRelationship = '';

    /** @var list<string> */
    public array $guardianReceives = [];

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);
        $this->studentId = $student->id;
    }

    #[Computed]
    public function student(): Student
    {
        return Student::query()->where('school_id', auth()->user()->school_id)
            ->with([
                'user', 'type', 'informationSource',
                'enrollments' => fn ($query) => $query->latest('registered_on')->latest('id'),
                'enrollments.salesAdvisor', 'enrollments.plan', 'enrollments.startBook', 'enrollments.startLesson', 'enrollments.startActivity',
                'statusChanges' => fn ($query) => $query->latest('changed_on')->latest('id'),
                'statusChanges.user:id,name',
                'absences' => fn ($query) => $query->latest('starts_on'),
                'guardians.user:id,email,password',
                'surveyAnswers.question',
            ])
            ->findOrFail($this->studentId);
    }

    private function authorizeUpdate(): Student
    {
        $student = $this->student;
        $this->authorize('update', $student);

        return $student;
    }

    // ── Inscripciones ──────────────────────────────────────────────

    public function editEnrollment(?int $enrollmentId = null): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $this->enrollment->setEnrollment($enrollmentId ? $student->enrollments->firstWhere('id', $enrollmentId) ?? abort(404) : null);
        $this->editingEnrollment = true;
    }

    public function updatedEnrollmentStudentPlanId(): void
    {
        $this->enrollment->applyPlanInstallments();
    }

    public function updatedEnrollmentStartBookId(): void
    {
        $this->enrollment->start_lesson_id = null;
    }

    public function saveEnrollment(): void
    {
        $student = $this->authorizeUpdate();

        // La inscripción que se edita debe ser de este alumno, aunque alguien la cambie en el navegador.
        abort_if($this->enrollment->enrollment !== null && $this->enrollment->enrollment->student_id !== $student->id, 404);

        $this->enrollment->save($student);
        $this->editingEnrollment = null;
        unset($this->student);

        $this->dispatch('toast', type: 'success', message: __('students.messages.enrollment_saved'));
    }

    // ── Estatus ────────────────────────────────────────────────────

    public function askStatusChange(): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $this->newStatus = $student->status->value;
        $this->statusDate = now(config('appingles.timezone'))->toDateString();
        $this->statusReason = '';
        $this->changingStatus = true;
    }

    public function changeStatus(ChangeStudentStatus $changeStatus): void
    {
        $student = $this->authorizeUpdate();
        $status = StudentStatus::tryFrom($this->newStatus);

        $this->validate([
            'newStatus' => ['required', Rule::enum(StudentStatus::class)],
            'statusDate' => ['required', 'date', 'before_or_equal:today'],
            'statusReason' => [Rule::requiredIf($status?->requiresReason() ?? false), 'nullable', 'string', 'max:1000'],
        ], attributes: [
            'newStatus' => Str::lower(__('students.fields.status')),
            'statusDate' => Str::lower(__('students.status_change.date')),
            'statusReason' => Str::lower(__('students.status_change.reason')),
        ]);

        $changeStatus->handle($student, $status, $this->statusReason, Carbon::parse($this->statusDate));

        $this->changingStatus = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.status_changed', ['status' => $status->label()]));
    }

    // ── Ausencias (pausas) ─────────────────────────────────────────

    public function editAbsence(?int $absenceId = null): void
    {
        $student = $this->authorizeUpdate();
        $this->resetValidation();

        $absence = $absenceId ? $student->absences->firstWhere('id', $absenceId) ?? abort(404) : null;
        $this->absenceId = $absence?->id;
        $this->absenceFrom = (string) ($absence?->starts_on?->toDateString() ?? now(config('appingles.timezone'))->toDateString());
        $this->absenceTo = (string) $absence?->ends_on?->toDateString();
        $this->absenceReason = (string) $absence?->reason;
        $this->addingAbsence = true;
    }

    public function saveAbsence(): void
    {
        $student = $this->authorizeUpdate();

        $data = $this->validate([
            'absenceFrom' => ['required', 'date'],
            'absenceTo' => ['nullable', 'date', 'after_or_equal:absenceFrom'],
            'absenceReason' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'absenceFrom' => Str::lower(__('students.absences.from')),
            'absenceTo' => Str::lower(__('students.absences.to')),
            'absenceReason' => Str::lower(__('students.absences.reason')),
        ]);

        $absence = $this->absenceId
            ? $student->absences->firstWhere('id', $this->absenceId) ?? abort(404)
            : new StudentAbsence(['school_id' => $student->school_id, 'student_id' => $student->id, 'user_id' => auth()->id()]);

        $absence->fill([
            'starts_on' => $data['absenceFrom'],
            'ends_on' => $data['absenceTo'] ?: null,
            'reason' => trim($data['absenceReason']) ?: null,
        ])->save();

        $this->addingAbsence = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.absence_saved'));
    }

    // ── Tutores ────────────────────────────────────────────────────

    public function linkGuardian(): void
    {
        $this->authorizeUpdate();
        $this->resetValidation();
        $this->reset('guardianId', 'guardianName', 'guardianPhone', 'guardianEmail', 'guardianRelationship', 'guardianReceives');
        $this->linkingGuardian = true;
    }

    public function saveGuardian(): void
    {
        $student = $this->authorizeUpdate();
        $schoolId = $student->school_id;
        $existing = $this->guardianId !== '';

        $this->validate([
            'guardianId' => ['nullable', Rule::exists('guardians', 'id')->where('school_id', $schoolId)],
            'guardianName' => [Rule::requiredIf(! $existing), 'nullable', 'string', 'max:160'],
            'guardianPhone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'guardianEmail' => ['nullable', 'email:rfc', 'max:255'],
            'guardianRelationship' => ['nullable', 'string', 'max:40'],
            'guardianReceives' => ['array'],
            'guardianReceives.*' => [Rule::in(Guardian::REPORTS)],
        ], attributes: [
            'guardianName' => Str::lower(__('students.guardians.name')),
            'guardianPhone' => Str::lower(__('students.guardians.phone')),
            'guardianEmail' => Str::lower(__('students.guardians.email')),
        ]);

        $guardian = $existing
            ? Guardian::where('school_id', $schoolId)->findOrFail((int) $this->guardianId)
            : Guardian::create([
                'school_id' => $schoolId,
                'name' => Str::upper(trim($this->guardianName)),
                'mobile_phone' => trim($this->guardianPhone) ?: null,
                'email' => $this->guardianEmail,
                'receives' => array_values($this->guardianReceives),
            ]);

        $student->guardians()->syncWithoutDetaching([$guardian->id => ['relationship' => trim($this->guardianRelationship) ?: null]]);

        $this->linkingGuardian = null;
        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.guardian_linked'));
    }

    public function unlinkGuardian(int $guardianId): void
    {
        $student = $this->authorizeUpdate();
        $student->guardians()->detach($guardianId);

        unset($this->student);
        $this->dispatch('toast', type: 'success', message: __('students.messages.guardian_unlinked'));
    }

    /** @return array<int, string> */
    #[Computed]
    public function guardianOptions(): array
    {
        return Guardian::query()->where('school_id', auth()->user()->school_id)->orderBy('name')->get(['id', 'name', 'mobile_phone'])
            ->mapWithKeys(fn (Guardian $guardian) => [$guardian->id => trim($guardian->name.' '.($guardian->mobile_phone ? "· {$guardian->mobile_phone}" : ''))])
            ->all();
    }

    // ── Acceso ─────────────────────────────────────────────────────

    public function sendAccess(SendInvitation $sendInvitation): void
    {
        $student = $this->student;
        $this->authorize('sendAccess', $student);

        $this->dispatch('toast', ...$sendInvitation->handle($student->user)
            ? ['type' => 'success', 'message' => __('students.messages.access_sent', ['email' => $student->user->email])]
            : ['type' => 'error', 'message' => __('users.messages.mail_failed')]);
    }

    public function render(): View
    {
        return view('livewire.students.student-show', [
            'canUpdate' => auth()->user()->can('update', $this->student),
            'statuses' => collect(StudentStatus::cases())->mapWithKeys(fn (StudentStatus $s) => [$s->value => $s->label()])->all(),
            'reports' => collect(Guardian::REPORTS)->mapWithKeys(fn (string $report) => [$report => __("students.guardians.reports.$report")])->all(),
            'timezone' => config('appingles.timezone'),
        ])->title($this->student->fullName());
    }
}
