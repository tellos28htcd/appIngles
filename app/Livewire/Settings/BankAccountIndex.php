<?php

namespace App\Livewire\Settings;

use App\Livewire\Settings\Concerns\ManagesOwnSchool;
use App\Models\BankAccount;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Configuración → Cuentas bancarias: cuentas de la escuela donde los alumnos
 * depositan. Se eligen en la ficha de inscripción ("Cuenta bancaria de pago").
 * Cada escuela captura las suyas (no hay catálogo base).
 */
#[Layout('layouts.app')]
class BankAccountIndex extends Component
{
    use ManagesOwnSchool;

    public ?bool $editing = null;

    public ?int $accountId = null;

    public string $name = '';

    public string $bank = '';

    public string $accountHolder = '';

    public string $accountNumber = '';

    public string $clabe = '';

    public function mount(): void
    {
        $this->mountOwnSchool();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $account = $id ? $this->findInCatalog(BankAccount::class, $id) : null;
        $this->accountId = $account?->id;
        $this->name = (string) $account?->name;
        $this->bank = (string) $account?->bank;
        $this->accountHolder = (string) $account?->account_holder;
        $this->accountNumber = (string) $account?->account_number;
        $this->clabe = (string) $account?->clabe;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->accountNumber = preg_replace('/\s+/', '', $this->accountNumber);
        $this->clabe = preg_replace('/\s+/', '', $this->clabe);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:80', $this->uniqueInCatalog('bank_accounts', 'name', $this->accountId)],
            'bank' => ['required', 'string', 'max:80'],
            'accountHolder' => ['nullable', 'string', 'max:200'],
            'accountNumber' => ['nullable', 'regex:/^\d{4,30}$/'],
            'clabe' => ['nullable', 'digits:18'],
        ], [
            'accountNumber.regex' => __('settings.bank_accounts.account_number_invalid'),
        ], [
            'name' => Str::lower(__('settings.bank_accounts.name')),
            'bank' => Str::lower(__('settings.bank_accounts.bank')),
            'accountHolder' => Str::lower(__('settings.bank_accounts.account_holder')),
            'accountNumber' => Str::lower(__('settings.bank_accounts.account_number')),
            'clabe' => 'CLABE',
        ]);

        $account = $this->accountId ? $this->findInCatalog(BankAccount::class, $this->accountId) : new BankAccount([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $account->fill([
            'name' => trim($data['name']),
            'bank' => trim($data['bank']),
            'account_holder' => trim($data['accountHolder']) ?: null,
            'account_number' => $data['accountNumber'] ?: null,
            'clabe' => $data['clabe'] ?: null,
        ])->save();

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('settings.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(BankAccount::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(BankAccount::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['BankAccount' => BankAccount::class]);
    }

    /** @return Collection<int, BankAccount> */
    #[Computed]
    public function accounts(): Collection
    {
        return $this->catalog(BankAccount::class)->orderBy('name')->get();
    }

    protected function catalogModel(): string
    {
        return BankAccount::class;
    }

    public function render(): View
    {
        return view('livewire.settings.bank-account-index')->title(__('settings.bank_accounts.title'));
    }
}
