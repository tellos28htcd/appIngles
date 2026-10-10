<div class="flex flex-col gap-6">
    @unless ($embedded)
    <x-ui.page-header :title="__('settings.payment_methods.title')" :subtitle="__('settings.payment_methods.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.payment_methods.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @else
        <div class="flex justify-end">
            <x-ui.button variant="secondary" wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.payment_methods.add') }}</x-ui.button>
        </div>
    @endunless

    @include('livewire.settings.partials.base-updates')

    <section class="max-w-3xl rounded-xl border border-line bg-white shadow-xs">
        @if ($this->methods->isEmpty())
            <x-ui.empty-state icon="receipt" :title="__('settings.payment_methods.empty')" :description="__('settings.payment_methods.empty_hint')">
                <x-ui.button wire:click="edit">{{ __('settings.payment_methods.add') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->methods as $method)
                    <li wire:key="method-{{ $method->id }}" @class(['flex items-center gap-3 px-4 py-3 md:px-5', 'opacity-60' => ! $method->is_active])>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold">{{ $method->name }}</span>
                            @if ($method->requires_reference)
                                <span class="text-xs text-ink-500">{{ __('settings.payment_methods.requires_reference_short') }}</span>
                            @endif
                        </div>
                        <x-catalog.row-actions :active="$method->is_active" :name="$method->name"
                                               edit="edit({{ $method->id }})" toggle="toggle({{ $method->id }})" delete="confirmDeletion({{ $method->id }})" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-ui.modal wire:model="editing" :title="__($methodId ? 'settings.payment_methods.edit' : 'settings.payment_methods.add')">
        <x-ui.input name="name" :label="__('settings.fields.name')" :hint="__('settings.payment_methods.name_hint')" wire:model="name" maxlength="80" required />
        <x-ui.toggle name="requiresReference" :label="__('settings.payment_methods.requires_reference')"
                     :description="__('settings.payment_methods.requires_reference_hint')" wire:model="requiresReference" />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
