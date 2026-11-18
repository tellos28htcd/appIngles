<?php

namespace App\Livewire\Catalogs;

use App\Enums\GradeType;
use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /** Calificación del Kardex: firma (F) o A/B/C. */
    public string $gradeType = 'score';

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
        $this->gradeType = ($activity?->grade_type ?? GradeType::forActivityNumber($this->number))->value;
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
            'gradeType' => ['required', Rule::enum(GradeType::class)],
        ], [
            'code.regex' => __('catalogs.validation.code'),
        ], [
            'number' => Str::lower(__('catalogs.fields.activity_number')),
            'code' => Str::lower(__('catalogs.fields.code')),
            'description' => Str::lower(__('catalogs.fields.description')),
            'minutes' => Str::lower(__('catalogs.fields.minutes')),
            'gradeType' => Str::lower(__('catalogs.fields.grade_type')),
        ]);

        $activity = $this->activityId ? $this->findInCatalog(Activity::class, $this->activityId) : new Activity([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $activity->fill([
            'number' => $data['number'], 'code' => $data['code'], 'description' => trim($data['description']), 'minutes' => $data['minutes'], 'grade_type' => $data['gradeType'],
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
        return view('livewire.catalogs.activities-tab', [
            'gradeTypes' => collect(GradeType::cases())->mapWithKeys(fn (GradeType $type) => [$type->value => $type->label()])->all(),
        ]);
    }
}
