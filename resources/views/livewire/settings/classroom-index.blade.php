<div class="flex flex-col gap-6">
    @unless ($embedded)
    <x-ui.page-header :title="__('settings.classrooms.title')" :subtitle="__('settings.classrooms.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.classrooms.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @else
        <div class="flex justify-end">
            <x-ui.button variant="secondary" wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.classrooms.add') }}</x-ui.button>
        </div>
    @endunless

    @include('livewire.settings.partials.base-updates')

    <section class="max-w-4xl rounded-xl border border-line bg-white shadow-xs">
        @if ($this->classrooms->isEmpty())
            <x-ui.empty-state icon="building" :title="__('settings.classrooms.empty')" :description="__('settings.classrooms.empty_hint')">
                <x-ui.button wire:click="edit">{{ __('settings.classrooms.add') }}</x-ui.button>
            </x-ui.empty-state>
        @else
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-bold md:px-5">{{ __('settings.fields.name') }}</th>
                        <th scope="col" class="w-28 px-3 py-3 text-right font-bold">{{ __('settings.fields.capacity') }}</th>
                        <th scope="col" class="px-4 py-3 md:px-5"><span class="sr-only">{{ __('settings.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->classrooms as $classroom)
                        <tr wire:key="classroom-{{ $classroom->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $classroom->is_active])>
                            <td class="px-4 py-2.5 md:px-5">
                                <span class="font-semibold">{{ $classroom->name }}</span>
                                @if ($classroom->description)
                                    <span class="block text-xs text-ink-500">{{ $classroom->description }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right tabular-nums">{{ trans_choice('settings.classrooms.people', $classroom->capacity) }}</td>
                            <td class="w-px px-4 py-2.5 md:px-5">
                                <x-catalog.row-actions :active="$classroom->is_active" :name="$classroom->name"
                                                       edit="edit({{ $classroom->id }})" toggle="toggle({{ $classroom->id }})" delete="confirmDeletion({{ $classroom->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <x-ui.modal wire:model="editing" :title="__($classroomId ? 'settings.classrooms.edit' : 'settings.classrooms.add')">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_140px]">
            <x-ui.input name="name" :label="__('settings.fields.name')" wire:model="name" maxlength="80" required />
            <x-ui.input name="capacity" type="number" :label="__('settings.fields.capacity')" wire:model="capacity" min="1" max="999" class="tabular-nums" required />
        </div>
        <p class="-mt-2 text-[13px] text-ink-500">{{ __('settings.classrooms.capacity_hint') }}</p>
        <x-ui.input name="description" :label="__('settings.fields.description')" :hint="__('settings.classrooms.description_hint')" wire:model="description" maxlength="255" />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
