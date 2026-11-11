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
                        <th scope="col" class="hidden w-24 px-3 py-2.5 text-right font-bold sm:table-cell">{{ __('catalogs.fields.minutes') }}</th>
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
                                @if ($activity->minutes)
                                    <span class="block text-xs font-normal text-ink-500 sm:hidden">{{ __('catalogs.activities.minutes_value', ['minutes' => $activity->minutes]) }}</span>
                                @endif
                            </td>
                            <td class="hidden px-3 py-2 text-right text-sm tabular-nums text-ink-700 sm:table-cell">{{ $activity->minutes ?? '—' }}</td>
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

    @if ($this->activities->isNotEmpty())
        {{-- Pesos por tipo de lección (balanceo de aulas): vacío = no se hace; la ruta de avance la salta. --}}
        <x-catalog.section :title="__('catalogs.activities.weights')" :description="__('catalogs.activities.weights_description')" class="mt-6 max-w-4xl">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm">
                    <thead class="bg-primary-700 text-on-primary">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-bold">{{ __('catalogs.activities.lesson_type') }}</th>
                            @foreach ($this->activities->where('is_active', true) as $activity)
                                <th scope="col" class="px-2 py-2 text-right font-bold" title="{{ $activity->description }}">
                                    <span class="block text-[11px] font-semibold opacity-80">{{ $activity->number }}</span>{{ $activity->code }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach (\App\Enums\LessonType::cases() as $type)
                            <tr wire:key="weights-{{ $type->value }}">
                                <th scope="row" class="px-4 py-2 text-left font-semibold">{{ $type->label() }}</th>
                                @foreach ($this->activities->where('is_active', true) as $activity)
                                    @php($weight = $activity->weightFor($type))
                                    @if ($weight === null)
                                        <td class="bg-ink-700 px-2 py-2" title="{{ __('catalogs.activities.not_done') }}"><span class="sr-only">{{ __('catalogs.activities.not_done') }}</span></td>
                                    @else
                                        <td class="px-2 py-2 text-right font-bold tabular-nums">
                                            {{ $weight }}@if ($activity->is_one_to_one)<span class="ml-1 text-[10px] text-primary-700" title="{{ __('catalogs.activities.one_to_one') }}">1:1</span>@endif
                                        </td>
                                    @endif
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-catalog.section>
    @endif

    <x-ui.modal wire:model="editing" :title="__($activityId ? 'catalogs.activities.edit' : 'catalogs.activities.add')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.input name="number" type="number" :label="__('catalogs.fields.activity_number')" wire:model="number" min="1" class="tabular-nums" required />
            <x-ui.input name="code" :label="__('catalogs.fields.code')" wire:model="code" maxlength="10" class="font-mono uppercase" required />
            <x-ui.input name="minutes" type="number" :label="__('catalogs.fields.minutes')" wire:model="minutes" min="1" class="tabular-nums" />
        </div>
        <x-ui.input name="description" :label="__('catalogs.fields.description')" wire:model="description" maxlength="150" required />
        <fieldset class="flex flex-col gap-2">
            <legend class="text-sm font-semibold text-ink-900">{{ __('catalogs.activities.weights') }}</legend>
            <p class="text-xs text-ink-500">{{ __('catalogs.activities.weights_hint') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.input name="weightLesson" type="number" :label="__('catalogs.lesson_types.lesson')" wire:model="weightLesson" min="0" max="999" class="tabular-nums" />
                <x-ui.input name="weightCheckpoint" type="number" :label="__('catalogs.lesson_types.checkpoint')" wire:model="weightCheckpoint" min="0" max="999" class="tabular-nums" />
                <x-ui.input name="weightVerbInContext" type="number" :label="__('catalogs.lesson_types.verb_in_context')" wire:model="weightVerbInContext" min="0" max="999" class="tabular-nums" />
            </div>
        </fieldset>
        <x-ui.toggle name="isOneToOne" :label="__('catalogs.activities.one_to_one')" :description="__('catalogs.activities.one_to_one_hint')" wire:model="isOneToOne" />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
