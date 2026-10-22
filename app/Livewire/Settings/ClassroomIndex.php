<?php

namespace App\Livewire\Settings;

use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\Classroom;
use App\Models\ClassroomClosure;
use App\Models\ClubSession;
use App\Models\ScheduleSlot;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Configuración → Aulas. Un aula marcada como "audiovisual (para clubs)" es la que se
 * usa en la programación de clubs; sus periodos sin disponibilidad (fechas, todo el día
 * o de una hora a otra) impiden programar clubs en ella.
 */
#[Layout('layouts.app')]
class ClassroomIndex extends Component
{
    use ManagesOwnSchool;

    public ?bool $editing = null;

    public ?int $classroomId = null;

    public string $name = '';

    public ?int $capacity = null;

    public string $description = '';

    public bool $isClubRoom = false;

    /** Aula audiovisual cuyos periodos sin disponibilidad se ven. */
    public ?int $closuresClassroomId = null;

    public string $closureFrom = '';

    public string $closureTo = '';

    public bool $closureAllDay = true;

    public ?int $closureFromSlot = null;

    public ?int $closureToSlot = null;

    public string $closureReason = '';

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
        $this->isClubRoom = (bool) $classroom?->is_club_room;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueInCatalog('classrooms', 'name', $this->classroomId)],
            'capacity' => ['required', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string', 'max:255'],
            'isClubRoom' => ['boolean'],
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
            'is_club_room' => $data['isClubRoom'],
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(Classroom::class, $id);
    }

    public function openClosures(int $id): void
    {
        $this->authorizeCatalog();
        abort_if($this->schoolId === null, 404);
        $this->resetValidation();

        $this->closuresClassroomId = $this->findInCatalog(Classroom::class, $id)->id;
        $this->closureFrom = $this->closureTo = now(config('appingles.timezone'))->toDateString();
        $this->closureAllDay = true;
        $this->reset('closureFromSlot', 'closureToSlot', 'closureReason');
    }

    public function saveClosure(): void
    {
        $this->authorizeCatalog();
        $classroom = $this->findInCatalog(Classroom::class, $this->closuresClassroomId);
        $inSchool = Rule::exists('schedule_slots', 'id')->where('school_id', $this->schoolId);

        $this->validate([
            'closureFrom' => ['required', 'date'],
            'closureTo' => ['required', 'date', 'after_or_equal:closureFrom'],
            'closureFromSlot' => [Rule::requiredIf(! $this->closureAllDay), 'nullable', 'integer', $inSchool],
            'closureToSlot' => [Rule::requiredIf(! $this->closureAllDay), 'nullable', 'integer', $inSchool],
            'closureReason' => ['required', 'string', 'max:255'],
        ], attributes: collect(['closureFrom' => 'from', 'closureTo' => 'to', 'closureFromSlot' => 'from_hour', 'closureToSlot' => 'to_hour', 'closureReason' => 'reason'])
            ->map(fn (string $key) => Str::lower(__("settings.closures.$key")))->all());

        $closure = ClassroomClosure::create([
            'school_id' => $this->schoolId,
            'classroom_id' => $classroom->id,
            'starts_on' => $this->closureFrom,
            'ends_on' => $this->closureTo,
            'from_slot_id' => $this->closureAllDay ? null : $this->closureFromSlot,
            'to_slot_id' => $this->closureAllDay ? null : $this->closureToSlot,
            'reason' => trim($this->closureReason),
            'user_id' => auth()->id(),
        ])->load(['fromSlot', 'toSlot']);

        if (! $closure->isAllDay() && $closure->fromSlot->starts_at > $closure->toSlot->starts_at) {
            $closure->delete();
            throw ValidationException::withMessages(['closureToSlot' => __('settings.closures.slot_order')]);
        }

        // Clubs ya programados que quedan dentro del periodo: se avisan para moverlos.
        $affected = ClubSession::query()->where('classroom_id', $classroom->id)
            ->whereBetween('date', [$closure->starts_on->toDateString(), $closure->ends_on->toDateString()])->with('slot')->get()
            ->filter(fn (ClubSession $session) => $closure->covers($session->date, $session->slot))->count();

        $this->reset('closureReason');
        unset($this->closures);
        $this->dispatch('toast', ...($affected > 0
            ? ['type' => 'warning', 'message' => trans_choice('settings.closures.affected', $affected)]
            : ['type' => 'success', 'message' => __('settings.closures.saved')]));
    }

    public function deleteClosure(int $id): void
    {
        $this->authorizeCatalog();
        ClassroomClosure::query()->where('school_id', $this->schoolId)->findOrFail($id)->delete();
        unset($this->closures);
        $this->dispatch('toast', type: 'success', message: __('settings.closures.deleted'));
    }

    /** @return Collection<int, ClassroomClosure> */
    #[Computed]
    public function closures(): Collection
    {
        return $this->closuresClassroomId === null ? new Collection : ClassroomClosure::query()->where('school_id', $this->schoolId)
            ->where('classroom_id', $this->closuresClassroomId)->with(['fromSlot', 'toSlot'])->latest('starts_on')->get();
    }

    /** @return array<int, string> */
    #[Computed]
    public function slotOptions(): array
    {
        return $this->schoolId === null ? [] : ScheduleSlot::query()->where('school_id', $this->schoolId)->where('is_active', true)
            ->orderBy('starts_at')->get()->mapWithKeys(fn (ScheduleSlot $slot) => [$slot->id => $slot->range()])->all();
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
