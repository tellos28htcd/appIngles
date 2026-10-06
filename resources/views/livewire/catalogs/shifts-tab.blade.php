<div class="grid gap-4 md:gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
    {{-- Turnos --}}
    <x-catalog.section :title="__('catalogs.shifts.title')" :description="__('catalogs.shifts.description')"
                       add="editShift" :add-label="__('catalogs.shifts.add')" class="self-start">
        <ul class="divide-y divide-line">
            @forelse ($this->shifts as $shift)
                <li wire:key="shift-{{ $shift->id }}" @class(['flex items-center gap-3 px-4 py-2.5 md:px-5', 'opacity-60' => ! $shift->is_active])>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate font-bold">{{ $shift->name }}</span>
                        <span class="text-xs text-ink-500">{{ trans_choice('catalogs.shifts.slots_count', $shift->slots_count) }}</span>
                    </div>
                    <x-catalog.row-actions :active="$shift->is_active" :name="$shift->name"
                                           edit="editShift({{ $shift->id }})" toggle="toggleShift({{ $shift->id }})" delete="confirmShiftDeletion({{ $shift->id }})" />
                </li>
            @empty
                <li><x-ui.empty-state icon="calendar" :title="__('catalogs.empty')" /></li>
            @endforelse
        </ul>
    </x-catalog.section>

    {{-- Horarios --}}
    <x-catalog.section :title="__('catalogs.slots.title')" :description="__('catalogs.slots.description')"
                       add="editSlot" :add-label="__('catalogs.slots.add')">
        @if ($this->scheduleSlots->isEmpty())
            <x-ui.empty-state icon="calendar" :title="__('catalogs.empty')" />
        @else
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="w-16 px-4 py-2.5 text-right font-bold md:px-5">{{ __('catalogs.fields.number_short') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-bold">{{ __('catalogs.fields.schedule') }}</th>
                        <th scope="col" class="hidden px-3 py-2.5 font-bold sm:table-cell">{{ __('catalogs.fields.shift') }}</th>
                        <th scope="col" class="px-4 py-2.5 text-right font-bold md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->scheduleSlots as $slot)
                        <tr wire:key="slot-{{ $slot->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $slot->is_active])>
                            <td class="px-4 py-2 text-right font-mono text-sm tabular-nums md:px-5">{{ $slot->number }}</td>
                            <td class="px-3 py-2 font-semibold tabular-nums">
                                {{ $slot->range() }}
                                <span class="block text-xs font-normal text-ink-500 sm:hidden">{{ $slot->shift->name }}</span>
                            </td>
                            <td class="hidden px-3 py-2 text-sm text-ink-700 sm:table-cell">{{ $slot->shift->name }}</td>
                            <td class="px-4 py-2 md:px-5">
                                <x-catalog.row-actions :active="$slot->is_active" :name="$slot->range()"
                                                       edit="editSlot({{ $slot->id }})" toggle="toggleSlot({{ $slot->id }})" delete="confirmSlotDeletion({{ $slot->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editingShift" :title="__($shiftId ? 'catalogs.shifts.edit' : 'catalogs.shifts.add')">
        <x-ui.input name="shiftName" :label="__('catalogs.fields.name')" wire:model="shiftName" maxlength="60" required />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="saveShift">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="editingSlot" :title="__($slotId ? 'catalogs.slots.edit' : 'catalogs.slots.add')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.input name="slotNumber" type="number" :label="__('catalogs.fields.number')" wire:model="slotNumber" min="1" class="tabular-nums" required />
            <x-ui.input name="slotStartsAt" type="time" :label="__('catalogs.fields.starts_at')" wire:model="slotStartsAt" required />
            <x-ui.input name="slotEndsAt" type="time" :label="__('catalogs.fields.ends_at')" wire:model="slotEndsAt" required />
        </div>
        <x-ui.select name="slotShiftId" :label="__('catalogs.fields.shift')" :options="$this->shifts->pluck('name', 'id')->all()"
                     :placeholder="__('catalogs.fields.select_shift')" wire:model="slotShiftId" required />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="saveSlot">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
