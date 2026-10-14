<?php

namespace App\Livewire\Guardians;

use App\Actions\Students\GrantGuardianAccess;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alumnos → Tutores. No todos los alumnos tienen tutor; un tutor puede tener
 * varios alumnos. Su acceso se da a mano, solo cuando lo solicita.
 */
#[Layout('layouts.app')]
class GuardianIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public ?bool $editing = null;

    public ?int $guardianId = null;

    public string $name = '';

    public string $mobilePhone = '';

    public string $email = '';

    /** @var list<string> */
    public array $receives = [];

    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('students.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', Guardian::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    private function find(?int $guardianId): Guardian
    {
        return Guardian::query()->where('school_id', auth()->user()->school_id)->with('user')->findOrFail($guardianId);
    }

    public function edit(int $guardianId): void
    {
        $guardian = $this->find($guardianId);
        $this->authorize('update', $guardian);
        $this->resetValidation();

        $this->guardianId = $guardian->id;
        $this->name = $guardian->name;
        $this->mobilePhone = (string) $guardian->mobile_phone;
        $this->email = (string) $guardian->email;
        $this->receives = $guardian->receives ?? [];
        $this->editing = true;
    }

    public function save(): void
    {
        $guardian = $this->find($this->guardianId);
        $this->authorize('update', $guardian);

        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'mobilePhone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +()\-]{7,20}$/'],
            'email' => [
                Rule::requiredIf($guardian->user_id !== null), 'nullable', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($guardian->user_id),
            ],
            'receives' => ['array'],
            'receives.*' => [Rule::in(Guardian::REPORTS)],
        ], [
            'email.unique' => __('users.validation.email_taken'),
        ], [
            'name' => Str::lower(__('students.guardians.name')),
            'mobilePhone' => Str::lower(__('students.guardians.phone')),
            'email' => Str::lower(__('students.guardians.email')),
        ]);

        $guardian->update([
            'name' => Str::upper(trim($this->name)),
            'mobile_phone' => trim($this->mobilePhone) ?: null,
            'email' => $this->email,
            'receives' => array_values($this->receives),
        ]);

        // Si ya tiene acceso, su usuario se mantiene al día.
        $guardian->user?->update(['first_name' => mb_substr($guardian->name, 0, 80), 'email' => $guardian->email]);

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('students.guardians.saved'));
    }

    public function grantAccess(int $guardianId, GrantGuardianAccess $grantAccess): void
    {
        $guardian = $this->find($guardianId);
        $this->authorize('grantAccess', $guardian);

        if (User::where('email', $guardian->email)->whereKeyNot($guardian->user_id)->exists()) {
            $this->dispatch('toast', type: 'error', message: __('users.validation.email_taken'));

            return;
        }

        $this->dispatch('toast', ...$grantAccess->handle($guardian)
            ? ['type' => 'success', 'message' => __('students.guardians.access_sent', ['email' => $guardian->email])]
            : ['type' => 'error', 'message' => __('users.messages.mail_failed')]);
    }

    public function confirmDeletion(int $guardianId): void
    {
        $this->authorize('delete', $this->find($guardianId));
        $this->confirmingDeletionId = $guardianId;
    }

    public function delete(): void
    {
        $guardian = $this->find($this->confirmingDeletionId);
        $this->authorize('delete', $guardian);

        $guardian->delete();
        $this->confirmingDeletionId = null;
        $this->dispatch('toast', type: 'success', message: __('students.guardians.deleted'));
    }

    #[Computed]
    public function pendingDeletion(): ?Guardian
    {
        return $this->confirmingDeletionId ? Guardian::query()->where('school_id', auth()->user()->school_id)->find($this->confirmingDeletionId) : null;
    }

    /** @return LengthAwarePaginator<int, Guardian> */
    #[Computed]
    public function guardians(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Guardian::query()
            ->where('school_id', auth()->user()->school_id)
            ->with(['user:id,email,password', 'students:students.id,enrollment_number,first_name,last_name,second_last_name'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('mobile_phone', 'like', "%{$term}%")
                ->orWhereHas('students', fn (Builder $student) => $student->search($term))))
            ->orderBy('name')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.guardians.guardian-index', [
            'reports' => collect(Guardian::REPORTS)->mapWithKeys(fn (string $report) => [$report => __("students.guardians.reports.$report")])->all(),
        ])->title(__('students.guardians.title'));
    }
}
