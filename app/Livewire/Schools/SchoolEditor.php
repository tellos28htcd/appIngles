<?php

namespace App\Livewire\Schools;

use App\Livewire\Forms\SchoolForm;
use App\Models\School;
use App\Models\State;
use App\Support\BrandColor;
use DateTimeZone;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Alta y edición de una escuela (solo Super Admin). */
#[Layout('layouts.app')]
class SchoolEditor extends Component
{
    use WithFileUploads;

    public SchoolForm $form;

    public function mount(?School $school = null): void
    {
        if ($school?->exists) {
            $this->authorize('update', $school);
            $this->form->setSchool($school);
        } else {
            $this->authorize('create', School::class);
        }
    }

    public function updatedFormStateId(): void
    {
        $this->form->municipality_id = null;
    }

    public function save(): void
    {
        $this->form->school
            ? $this->authorize('update', $this->form->school)
            : $this->authorize('create', School::class);

        $this->form->save();

        session()->flash('toast', ['type' => 'success', 'message' => __('schools.saved')]);

        $this->redirectRoute('schools.index', navigate: true);
    }

    /** @return array<int, string> */
    #[Computed]
    public function states(): array
    {
        return State::orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    #[Computed]
    public function municipalities(): array
    {
        return $this->form->municipalityOptions();
    }

    #[Computed]
    public function primaryContrast(): ?float
    {
        return preg_match('/^#[0-9A-Fa-f]{6}$/', $this->form->brand_primary)
            ? BrandColor::contrastOnWhite($this->form->brand_primary)
            : null;
    }

    public function render(): View
    {
        $editing = $this->form->school !== null;

        return view('livewire.schools.school-editor', [
            'editing' => $editing,
            'timezones' => collect(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX'))
                ->mapWithKeys(fn (string $zone) => [$zone => str_replace(['America/', '_'], ['', ' '], $zone)])
                ->all(),
            'logoPreview' => $this->form->logo?->isPreviewable() ? $this->form->logo->temporaryUrl() : null,
        ])->title(__($editing ? 'schools.edit_title' : 'schools.create_title'));
    }
}
