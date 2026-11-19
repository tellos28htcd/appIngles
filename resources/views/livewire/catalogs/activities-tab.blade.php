<div>
    <x-catalog.section :title="__('catalogs.activities.title')" :description="__('catalogs.activities.description')"
                       add="edit" :add-label="__('catalogs.activities.add')" class="max-w-4xl">
        @if ($this->activities->isEmpty())
            <x-ui.empty-state icon="clipboard" :title="__('catalogs.empty')" />
        @else
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="w-16 px-4 py-2.5 text-right font-bold md:px-5" title="{{ __('catalogs.fields.activity_number') }}">{{ __('catalogs.fields.number_short') }}</th>
                        <th scope="col" class="w-20 px-3 py-2.5 font-bold">{{ __('catalogs.fields.code') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-bold">{{ __('catalogs.fields.description') }}</th>
                        <th scope="col" class="hidden px-3 py-2.5 font-bold md:table-cell">{{ __('catalogs.fields.grade_type') }}</th>
                        <th scope="col" class="px-4 py-2.5 md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->activities as $activity)
                        <tr wire:key="activity-{{ $activity->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $activity->is_active])>
                            <td class="px-4 py-2 text-right font-mono text-sm tabular-nums md:px-5">{{ $activity->number }}</td>
                            <td class="px-3 py-2">
                                <span class="inline-flex h-7 min-w-10 items-center justify-center rounded-md bg-primary-50 px-2 font-mono text-sm font-bold text-primary-700">{{ $activity->code }}</span>
                            </td>
                            <td class="px-3 py-2 font-semibold">
                                {{ $activity->description }}
                            </td>
                            <td class="hidden px-3 py-2 text-sm text-ink-700 md:table-cell">{{ ($activity->grade_type ?? \App\Enums\GradeType::forActivityNumber($activity->number))->label() }}</td>
                            <td class="px-4 py-2 md:px-5">
                                <x-catalog.row-actions :active="$activity->is_active" :name="$activity->code"
                                                       edit="edit({{ $activity->id }})" toggle="toggle({{ $activity->id }})" delete="confirmDeletion({{ $activity->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" :title="__($activityId ? 'catalogs.activities.edit' : 'catalogs.activities.add')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="number" type="number" :label="__('catalogs.fields.activity_number')" wire:model="number" min="1" class="tabular-nums" required />
            <x-ui.input name="code" :label="__('catalogs.fields.code')" wire:model="code" maxlength="10" class="font-mono uppercase" required />
        </div>
        <x-ui.input name="description" :label="__('catalogs.fields.description')" wire:model="description" maxlength="150" required />
        <x-ui.select name="gradeType" :label="__('catalogs.fields.grade_type')" :options="$gradeTypes" wire:model="gradeType" :hint="__('catalogs.activities.grade_type_hint')" required />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
