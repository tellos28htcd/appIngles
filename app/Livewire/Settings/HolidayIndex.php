<?php

namespace App\Livewire\Settings;

use App\Actions\Settings\EnsureOfficialHolidays;
use App\Enums\HolidayType;
use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\Holiday;
use App\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Configuración → Días festivos: los oficiales de la LFT se generan solos
 * cada año (se pueden desactivar); la escuela agrega sus días o periodos.
 */
#[Layout('layouts.app')]
class HolidayIndex extends Component
{
    use ManagesOwnSchool;

    #[Url(as: 'anio')]
    public int $year = 0;

    public ?bool $editing = null;

    public ?int $holidayId = null;

    public string $name = '';

    public string $startsOn = '';

    public string $endsOn = '';

    public function mount(EnsureOfficialHolidays $ensureOfficial, ?int $schoolId = null, bool $embedded = false): void
    {
        if (! $this->mountCatalogContext($schoolId, $embedded)) {
            return;
        }

        $this->year = $this->validYear($this->year ?: (int) now()->year);
        $this->ensureOfficial($ensureOfficial);
    }

    /** Los festivos oficiales son de cada escuela; el catálogo base solo tiene días propios. */
    private function ensureOfficial(EnsureOfficialHolidays $ensureOfficial): void
    {
        if ($this->schoolId !== null) {
            $ensureOfficial->handle(School::findOrFail($this->schoolId), $this->year);
        }
    }

    public function changeYear(int $offset, EnsureOfficialHolidays $ensureOfficial): void
    {
        $this->authorizeCatalog();

        $this->year = $this->validYear($this->year + $offset);
        $this->ensureOfficial($ensureOfficial);
        unset($this->holidays);
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $holiday = $id ? $this->findSchoolHoliday($id) : null;
        $this->holidayId = $holiday?->id;
        $this->name = (string) $holiday?->name;
        $this->startsOn = $holiday?->starts_on->toDateString() ?? '';
        $this->endsOn = $holiday?->ends_on->toDateString() ?? '';
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->endsOn = $this->endsOn ?: $this->startsOn;

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'startsOn' => ['required', 'date_format:Y-m-d'],
            'endsOn' => ['required', 'date_format:Y-m-d', 'after_or_equal:startsOn', 'before_or_equal:'.$this->maxEnd()],
        ], [
            'endsOn.before_or_equal' => __('settings.holidays.max_range'),
        ], [
            'name' => Str::lower(__('settings.fields.name')),
            'startsOn' => Str::lower(__('settings.fields.starts_on')),
            'endsOn' => Str::lower(__('settings.fields.ends_on')),
        ]);

        $holiday = $this->holidayId ? $this->findSchoolHoliday($this->holidayId) : new Holiday([
            'school_id' => $this->schoolId,
            'type' => HolidayType::School,
            'is_active' => true,
        ]);
        $holiday->fill(['name' => trim($data['name']), 'starts_on' => $data['startsOn'], 'ends_on' => $data['endsOn']])->save();

        $this->editing = null;
        unset($this->holidays);
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(Holiday::class, $id);
        unset($this->holidays);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(Holiday::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Holiday' => Holiday::class]);
        unset($this->holidays);
    }

    /** @return Collection<int, Holiday> */
    #[Computed]
    public function holidays(): Collection
    {
        return $this->catalog(Holiday::class)->inYear($this->year)->orderBy('starts_on')->get();
    }

    /** Los días oficiales no se editan: solo se activan o desactivan. */
    private function findSchoolHoliday(int $id): Holiday
    {
        return $this->catalog(Holiday::class)->where('type', HolidayType::School)->findOrFail($id);
    }

    private function maxEnd(): string
    {
        return $this->startsOn !== '' && strtotime($this->startsOn)
            ? Carbon::parse($this->startsOn)->addDays(365)->toDateString()
            : '9999-12-31';
    }

    private function validYear(int $year): int
    {
        return max(2000, min(2100, $year));
    }

    protected function catalogModel(): string
    {
        return Holiday::class;
    }

    public function render(): View
    {
        return view('livewire.settings.holiday-index', [
            'officialCount' => $this->holidays->where('type', HolidayType::Official)->count(),
        ])->title(__('settings.holidays.title'));
    }
}
