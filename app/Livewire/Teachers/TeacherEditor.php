<?php

namespace App\Livewire\Teachers;

use App\Actions\Users\SendInvitation;
use App\Enums\ContractType;
use App\Enums\TeacherStatus;
use App\Livewire\Forms\TeacherForm;
use App\Models\School;
use App\Models\State;
use App\Models\Teacher;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Alta y edición de un teacher (con su usuario de acceso). */
#[Layout('layouts.app')]
class TeacherEditor extends Component
{
    use WithFileUploads;

    public TeacherForm $form;

    public function mount(?Teacher $teacher = null): void
    {
        if ($teacher?->exists) {
            $this->authorize('update', $teacher);
            $this->form->setTeacher($teacher->load('user'));
        } else {
            $this->authorize('create', Teacher::class);
            $user = auth()->user();
            $this->form->school_id = $user->isPlatformAdmin() ? null : $user->school_id;
        }
    }

    public function updatedFormStateId(): void
    {
        $this->form->municipality_id = null;
    }

    public function updatedFormContractType(): void
    {
        $this->form->syncWeeklyHours();
    }

    public function save(SendInvitation $sendInvitation): void
    {
        $this->form->teacher
            ? $this->authorize('update', $this->form->teacher)
            : $this->authorize('create', Teacher::class);

        $created = $this->form->save($sendInvitation);
        $mailFailed = $created && $this->form->invitationSent === false;

        session()->flash('toast', [
            'type' => $mailFailed ? 'warning' : 'success',
            'message' => __(match (true) {
                $mailFailed => 'teachers.messages.created_mail_failed',
                $created => 'teachers.messages.created',
                default => 'teachers.messages.updated',
            }),
        ]);

        $this->redirectRoute('teachers.index', navigate: true);
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

    public function render(): View
    {
        $editing = $this->form->teacher !== null;
        $isPlatform = auth()->user()->isPlatformAdmin();

        return view('livewire.teachers.teacher-editor', [
            'editing' => $editing,
            'isPlatform' => $isPlatform,
            'schoolOptions' => $isPlatform
                ? School::orderBy('name')->get(['id', 'code', 'name'])->mapWithKeys(fn (School $s) => [$s->id => "{$s->code} · {$s->name}"])->all()
                : [],
            'contractTypes' => collect(ContractType::cases())->mapWithKeys(fn (ContractType $type) => [
                $type->value => $type->label().' · '.($type->hasFixedHours() ? trans_choice('teachers.hours_max', $type->maxWeeklyHours()) : __('teachers.hours_assigned')),
            ])->all(),
            'statuses' => collect(TeacherStatus::cases())->mapWithKeys(fn (TeacherStatus $status) => [$status->value => $status->label()])->all(),
            'fixedHours' => ContractType::tryFrom($this->form->contract_type)?->hasFixedHours() ?? true,
            'photoPreview' => $this->form->photo?->isPreviewable() ? $this->form->photo->temporaryUrl() : null,
        ])->title(__($editing ? 'teachers.edit_title' : 'teachers.create_title'));
    }
}
