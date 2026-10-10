<?php

namespace App\Livewire\Teachers;

use App\Actions\Users\SendInvitation;
use App\Enums\TeacherStatus;
use App\Models\School;
use App\Models\Teacher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Listado de teachers (de la escuela del usuario; el Super Admin ve todas). */
#[Layout('layouts.app')]
class TeacherIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'escuela', except: '')]
    public string $school = '';

    #[Url(as: 'estado', except: '')]
    public string $status = '';

    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Teacher::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'school', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function resendInvitation(int $teacherId, SendInvitation $sendInvitation): void
    {
        $teacher = Teacher::with('user')->findOrFail($teacherId);
        $this->authorize('resendInvitation', $teacher);

        $sent = $sendInvitation->handle($teacher->user);

        $this->dispatch('toast',
            type: $sent ? 'success' : 'error',
            message: $sent ? __('users.messages.invitation_sent', ['email' => $teacher->user->email]) : __('users.messages.mail_failed'),
        );
    }

    public function confirmDeletion(int $teacherId): void
    {
        $this->authorize('delete', Teacher::with('user')->findOrFail($teacherId));
        $this->confirmingDeletionId = $teacherId;
    }

    /** Elimina al teacher y su usuario (solo si no tiene registros en ningún módulo). */
    public function delete(): void
    {
        $teacher = Teacher::with('user')->findOrFail($this->confirmingDeletionId);
        $this->authorize('delete', $teacher);

        $teacher->user->delete();
        $this->confirmingDeletionId = null;

        $this->dispatch('toast', type: 'success', message: __('teachers.messages.deleted'));
    }

    #[Computed]
    public function pendingDeletion(): ?Teacher
    {
        return $this->confirmingDeletionId ? Teacher::find($this->confirmingDeletionId) : null;
    }

    /** @return LengthAwarePaginator<int, Teacher> */
    #[Computed]
    public function teachers(): LengthAwarePaginator
    {
        $term = trim($this->search);

        return Teacher::query()
            ->with(['user:id,email,password,status', 'school:id,code,name'])
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('second_last_name', 'like', "%{$term}%")
                ->orWhere('curp', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('email', 'like', "%{$term}%"))))
            ->when(auth()->user()->isPlatformAdmin() && $this->school !== '', fn (Builder $query) => $query->where('school_id', (int) $this->school))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25);
    }

    public function render(): View
    {
        $isPlatform = auth()->user()->isPlatformAdmin();

        return view('livewire.teachers.teacher-index', [
            'isPlatform' => $isPlatform,
            'schoolOptions' => $isPlatform
                ? School::orderBy('name')->get(['id', 'code', 'name'])->mapWithKeys(fn (School $s) => [(string) $s->id => "{$s->code} · {$s->name}"])->all()
                : [],
            'statusOptions' => collect(TeacherStatus::cases())->mapWithKeys(fn (TeacherStatus $s) => [$s->value => $s->label()])->all(),
        ])->title(__('teachers.title'));
    }
}
