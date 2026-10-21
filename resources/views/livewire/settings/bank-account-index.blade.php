<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('settings.bank_accounts.title')" :subtitle="__('settings.bank_accounts.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.bank_accounts.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="max-w-3xl rounded-xl border border-line bg-white shadow-xs">
        @if ($this->accounts->isEmpty())
            <x-ui.empty-state icon="receipt" :title="__('settings.bank_accounts.empty')" :description="__('settings.bank_accounts.empty_hint')">
                <x-ui.button wire:click="edit">{{ __('settings.bank_accounts.add') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->accounts as $account)
                    <li wire:key="account-{{ $account->id }}" @class(['flex items-center gap-3 px-4 py-3 md:px-5', 'opacity-60' => ! $account->is_active])>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">{{ $account->name }} · {{ $account->bank }}</span>
                            <span class="text-xs text-ink-500 [overflow-wrap:anywhere]">
                                {{ collect([
                                    $account->account_holder,
                                    $account->account_number ? __('settings.bank_accounts.account_short', ['number' => $account->account_number]) : null,
                                    $account->clabe ? 'CLABE '.$account->clabe : null,
                                ])->filter()->implode(' · ') }}
                            </span>
                        </div>
                        <x-catalog.row-actions :active="$account->is_active" :name="$account->name"
                                               edit="edit({{ $account->id }})" toggle="toggle({{ $account->id }})" delete="confirmDeletion({{ $account->id }})" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-ui.modal wire:model="editing" :title="__($accountId ? 'settings.bank_accounts.edit' : 'settings.bank_accounts.add')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="name" :label="__('settings.bank_accounts.name')" :hint="__('settings.bank_accounts.name_hint')" wire:model="name" maxlength="80" required />
            <x-ui.input name="bank" :label="__('settings.bank_accounts.bank')" wire:model="bank" maxlength="80" required />
        </div>
        <x-ui.input name="accountHolder" :label="__('settings.bank_accounts.account_holder')" wire:model="accountHolder" maxlength="200" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="accountNumber" :label="__('settings.bank_accounts.account_number')" wire:model="accountNumber" maxlength="30" inputmode="numeric" class="font-mono tabular-nums" />
            <x-ui.input name="clabe" label="CLABE" wire:model="clabe" maxlength="18" inputmode="numeric" class="font-mono tabular-nums" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
