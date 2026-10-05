<?php

namespace App\Livewire\Schools;

use App\Enums\SchoolStatus;
use App\Models\School;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Listado de escuelas (solo Super Admin). */
#[Layout('layouts.app')]
class SchoolIndex extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    public ?int $confirmingSuspendId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', School::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function confirmSuspend(int $schoolId): void
    {
        $this->authorize('toggleStatus', School::findOrFail($schoolId));
        $this->confirmingSuspendId = $schoolId;
    }

    public function suspend(): void
    {
        $school = School::findOrFail($this->confirmingSuspendId);
        $this->authorize('toggleStatus', $school);

        $school->update(['status' => SchoolStatus::Suspended]);
        $this->confirmingSuspendId = null;

        $this->dispatch('toast', type: 'success', message: __('schools.suspended_msg'));
    }

    public function activate(int $schoolId): void
    {
        $school = School::findOrFail($schoolId);
        $this->authorize('toggleStatus', $school);

        $school->update(['status' => SchoolStatus::Active]);

        $this->dispatch('toast', type: 'success', message: __('schools.activated'));
    }

    #[Computed]
    public function schoolToSuspend(): ?School
    {
        return $this->confirmingSuspendId ? School::find($this->confirmingSuspendId) : null;
    }

    /** @return LengthAwarePaginator<int, School> */
    #[Computed]
    public function schools(): LengthAwarePaginator
    {
        return School::query()
            ->with(['state:id,name', 'municipality:id,name'])
            ->withCount('users')
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('code', 'like', "%{$this->search}%")
                ->orWhere('name', 'like', "%{$this->search}%")
                ->orWhere('legal_name', 'like', "%{$this->search}%")))
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->orderBy('name')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.schools.school-index', [
            'totalSchools' => School::count(),
        ])->title(__('schools.title'));
    }
}
