<?php

namespace App\Livewire\Students;

use App\Actions\Users\SendInvitation;
use App\Enums\StudentStatus;
use App\Models\SalesAdvisor;
use App\Models\Student;
use App\Models\StudentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Alumnos → Expedientes. Siempre de la escuela del usuario. El acceso del
 * alumno nunca sale al darlo de alta: solo con "Enviar acceso".
 */
#[Layout('layouts.app')]
class StudentIndex extends Component
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [25, 50, 100];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'estatus', except: '')]
    public string $status = '';

    #[Url(as: 'asesor', except: '')]
    public string $advisor = '';

    #[Url(as: 'tipo', except: '')]
    public string $type = '';

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'por_pagina', except: 25)]
    public int $perPage = 25;

    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        // El Super Admin no pertenece a una escuela: los expedientes los lleva cada escuela.
        if (auth()->user()->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('students.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $this->authorize('viewAny', Student::class);
    }

    public function updated(string $property): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        if (StudentStatus::tryFrom($this->status) === null) {
            $this->status = '';
        }

        foreach (['from', 'to'] as $date) {
            if ($this->{$date} !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->{$date})) {
                $this->{$date} = '';
            }
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'advisor', 'type', 'from', 'to');
        $this->resetPage();
    }

    public function sendAccess(int $studentId, SendInvitation $sendInvitation): void
    {
        $student = $this->find($studentId);
        $this->authorize('sendAccess', $student);

        $this->dispatch('toast', ...$sendInvitation->handle($student->user)
            ? ['type' => 'success', 'message' => __('students.messages.access_sent', ['email' => $student->user->email])]
            : ['type' => 'error', 'message' => __('users.messages.mail_failed')]);
    }

    public function confirmDeletion(int $studentId): void
    {
        $this->authorize('delete', $this->find($studentId));
        $this->confirmingDeletionId = $studentId;
    }

    public function delete(): void
    {
        $student = $this->find($this->confirmingDeletionId);
        $this->authorize('delete', $student);

        DB::transaction(function () use ($student): void {
            $user = $student->user;
            $student->delete();
            $user?->delete();
        });

        if ($student->photo_path !== null) {
            Storage::disk('local')->delete($student->photo_path);
        }
        $this->confirmingDeletionId = null;

        $this->dispatch('toast', type: 'success', message: __('students.messages.deleted'));
    }

    /** Solo alumnos de la escuela del usuario; un ID ajeno responde 404. */
    private function find(?int $studentId): Student
    {
        return Student::query()->where('school_id', auth()->user()->school_id)->with('user')->findOrFail($studentId);
    }

    #[Computed]
    public function pendingDeletion(): ?Student
    {
        return $this->confirmingDeletionId ? Student::query()->where('school_id', auth()->user()->school_id)->find($this->confirmingDeletionId) : null;
    }

    /** @return LengthAwarePaginator<int, Student> */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        $timezone = config('appingles.timezone');

        return Student::query()
            ->where('school_id', auth()->user()->school_id)
            ->with(['user:id,email,password,status', 'type:id,code,name', 'latestEnrollment.salesAdvisor:id,name'])
            ->search($this->search)
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '', fn (Builder $query) => $query->where('student_type_id', (int) $this->type))
            ->when($this->advisor !== '', fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollment) => $enrollment->where('sales_advisor_id', (int) $this->advisor)))
            ->when($this->from !== '' || $this->to !== '', fn (Builder $query) => $query->whereHas('enrollments', fn (Builder $enrollment) => $enrollment
                ->when($this->from !== '', fn (Builder $q) => $q->whereDate('registered_on', '>=', $this->from))
                ->when($this->to !== '', fn (Builder $q) => $q->whereDate('registered_on', '<=', $this->to))))
            ->orderByDesc('id')
            ->paginate($this->perPage);
    }

    public function render(): View
    {
        return view('livewire.students.student-index', [
            'statusOptions' => collect(StudentStatus::cases())->mapWithKeys(fn (StudentStatus $s) => [$s->value => $s->label()])->all(),
            'advisorOptions' => SalesAdvisor::query()->where('school_id', auth()->user()->school_id)->orderBy('name')->pluck('name', 'id')->all(),
            'typeOptions' => StudentType::query()->ofCatalog(auth()->user()->school_id)->orderBy('name')->pluck('name', 'id')->all(),
            'hasFilters' => $this->search !== '' || $this->status !== '' || $this->advisor !== '' || $this->type !== '' || $this->from !== '' || $this->to !== '',
        ])->title(__('students.title'));
    }
}
