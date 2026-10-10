<?php

namespace App\Livewire\Settings;

use App\Enums\ChargeConceptType;
use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\ChargeConcept;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Configuración → Conceptos de cobro (nombre, tipo y monto sugerido). */
#[Layout('layouts.app')]
class ChargeConceptIndex extends Component
{
    use ManagesOwnSchool;

    public ?bool $editing = null;

    public ?int $conceptId = null;

    public string $name = '';

    public string $type = 'tuition';

    public string $suggestedAmount = '';

    public function mount(?int $schoolId = null, bool $embedded = false): void
    {
        $this->mountCatalogContext($schoolId, $embedded);
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $concept = $id ? $this->findInCatalog(ChargeConcept::class, $id) : null;
        $this->conceptId = $concept?->id;
        $this->name = (string) $concept?->name;
        $this->type = $concept?->type->value ?? ChargeConceptType::Tuition->value;
        $this->suggestedAmount = (string) $concept?->suggested_amount;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->suggestedAmount = str_replace([',', '$', ' '], '', $this->suggestedAmount);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueInCatalog('charge_concepts', 'name', $this->conceptId)],
            'type' => ['required', Rule::enum(ChargeConceptType::class)],
            'suggestedAmount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ], attributes: [
            'name' => Str::lower(__('settings.fields.name')),
            'type' => Str::lower(__('settings.fields.type')),
            'suggestedAmount' => Str::lower(__('settings.fields.suggested_amount')),
        ]);

        $concept = $this->conceptId ? $this->findInCatalog(ChargeConcept::class, $this->conceptId) : new ChargeConcept([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $concept->fill([
            'name' => trim($data['name']),
            'type' => $data['type'],
            'suggested_amount' => $data['suggestedAmount'] === '' || $data['suggestedAmount'] === null ? null : $data['suggestedAmount'],
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(ChargeConcept::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(ChargeConcept::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['ChargeConcept' => ChargeConcept::class]);
    }

    /** @return Collection<int, ChargeConcept> */
    #[Computed]
    public function concepts(): Collection
    {
        return $this->catalog(ChargeConcept::class)->orderBy('type')->orderBy('name')->get();
    }

    protected function catalogModel(): string
    {
        return ChargeConcept::class;
    }

    public function render(): View
    {
        return view('livewire.settings.charge-concept-index', [
            'types' => collect(ChargeConceptType::cases())->mapWithKeys(fn (ChargeConceptType $type) => [$type->value => $type->label()])->all(),
            'currency' => auth()->user()->school?->currency ?? 'MXN',
        ])->title(__('settings.charge_concepts.title'));
    }
}
