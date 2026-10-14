<div>
    <x-catalog.section :title="__('catalogs.student.survey.title')" :description="__('catalogs.student.survey.description')"
                       add="edit" :add-label="__('catalogs.student.survey.add')" class="max-w-4xl">
        @if ($this->questions->isEmpty())
            <x-ui.empty-state icon="clipboard" :title="__('catalogs.empty')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->questions->groupBy('section') as $section => $questions)
                    <li wire:key="section-{{ $loop->index }}" class="bg-surface px-4 py-2 text-xs font-bold uppercase tracking-wide text-ink-500 md:px-5">{{ $section }}</li>
                    @foreach ($questions as $record)
                        <li wire:key="question-{{ $record->id }}" @class(['flex items-start gap-3 px-4 py-3 md:px-5', 'opacity-60' => ! $record->is_active])>
                            <span class="w-10 flex-none pt-0.5 text-right font-mono text-xs tabular-nums text-ink-500">{{ $record->sort_order }}</span>
                            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span class="font-semibold [overflow-wrap:anywhere]">{{ $record->question }}</span>
                                <span class="text-xs text-ink-500 [overflow-wrap:anywhere]">
                                    {{ $record->type->label() }}@if ($record->options) · {{ implode(' · ', $record->options) }}@endif
                                </span>
                            </div>
                            <x-catalog.row-actions :active="$record->is_active" :name="$record->question"
                                                   edit="edit({{ $record->id }})" toggle="toggle({{ $record->id }})" delete="confirmDeletion({{ $record->id }})" />
                        </li>
                    @endforeach
                @endforeach
            </ul>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" size="md" :title="__($questionId ? 'catalogs.student.survey.edit' : 'catalogs.student.survey.add')">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px]">
            <div>
                <x-ui.input name="section" :label="__('catalogs.student.survey.fields.section')" wire:model="section" maxlength="80" list="survey-sections" required />
                <datalist id="survey-sections">
                    @foreach ($sections as $option)
                        <option value="{{ $option }}"></option>
                    @endforeach
                </datalist>
            </div>
            <x-ui.input name="sortOrder" type="number" :label="__('catalogs.student.survey.fields.sort_order')" wire:model="sortOrder" min="0" max="9999" class="tabular-nums" />
        </div>
        <x-ui.input name="question" :label="__('catalogs.student.survey.fields.question')" wire:model="question" maxlength="255" required />
        <x-ui.select name="type" :label="__('catalogs.student.survey.fields.type')" :options="$types" wire:model.live="type" required />
        @if ($type === 'choice')
            <x-ui.textarea name="options" :label="__('catalogs.student.survey.fields.options')" :hint="__('catalogs.student.survey.options_hint')" wire:model="options" rows="6" />
        @endif
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
