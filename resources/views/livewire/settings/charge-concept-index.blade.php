<div class="flex flex-col gap-6">
    @unless ($embedded)
    <x-ui.page-header :title="__('settings.charge_concepts.title')" :subtitle="__('settings.charge_concepts.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.charge_concepts.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @else
        <div class="flex justify-end">
            <x-ui.button variant="secondary" wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.charge_concepts.add') }}</x-ui.button>
        </div>
    @endunless

    @include('livewire.settings.partials.base-updates')

    <section class="max-w-4xl rounded-xl border border-line bg-white shadow-xs">
        @if ($this->concepts->isEmpty())
            <x-ui.empty-state icon="receipt" :title="__('settings.charge_concepts.empty')" :description="__('settings.charge_concepts.empty_hint')">
                <x-ui.button wire:click="edit">{{ __('settings.charge_concepts.add') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold md:px-5">{{ __('settings.fields.concept') }}</th>
                        <th scope="col" class="hidden px-3 py-3 font-bold sm:table-cell">{{ __('settings.fields.type') }}</th>
                        <th scope="col" class="px-3 py-3 text-right font-bold">{{ __('settings.fields.suggested_amount') }}</th>
                        <th scope="col" class="px-4 py-3 md:px-5"><span class="sr-only">{{ __('settings.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->concepts as $concept)
                        <tr wire:key="concept-{{ $concept->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $concept->is_active])>
                            <td class="px-4 py-2.5 md:px-5">
                                <span class="font-semibold">{{ $concept->name }}</span>
                                <span class="block text-xs text-ink-500 sm:hidden">{{ $concept->type->label() }}</span>
                            </td>
                            <td class="hidden px-3 py-2.5 sm:table-cell"><x-ui.badge variant="neutral">{{ $concept->type->label() }}</x-ui.badge></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold tabular-nums">
                                {{ $concept->suggested_amount !== null ? '$'.number_format((float) $concept->suggested_amount, 2).' '.$currency : '—' }}
                            </td>
                            <td class="w-px px-4 py-2.5 md:px-5">
                                <x-catalog.row-actions :active="$concept->is_active" :name="$concept->name"
                                                       edit="edit({{ $concept->id }})" toggle="toggle({{ $concept->id }})" delete="confirmDeletion({{ $concept->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <x-ui.modal wire:model="editing" :title="__($conceptId ? 'settings.charge_concepts.edit' : 'settings.charge_concepts.add')">
        <x-ui.input name="name" :label="__('settings.fields.name')" :hint="__('settings.charge_concepts.name_hint')" wire:model="name" maxlength="120" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.select name="type" :label="__('settings.fields.type')" :options="$types" wire:model="type" required />
            <x-ui.input name="suggestedAmount" type="text" inputmode="decimal" :label="__('settings.fields.suggested_amount').' ('.$currency.')'"
                        :hint="__('settings.charge_concepts.amount_hint')" wire:model="suggestedAmount" class="tabular-nums" placeholder="0.00" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
