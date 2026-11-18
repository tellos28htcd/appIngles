<div>
    <x-catalog.section :title="__('catalogs.weights.title')" :description="__('catalogs.weights.description')">
        @if ($this->lessons->isEmpty())
            <x-ui.empty-state icon="book" :title="__('catalogs.empty')" />
        @else
            {{-- Tabla general (aplica a la lección en todos los libros); oscuro = la actividad no se hace en esa lección. --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead class="bg-primary-700 text-on-primary">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-bold">{{ __('catalogs.weights.lesson') }}</th>
                            <th scope="col" class="px-2 py-2 text-right font-bold">{{ __('catalogs.weights.number') }}</th>
                            <th scope="col" class="px-2 py-2 text-right font-bold" title="{{ __('catalogs.weights.minutes_hint') }}">{{ __('catalogs.weights.minutes') }}</th>
                            <th scope="col" class="px-2 py-2 text-right font-bold" title="{{ __('catalogs.weights.hours_hint') }}">{{ __('catalogs.weights.hours') }}</th>
                            @foreach ($this->activities as $activity)
                                <th scope="col" class="px-1 py-2 text-center font-bold" title="{{ $activity->description }}">
                                    <span class="block text-[11px] font-semibold opacity-80">{{ $activity->number }}</span>{{ $activity->code }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->lessons as $lesson)
                            @php($done = $this->doneActivities[$lesson->number] ?? [])
                            <tr wire:key="weights-lesson-{{ $lesson->number }}" @class(['bg-row-alt' => $lesson->type !== \App\Enums\LessonType::Lesson, 'opacity-60' => ! $lesson->is_active])>
                                <th scope="row" class="whitespace-nowrap px-4 py-1.5 text-left font-semibold">{{ $lesson->name }}</th>
                                <td class="px-2 py-1.5 text-right tabular-nums text-ink-700">{{ $lesson->number }}</td>
                                <td class="px-2 py-1.5 text-right font-bold tabular-nums">{{ $totals[$lesson->number] ?? 0 }}</td>
                                <td class="px-2 py-1.5 text-right tabular-nums">{{ number_format(($totals[$lesson->number] ?? 0) / 60, 2) }}</td>
                                @foreach ($this->activities as $activity)
                                    <td class="px-1 py-1">
                                        @if (in_array($activity->number, $done, true))
                                            <input type="number" min="0" max="999" inputmode="numeric" wire:model.blur="weights.{{ $lesson->number }}.{{ $activity->number }}"
                                                   aria-label="{{ __('catalogs.weights.weight_of', ['activity' => $activity->code, 'lesson' => $lesson->name]) }}"
                                                   @class(['h-9 w-full min-w-14 rounded-md border border-line-strong bg-white px-2 text-right font-bold tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100',
                                                       'border-danger-500' => $errors->has("weights.{$lesson->number}.{$activity->number}")])>
                                        @else
                                            <span class="block h-9 rounded-md bg-ink-700" title="{{ __('catalogs.weights.not_done') }}"><span class="sr-only">{{ __('catalogs.weights.not_done') }}</span></span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($errors->any())
                <p role="alert" class="px-4 pt-3 text-sm font-semibold text-danger-700 md:px-5">{{ $errors->first() }}</p>
            @endif
            <div class="flex justify-end border-t border-line px-4 py-3 md:px-5">
                <x-ui.button wire:click="save" wire:loading.attr="disabled">{{ __('catalogs.weights.save') }}</x-ui.button>
            </div>
        @endif
    </x-catalog.section>
</div>
