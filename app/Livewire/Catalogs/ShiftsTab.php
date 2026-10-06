<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\ScheduleSlot;
use App\Models\Shift;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Turnos y horarios". */
class ShiftsTab extends Component
{
    use ManagesCatalog;

    // Turno
    public ?bool $editingShift = null;

    public ?int $shiftId = null;

    public string $shiftName = '';

    // Horario
    public ?bool $editingSlot = null;

    public ?int $slotId = null;

    public ?int $slotNumber = null;

    public string $slotStartsAt = '';

    public string $slotEndsAt = '';

    public ?int $slotShiftId = null;

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function editShift(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $shift = $id ? $this->findInCatalog(Shift::class, $id) : null;
        $this->shiftId = $shift?->id;
        $this->shiftName = (string) $shift?->name;
        $this->editingShift = true;
    }

    public function saveShift(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'shiftName' => ['required', 'string', 'max:60', $this->uniqueInCatalog('shifts', 'name', $this->shiftId)],
        ], attributes: ['shiftName' => Str::lower(__('catalogs.fields.name'))]);

        $shift = $this->shiftId ? $this->findInCatalog(Shift::class, $this->shiftId) : new Shift([
            'school_id' => $this->schoolId,
            'sort_order' => (int) $this->catalog(Shift::class)->max('sort_order') + 10,
            'is_active' => true,
        ]);
        $shift->fill(['name' => trim($data['shiftName'])])->save();

        $this->editingShift = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function editSlot(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $slot = $id ? $this->findInCatalog(ScheduleSlot::class, $id) : null;
        $this->slotId = $slot?->id;
        $this->slotNumber = $slot?->number ?? (int) $this->catalog(ScheduleSlot::class)->max('number') + 1;
        $this->slotStartsAt = $slot ? substr($slot->starts_at, 0, 5) : '';
        $this->slotEndsAt = $slot ? substr($slot->ends_at, 0, 5) : '';
        $this->slotShiftId = $slot?->shift_id;
        $this->editingSlot = true;
    }

    public function saveSlot(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'slotNumber' => ['required', 'integer', 'min:1', 'max:999', $this->uniqueInCatalog('schedule_slots', 'number', $this->slotId)],
            'slotStartsAt' => ['required', 'date_format:H:i'],
            'slotEndsAt' => ['required', 'date_format:H:i', 'after:slotStartsAt'],
            'slotShiftId' => ['required', 'integer', Rule::in($this->catalog(Shift::class)->pluck('id')->all())],
        ], attributes: [
            'slotNumber' => Str::lower(__('catalogs.fields.number')),
            'slotStartsAt' => Str::lower(__('catalogs.fields.starts_at')),
            'slotEndsAt' => Str::lower(__('catalogs.fields.ends_at')),
            'slotShiftId' => Str::lower(__('catalogs.fields.shift')),
        ]);

        $slot = $this->slotId ? $this->findInCatalog(ScheduleSlot::class, $this->slotId) : new ScheduleSlot([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $slot->fill([
            'number' => $data['slotNumber'],
            'starts_at' => $data['slotStartsAt'].':00',
            'ends_at' => $data['slotEndsAt'].':00',
            'shift_id' => $data['slotShiftId'],
        ])->save();

        $this->editingSlot = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggleShift(int $id): void
    {
        $this->toggleActive(Shift::class, $id);
    }

    public function toggleSlot(int $id): void
    {
        $this->toggleActive(ScheduleSlot::class, $id);
    }

    public function confirmShiftDeletion(int $id): void
    {
        $this->askDeletion(Shift::class, $id);
    }

    public function confirmSlotDeletion(int $id): void
    {
        $this->askDeletion(ScheduleSlot::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Shift' => Shift::class, 'ScheduleSlot' => ScheduleSlot::class]);
    }

    /** @return Collection<int, Shift> */
    #[Computed]
    public function shifts(): Collection
    {
        return $this->catalog(Shift::class)->withCount('slots')->orderBy('sort_order')->get();
    }

    /** @return Collection<int, ScheduleSlot> */
    #[Computed]
    public function scheduleSlots(): Collection
    {
        return $this->catalog(ScheduleSlot::class)->with('shift:id,name')->orderBy('number')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.shifts-tab');
    }
}
