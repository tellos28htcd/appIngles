<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\CoordinatorActivity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Actividades de coordinación": actividades del coordinador con sus horas autorizadas al mes (HA). */
class CoordinatorActivitiesTab extends Component
{
    use ManagesCatalog;

    public ?bool $editing = null;

    public ?int $activityId = null;

    public string $name = '';

    public ?float $authorizedHours = null;

    public bool $usesTeacherHour = false;

    public string $panelMark = '';

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $activity = $id ? $this->findInCatalog(CoordinatorActivity::class, $id) : null;
        $this->activityId = $activity?->id;
        $this->name = (string) $activity?->name;
        $this->authorizedHours = $activity?->authorized_hours;
        $this->usesTeacherHour = (bool) $activity?->uses_teacher_hour;
        $this->panelMark = (string) $activity?->panel_mark;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueInCatalog('coordinator_activities', 'name', $this->activityId)],
            'authorizedHours' => ['required', 'numeric', 'min:0', 'max:999'],
            'usesTeacherHour' => ['boolean'],
            'panelMark' => [$this->usesTeacherHour ? 'required' : 'nullable', 'string', 'max:3'],
        ], attributes: [
            'name' => Str::lower(__('catalogs.fields.name')),
            'authorizedHours' => Str::lower(__('catalogs.coordination.authorized_hours')),
            'panelMark' => Str::lower(__('catalogs.coordination.panel_mark')),
        ]);

        $activity = $this->activityId ? $this->findInCatalog(CoordinatorActivity::class, $this->activityId) : new CoordinatorActivity([
            'school_id' => $this->schoolId,
            'sort_order' => (int) $this->catalog(CoordinatorActivity::class)->max('sort_order') + 10,
            'is_active' => true,
        ]);
        $activity->fill([
            'name' => trim($data['name']),
            'authorized_hours' => $data['authorizedHours'],
            'uses_teacher_hour' => $data['usesTeacherHour'],
            'panel_mark' => $data['usesTeacherHour'] ? Str::upper(trim($data['panelMark'])) : null,
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(CoordinatorActivity::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(CoordinatorActivity::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['CoordinatorActivity' => CoordinatorActivity::class]);
    }

    /** @return Collection<int, CoordinatorActivity> */
    #[Computed]
    public function activities(): Collection
    {
        return $this->catalog(CoordinatorActivity::class)->orderBy('sort_order')->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.coordinator-activities-tab');
    }
}
