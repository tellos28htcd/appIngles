<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\AdvisorPosition;
use App\Models\SalesAdvisor;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Asesores de ventas": solo de la escuela (no hay catálogo base). */
class SalesAdvisorsTab extends Component
{
    use ManagesCatalog;

    public ?bool $editing = null;

    public ?int $advisorId = null;

    public string $name = '';

    public ?int $positionId = null;

    public string $hiredOn = '';

    public string $leftOn = '';

    public function mount(?int $schoolId): void
    {
        abort_if($schoolId === null, 404);

        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $advisor = $id ? $this->findInCatalog(SalesAdvisor::class, $id) : null;
        $this->advisorId = $advisor?->id;
        $this->name = (string) $advisor?->name;
        $this->positionId = $advisor?->advisor_position_id;
        $this->hiredOn = (string) $advisor?->hired_on?->toDateString();
        $this->leftOn = (string) $advisor?->left_on?->toDateString();
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->name = Str::upper(trim($this->name));

        $this->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueInCatalog('sales_advisors', 'name', $this->advisorId)],
            'positionId' => ['nullable', 'integer', Rule::exists('advisor_positions', 'id')->where('school_id', $this->schoolId)],
            'hiredOn' => ['nullable', 'date'],
            'leftOn' => ['nullable', 'date', 'after_or_equal:hiredOn'],
        ], [], [
            'name' => Str::lower(__('catalogs.student.advisors.fields.name')),
            'positionId' => Str::lower(__('catalogs.student.advisors.fields.position')),
            'hiredOn' => Str::lower(__('catalogs.student.advisors.fields.hired_on')),
            'leftOn' => Str::lower(__('catalogs.student.advisors.fields.left_on')),
        ]);

        $advisor = $this->advisorId
            ? $this->findInCatalog(SalesAdvisor::class, $this->advisorId)
            : new SalesAdvisor(['school_id' => $this->schoolId, 'is_active' => true]);

        $advisor->fill([
            'name' => $this->name,
            'advisor_position_id' => $this->positionId,
            'hired_on' => $this->hiredOn ?: null,
            'left_on' => $this->leftOn ?: null,
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(SalesAdvisor::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(SalesAdvisor::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['SalesAdvisor' => SalesAdvisor::class]);
    }

    /** @return Collection<int, SalesAdvisor> */
    #[Computed]
    public function advisors(): Collection
    {
        return $this->catalog(SalesAdvisor::class)->with('position:id,name')->orderByDesc('is_active')->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.sales-advisors-tab', [
            'positions' => AdvisorPosition::query()->ofCatalog($this->schoolId)->where('is_active', true)->orderBy('rank')->pluck('name', 'id')->all(),
        ]);
    }
}
