<div>
    <x-catalog.section :title="__('catalogs.coordination.title')" :description="__('catalogs.coordination.description')"
                       add="edit" :add-label="__('catalogs.coordination.add')">
        @if ($this->activities->isEmpty())
            <x-ui.empty-state icon="calendar" :title="__('catalogs.empty')" />
        @else
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="px-4 py-2.5 font-bold md:px-5">{{ __('catalogs.fields.name') }}</th>
                        <th scope="col" class="w-20 px-3 py-2.5 text-right font-bold" title="{{ __('catalogs.coordination.authorized_hours') }}">{{ __('catalogs.coordination.authorized_short') }}</th>
                        <th scope="col" class="px-4 py-2.5 md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->activities as $activity)
                        <tr wire:key="coordination-{{ $activity->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $activity->is_active])>
                            <td class="px-4 py-2 md:px-5">
                                <span class="font-semibold">{{ $activity->name }}</span>
                                @if ($activity->per_teacher)
                                    <x-ui.badge variant="success" class="ml-1">{{ __('catalogs.coordination.per_teacher_badge') }}</x-ui.badge>
                                @elseif ($activity->uses_teacher_hour)
                                    <x-ui.badge variant="neutral" class="ml-1">{{ __('catalogs.coordination.uses_teacher_badge', ['mark' => $activity->panel_mark]) }}</x-ui.badge>
                                @elseif ($activity->requires_teacher)
                                    <x-ui.badge variant="info" class="ml-1">{{ __('catalogs.coordination.requires_teacher_badge') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums">{{ (float) $activity->authorized_hours }}</td>
                            <td class="px-4 py-2 md:px-5">
                                <x-catalog.row-actions :active="$activity->is_active" :name="$activity->name"
                                                       edit="edit({{ $activity->id }})" toggle="toggle({{ $activity->id }})" delete="confirmDeletion({{ $activity->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-line-strong">
                        <th scope="row" class="px-4 py-2 text-left md:px-5">{{ __('clubs.summary.total') }}</th>
                        <td class="px-3 py-2 text-right font-extrabold tabular-nums">{{ (float) $this->activities->where('is_active', true)->sum('authorized_hours') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" :title="__($activityId ? 'catalogs.coordination.edit' : 'catalogs.coordination.add')">
        <x-ui.input name="name" :label="__('catalogs.fields.name')" wire:model="name" maxlength="120" required />
        <x-ui.input name="authorizedHours" type="number" step="0.5" min="0" :label="__('catalogs.coordination.authorized_hours')" wire:model="authorizedHours" class="tabular-nums" required />
        <x-ui.toggle name="perTeacher" :label="__('catalogs.coordination.per_teacher')" :description="__('catalogs.coordination.per_teacher_hint')" wire:model="perTeacher" />
        <x-ui.choice name="tableColumn" :label="__('catalogs.coordination.table_column')"
                     :options="[1 => __('catalogs.coordination.column_left'), 2 => __('catalogs.coordination.column_right')]" wire:model="tableColumn" />
        <x-ui.toggle name="requiresTeacher" :label="__('catalogs.coordination.requires_teacher')" :description="__('catalogs.coordination.requires_teacher_hint')" wire:model="requiresTeacher" />
        <x-ui.toggle name="usesTeacherHour" :label="__('catalogs.coordination.uses_teacher_hour')" :description="__('catalogs.coordination.uses_teacher_hour_hint')" wire:model.live="usesTeacherHour" />
        @if ($usesTeacherHour)
            <x-ui.input name="panelMark" :label="__('catalogs.coordination.panel_mark')" :hint="__('catalogs.coordination.panel_mark_hint')" wire:model="panelMark" maxlength="3" class="w-24 uppercase" required />
        @endif
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
