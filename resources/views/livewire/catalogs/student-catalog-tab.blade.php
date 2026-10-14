<div>
    <x-catalog.section :title="__('catalogs.student.'.$catalog.'.title')" :description="__('catalogs.student.'.$catalog.'.description')"
                       add="edit" :add-label="__('catalogs.student.'.$catalog.'.add')" class="max-w-4xl">
        @if ($this->records->isEmpty())
            <x-ui.empty-state icon="clipboard" :title="__('catalogs.empty')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            @foreach ($fields as $name => $field)
                                <th scope="col" @class(['px-4 py-2.5 font-bold md:px-5', 'text-right' => $field[0] === 'number'])>{{ __("catalogs.student.fields.$name") }}</th>
                            @endforeach
                            <th scope="col" class="px-4 py-2.5 md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->records as $record)
                            <tr wire:key="{{ $catalog }}-{{ $record->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $record->is_active])>
                                @foreach ($fields as $name => $field)
                                    <td @class([
                                        'px-4 py-2 md:px-5',
                                        'text-right font-mono text-sm tabular-nums' => $field[0] === 'number',
                                        'font-mono text-sm font-bold text-primary-700' => $field[0] === 'code',
                                        'font-semibold' => $loop->first && $field[0] === 'text',
                                        'text-sm text-ink-700' => ! $loop->first && $field[0] === 'text',
                                    ])>{{ $record->{$name} ?? '—' }}</td>
                                @endforeach
                                <td class="px-4 py-2 md:px-5">
                                    <x-catalog.row-actions :active="$record->is_active" :name="$record->name ?? $record->code"
                                                           edit="edit({{ $record->id }})" toggle="toggle({{ $record->id }})" delete="confirmDeletion({{ $record->id }})" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" :title="__($recordId ? 'catalogs.student.'.$catalog.'.edit' : 'catalogs.student.'.$catalog.'.add')">
        @foreach ($fields as $name => $field)
            <x-ui.input name="values.{{ $name }}" :label="__('catalogs.student.fields.'.$name)" wire:model="values.{{ $name }}" wire:key="field-{{ $name }}"
                        :type="$field[0] === 'number' ? 'number' : 'text'" :maxlength="$field[0] === 'number' ? null : $field[1]"
                        :min="$field[0] === 'number' ? 0 : null" :max="$field[0] === 'number' ? $field[1] : null"
                        @class(['font-mono uppercase' => $field[0] === 'code', 'tabular-nums' => $field[0] === 'number'])
                        :hint="__('catalogs.student.hints.'.$catalog.'.'.$name) !== 'catalogs.student.hints.'.$catalog.'.'.$name ? __('catalogs.student.hints.'.$catalog.'.'.$name) : null" />
        @endforeach
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
