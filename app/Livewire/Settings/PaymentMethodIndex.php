<?php

namespace App\Livewire\Settings;

use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\PaymentMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Configuración → Métodos de pago (cada escuela los captura desde cero). */
#[Layout('layouts.app')]
class PaymentMethodIndex extends Component
{
    use ManagesOwnSchool;

    public ?bool $editing = null;

    public ?int $methodId = null;

    public string $name = '';

    public bool $requiresReference = false;

    public function mount(?int $schoolId = null, bool $embedded = false): void
    {
        $this->mountCatalogContext($schoolId, $embedded);
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $method = $id ? $this->findInCatalog(PaymentMethod::class, $id) : null;
        $this->methodId = $method?->id;
        $this->name = (string) $method?->name;
        $this->requiresReference = (bool) $method?->requires_reference;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueInCatalog('payment_methods', 'name', $this->methodId)],
            'requiresReference' => ['boolean'],
        ], attributes: ['name' => Str::lower(__('settings.fields.name'))]);

        $method = $this->methodId ? $this->findInCatalog(PaymentMethod::class, $this->methodId) : new PaymentMethod([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $method->fill(['name' => trim($data['name']), 'requires_reference' => $data['requiresReference']])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(PaymentMethod::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(PaymentMethod::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['PaymentMethod' => PaymentMethod::class]);
    }

    /** @return Collection<int, PaymentMethod> */
    #[Computed]
    public function methods(): Collection
    {
        return $this->catalog(PaymentMethod::class)->orderBy('name')->get();
    }

    protected function catalogModel(): string
    {
        return PaymentMethod::class;
    }

    public function render(): View
    {
        return view('livewire.settings.payment-method-index')->title(__('settings.payment_methods.title'));
    }
}
