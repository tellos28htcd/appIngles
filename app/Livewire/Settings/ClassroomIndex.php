<?php

namespace App\Livewire\Settings;

use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\Classroom;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Configuración → Salones. */
#[Layout('layouts.app')]
class ClassroomIndex extends Component
{
    use ManagesOwnSchool;

    public ?bool $editing = null;

    public ?int $classroomId = null;

    public string $name = '';

    public ?int $capacity = null;

    public string $description = '';

    public function mount(?int $schoolId = null, bool $embedded = false): void
    {
        $this->mountCatalogContext($schoolId, $embedded);
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $classroom = $id ? $this->findInCatalog(Classroom::class, $id) : null;
        $this->classroomId = $classroom?->id;
        $this->name = (string) $classroom?->name;
        $this->capacity = $classroom?->capacity;
        $this->description = (string) $classroom?->description;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueInCatalog('classrooms', 'name', $this->classroomId)],
            'capacity' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'name' => Str::lower(__('settings.fields.name')),
            'capacity' => Str::lower(__('settings.fields.capacity')),
            'description' => Str::lower(__('settings.fields.description')),
        ]);

        $classroom = $this->classroomId ? $this->findInCatalog(Classroom::class, $this->classroomId) : new Classroom([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $classroom->fill([
            'name' => trim($data['name']),
            'capacity' => $data['capacity'],
            'description' => trim((string) $data['description']) ?: null,
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(Classroom::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(Classroom::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Classroom' => Classroom::class]);
    }

    /** @return Collection<int, Classroom> */
    #[Computed]
    public function classrooms(): Collection
    {
        return $this->catalog(Classroom::class)->orderByRaw('CHAR_LENGTH(name)')->orderBy('name')->get();
    }

    protected function catalogModel(): string
    {
        return Classroom::class;
    }

    public function render(): View
    {
        return view('livewire.settings.classroom-index')->title(__('settings.classrooms.title'));
    }
}
