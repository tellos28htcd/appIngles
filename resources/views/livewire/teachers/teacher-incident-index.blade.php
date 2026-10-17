<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('availability.incidents_title')" :subtitle="__('availability.incidents_subtitle')">
        <x-slot:actions>
            @if ($canManage)
                <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('availability.incident_add') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        <div class="grid gap-3 border-b border-line p-4 sm:grid-cols-3">
            <x-ui.select name="teacher" :label="__('availability.incident_fields.teacher')" :options="$teacherOptions" :placeholder="__('availability.all_teachers')" wire:model.live="teacher" sr-only-label />
            <x-ui.select name="type" :label="__('availability.incident_fields.type')" :options="$typeOptions" :placeholder="__('availability.all_types')" wire:model.live="type" sr-only-label />
            <div class="flex items-center gap-2">
                <label for="incidents-month" class="w-14 flex-none text-sm font-semibold text-ink-700">{{ __('availability.month') }}</label>
                <input id="incidents-month" type="month" wire:model.live="month"
                       class="h-11 w-full min-w-0 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->incidents->isEmpty())
                <x-ui.empty-state icon="calendar" :title="__('availability.incidents_empty')" :description="__('availability.incidents_empty_hint')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->incidents as $incident)
                        <li wire:key="incident-{{ $incident->id }}" class="flex flex-col gap-2 p-4 md:flex-row md:items-center md:gap-4 md:px-5">
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <span class="flex flex-wrap items-center gap-2">
                                    <span class="font-bold">{{ $incident->teacher->fullName() }}</span>
                                    <x-ui.badge :variant="$incident->type->badge()">{{ $incident->type->label() }}</x-ui.badge>
                                </span>
                                @if ($incident->coveredTeacher)
                                    <span class="text-sm text-ink-700">{{ __('availability.covers_to', ['teacher' => $incident->coveredTeacher->panelName()]) }}</span>
                                @endif
                                <span class="text-sm text-ink-500 [overflow-wrap:anywhere]">{{ $incident->reason }}</span>
                            </div>
                            <div class="flex flex-col text-sm tabular-nums md:w-64 md:flex-none">
                                <span class="font-semibold">
                                    {{ $incident->starts_on->translatedFormat('D j M Y') }}@if (! $incident->starts_on->equalTo($incident->ends_on)) – {{ $incident->ends_on->translatedFormat('D j M Y') }}@endif
                                </span>
                                <span class="text-ink-500">
                                    {{ $incident->fromSlot ? substr($incident->fromSlot->starts_at, 0, 5).' – '.substr($incident->toSlot->ends_at, 0, 5) : __('availability.all_day') }}
                                </span>
                            </div>
                            @if ($canManage)
                                <div class="flex items-center justify-end gap-0.5">
                                    @php($button = 'flex size-9 items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
                                    <button type="button" wire:click="edit({{ $incident->id }})" class="{{ $button }} text-primary-700 hover:bg-primary-50"
                                            aria-label="{{ __('availability.incident_edit') }}" title="{{ __('availability.incident_edit') }}"><x-ui.icon name="pencil" class="size-4" /></button>
                                    <button type="button" wire:click="confirmDeletion({{ $incident->id }})" class="{{ $button }} text-danger-700 hover:bg-danger-50"
                                            aria-label="{{ __('availability.incident_delete') }}" title="{{ __('availability.incident_delete') }}"><x-ui.icon name="trash" class="size-4" /></button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($this->incidents->hasPages())
            <div class="border-t border-line p-4">{{ $this->incidents->links('partials.pagination') }}</div>
        @endif
    </section>

    @if ($canManage)
        <x-ui.modal wire:model="editing" size="md" :title="__($incidentId ? 'availability.incident_edit' : 'availability.incident_add')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.select name="teacherId" :label="__('availability.incident_fields.teacher')" :options="$teacherOptions" :placeholder="__('students.select')" wire:model="teacherId" required />
                <x-ui.select name="incidentType" :label="__('availability.incident_fields.type')" :options="$typeOptions" wire:model.live="incidentType" required />
            </div>
            @if ($incidentType === 'covers')
                <x-ui.select name="coveredTeacherId" :label="__('availability.incident_fields.covered_teacher')" :options="$teacherOptions"
                             :placeholder="__('students.select')" :hint="__('availability.covers_hint')" wire:model="coveredTeacherId" required />
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="startsOn" type="date" :label="__('availability.incident_fields.starts_on')" wire:model="startsOn" required />
                <x-ui.input name="endsOn" type="date" :label="__('availability.incident_fields.ends_on')" wire:model="endsOn" required />
            </div>
            <x-ui.toggle name="allDay" :label="__('availability.all_day')" :description="__('availability.all_day_hint')" wire:model.live="allDay" />
            @unless ($allDay)
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select name="fromSlotId" :label="__('availability.incident_fields.from')" :options="$slotOptions" :placeholder="__('students.select')" wire:model="fromSlotId" required />
                    <x-ui.select name="toSlotId" :label="__('availability.incident_fields.to')" :options="$slotOptions" :placeholder="__('students.select')" wire:model="toSlotId" required />
                </div>
            @endunless
            <x-ui.textarea name="reason" :label="__('availability.incident_fields.reason')" wire:model="reason" rows="3" maxlength="1000" required />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="save">{{ __('availability.incident_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal wire:model="confirmingDeletionId" :title="__('availability.incident_delete_title')">
            <p>{{ __('availability.incident_delete_body') }}</p>
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="delete">{{ __('availability.incident_delete') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
