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
                                @if ($classroom->is_club_room)
                                    <x-ui.badge variant="info" class="ml-1">{{ __('settings.classrooms.club_room_badge') }}</x-ui.badge>
                                    @if ($schoolId !== null)
                                        <button type="button" wire:click="openClosures({{ $classroom->id }})"
                                                class="ml-1 text-sm font-semibold text-primary-700 underline-offset-2 hover:underline">{{ __('settings.closures.open') }}</button>
                                    @endif
                                @endif
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
        <x-ui.toggle name="isClubRoom" :label="__('settings.classrooms.club_room')" :description="__('settings.classrooms.club_room_hint')" wire:model="isClubRoom" />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Periodos sin disponibilidad del aula audiovisual --}}
    <x-ui.modal wire:model="closuresClassroomId" size="md" :title="__('settings.closures.title')">
        <p class="-mt-2 text-sm text-ink-500">{{ __('settings.closures.hint') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="closureFrom" type="date" :label="__('settings.closures.from')" wire:model="closureFrom" required />
            <x-ui.input name="closureTo" type="date" :label="__('settings.closures.to')" wire:model="closureTo" required />
        </div>
        <x-ui.toggle name="closureAllDay" :label="__('settings.closures.all_day')" wire:model.live="closureAllDay" />
        @unless ($closureAllDay)
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="closureFromSlot" :label="__('settings.closures.from_hour')" :options="$this->slotOptions" :placeholder="__('students.select')" wire:model="closureFromSlot" required />
                <x-ui.select name="closureToSlot" :label="__('settings.closures.to_hour')" :options="$this->slotOptions" :placeholder="__('students.select')" wire:model="closureToSlot" required />
            </div>
        @endunless
        <x-ui.input name="closureReason" :label="__('settings.closures.reason')" wire:model="closureReason" maxlength="255" required />
        <div class="flex justify-end">
            <x-ui.button variant="secondary" wire:click="saveClosure"><x-ui.icon name="plus" class="size-4" />{{ __('settings.closures.add') }}</x-ui.button>
        </div>
        <ul class="divide-y divide-line rounded-lg border border-line">
            @forelse ($this->closures as $closure)
                <li wire:key="closure-{{ $closure->id }}" class="flex items-start gap-3 px-3 py-2">
                    <div class="flex min-w-0 flex-1 flex-col text-sm">
                        <span class="font-semibold tabular-nums">
                            {{ $closure->starts_on->translatedFormat('j M Y') }}@if (! $closure->starts_on->equalTo($closure->ends_on)) – {{ $closure->ends_on->translatedFormat('j M Y') }}@endif
                            · {{ $closure->isAllDay() ? __('settings.closures.all_day') : substr($closure->fromSlot->starts_at, 0, 5).'–'.substr($closure->toSlot->ends_at, 0, 5) }}
                        </span>
                        <span class="text-ink-500 [overflow-wrap:anywhere]">{{ $closure->reason }}</span>
                    </div>
                    <button type="button" wire:click="deleteClosure({{ $closure->id }})" wire:confirm="{{ __('settings.closures.confirm_delete') }}"
                            class="flex size-9 flex-none items-center justify-center rounded-[10px] text-danger-700 hover:bg-danger-50"
                            aria-label="{{ __('settings.closures.delete') }}" title="{{ __('settings.closures.delete') }}"><x-ui.icon name="trash" class="size-4" /></button>
                </li>
            @empty
                <li class="px-3 py-2 text-sm text-ink-500">{{ __('settings.closures.empty') }}</li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.closures.close') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
