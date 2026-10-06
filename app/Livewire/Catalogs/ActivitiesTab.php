<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Actividades": códigos de evaluación (BA, 1P, M, WS…). */
class ActivitiesTab extends Component
{
    use ManagesCatalog;

    public ?bool $editing = null;

    public ?int $activityId = null;

    public ?int $number = null;

    public string $code = '';

    public string $description = '';

    public ?int $minutes = null;

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $activity = $id ? $this->findInCatalog(Activity::class, $id) : null;
        $this->activityId = $activity?->id;
        $this->number = $activity?->number ?? (int) $this->catalog(Activity::class)->max('number') + 1;
        $this->code = (string) $activity?->code;
        $this->description = (string) $activity?->description;
        $this->minutes = $activity?->minutes;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->code = Str::upper(trim($this->code));

        $data = $this->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999', $this->uniqueInCatalog('activities', 'number', $this->activityId)],
            'code' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9]+$/', $this->uniqueInCatalog('activities', 'code', $this->activityId)],
            'description' => ['required', 'string', 'max:150'],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
        ], [
            'code.regex' => __('catalogs.validation.code'),
        ], [
            'number' => Str::lower(__('catalogs.fields.activity_number')),
            'code' => Str::lower(__('catalogs.fields.code')),
            'description' => Str::lower(__('catalogs.fields.description')),
            'minutes' => Str::lower(__('catalogs.fields.minutes')),
        ]);

        $activity = $this->activityId ? $this->findInCatalog(Activity::class, $this->activityId) : new Activity([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $activity->fill([...$data, 'description' => trim($data['description'])])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(Activity::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(Activity::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Activity' => Activity::class]);
    }

    /** @return Collection<int, Activity> */
    #[Computed]
    public function activities(): Collection
    {
        return $this->catalog(Activity::class)->orderBy('number')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.activities-tab');
    }
}
