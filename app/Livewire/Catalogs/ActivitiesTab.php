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

    /** Pesos para el balanceo de aulas por tipo de lección (vacío = la actividad no se hace en ese tipo). */
    public ?int $weightLesson = null;

    public ?int $weightCheckpoint = null;

    public ?int $weightVerbInContext = null;

    /** Asesoría uno a uno (PR, presentación): el teacher no recibe a nadie más a esa hora. */
    public bool $isOneToOne = false;

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
        $this->weightLesson = $activity?->weight_lesson;
        $this->weightCheckpoint = $activity?->weight_checkpoint;
        $this->weightVerbInContext = $activity?->weight_verb_in_context;
        $this->isOneToOne = (bool) $activity?->is_one_to_one;
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
            'weightLesson' => ['nullable', 'integer', 'min:0', 'max:999'],
            'weightCheckpoint' => ['nullable', 'integer', 'min:0', 'max:999'],
            'weightVerbInContext' => ['nullable', 'integer', 'min:0', 'max:999'],
            'isOneToOne' => ['boolean'],
        ], [
            'code.regex' => __('catalogs.validation.code'),
        ], [
            'number' => Str::lower(__('catalogs.fields.activity_number')),
            'code' => Str::lower(__('catalogs.fields.code')),
            'description' => Str::lower(__('catalogs.fields.description')),
            'minutes' => Str::lower(__('catalogs.fields.minutes')),
            'weightLesson' => Str::lower(__('catalogs.activities.weight_for', ['type' => __('catalogs.lesson_types.lesson')])),
            'weightCheckpoint' => Str::lower(__('catalogs.activities.weight_for', ['type' => __('catalogs.lesson_types.checkpoint')])),
            'weightVerbInContext' => Str::lower(__('catalogs.activities.weight_for', ['type' => __('catalogs.lesson_types.verb_in_context')])),
        ]);

        $activity = $this->activityId ? $this->findInCatalog(Activity::class, $this->activityId) : new Activity([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $activity->fill([
            'number' => $data['number'], 'code' => $data['code'], 'description' => trim($data['description']), 'minutes' => $data['minutes'],
            'weight_lesson' => $data['weightLesson'], 'weight_checkpoint' => $data['weightCheckpoint'],
            'weight_verb_in_context' => $data['weightVerbInContext'], 'is_one_to_one' => $data['isOneToOne'],
        ])->save();

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
