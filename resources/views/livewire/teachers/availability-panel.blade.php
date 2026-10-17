@php($dayNames = collect($days)->mapWithKeys(fn ($day) => [$day->toDateString() => $day->translatedFormat('l')]))
<div class="flex flex-col gap-6" x-data="{ cell: null, top: 0, left: 0, pick(id, event) { const r = event.currentTarget.getBoundingClientRect(); this.cell = id; this.top = r.bottom + window.scrollY + 4; this.left = Math.min(r.left + window.scrollX, window.innerWidth - 220); } }"
     x-on:keydown.escape.window="cell = null">
    <x-ui.page-header :title="__('availability.panel_title')" :subtitle="__('availability.panel_subtitle')" />

    {{-- Mes y semana (histórico) --}}
    <section class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-xs md:flex-row md:items-end">
        <div class="grid flex-1 gap-3 sm:grid-cols-2 md:max-w-xl">
            <x-ui.select name="month" :label="__('availability.month')" :options="$monthOptions" wire:model.live="month" />
            <x-ui.select name="week" :label="__('availability.week')" :options="$weekOptions" wire:model.live="week" />
        </div>
        <div class="flex gap-2">
            <x-ui.button variant="outline" wire:click="previousWeek" aria-label="{{ __('availability.previous_week') }}">‹ {{ __('availability.previous') }}</x-ui.button>
            <x-ui.button variant="outline" wire:click="nextWeek" aria-label="{{ __('availability.next_week') }}">{{ __('availability.next') }} ›</x-ui.button>
        </div>
    </section>

    @if (! $generated)
        <x-ui.card>
            <x-ui.empty-state icon="calendar" :title="__('availability.not_generated')" :description="__($canManage ? 'availability.not_generated_hint' : 'availability.not_generated_viewer')">
                @if ($canManage)
                    <x-ui.button wire:click="generate" wire:loading.attr="disabled" wire:target="generate">{{ __('availability.generate') }}</x-ui.button>
                @endif
            </x-ui.empty-state>
        </x-ui.card>
    @elseif ($teachers->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="users" :title="__('availability.no_teachers')" :description="__('availability.no_teachers_hint')" />
        </x-ui.card>
    @else
        {{-- Panel --}}
        <section class="overflow-x-auto rounded-xl border border-line bg-ink-900 p-3 shadow-xs" wire:loading.class="opacity-70">
            <table class="border-separate border-spacing-0 text-center text-[13px]">
                <thead>
                    <tr>
                        <th rowspan="3" class="w-10 align-bottom text-lg font-extrabold uppercase text-white [writing-mode:vertical-rl] rotate-180 px-1">
                            {{ \Carbon\CarbonImmutable::parse($week)->translatedFormat('M') }}
                        </th>
                        @foreach ($days as $day)
                            @foreach ($teachers as $teacher)
                                <th class="h-24 w-7 min-w-7 align-bottom px-0 pb-1 text-[11px] font-semibold uppercase leading-none text-white">
                                    <span class="inline-block [writing-mode:vertical-rl] rotate-180 whitespace-nowrap">{{ $teacher->panelName() }}</span>
                                </th>
                            @endforeach
                            <th class="w-3 min-w-3"></th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($days as $day)
                            @foreach ($teachers as $teacher)
                                <th class="border-x border-ink-900 bg-ink-500 py-0.5 text-xs font-bold text-white tabular-nums">{{ $teacher->panel_number ?? '–' }}</th>
                            @endforeach
                            <th></th>
                        @endforeach
                    </tr>
                    <tr>
                        @foreach ($days as $day)
                            <th colspan="{{ $teachers->count() }}" class="bg-primary-600 py-1 text-xs font-bold text-on-primary">
                                {{ $day->translatedFormat('l j') }}
                            </th>
                            <th></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scheduleSlots as $slot)
                        <tr wire:key="row-{{ $slot->id }}" @class(['bg-surface' => $loop->odd, 'bg-white' => $loop->even])>
                            <th scope="row" class="bg-white px-1.5 text-sm font-extrabold tabular-nums text-ink-900" title="{{ $slot->range() }}">{{ (int) substr($slot->starts_at, 0, 2) }}</th>
                            @foreach ($days as $day)
                                @foreach ($teachers as $teacher)
                                    @php($cell = $grid->get($day->toDateString().'|'.$slot->id.'|'.$teacher->id))
                                    <td class="h-7 border border-line p-0">
                                        @if ($cell)
                                            @php($label = $cell->classroom?->name)
                                            <button type="button"
                                                    @if ($canManage) x-on:click="pick({{ $cell->id }}, $event)" @else disabled @endif
                                                    @class([
                                                        'flex h-7 w-full items-center justify-center font-bold tabular-nums focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                        \App\Support\RoomColors::for($classroomNumbers[$cell->classroom_id] ?? 1) => $cell->classroom_id !== null,
                                                        'bg-primary-50 text-primary-700' => $cell->classroom_id === null,
                                                        'cursor-pointer hover:opacity-80' => $canManage,
                                                        'cursor-default' => ! $canManage,
                                                    ])
                                                    title="{{ $teacher->panelName() }} · {{ $dayNames[$day->toDateString()] }} {{ $slot->range() }}{{ $label ? ' · '.__('availability.classroom').' '.$label : ' · '.__('availability.no_classroom') }}">
                                                {{ $label ?? '·' }}
                                            </button>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="bg-ink-900"></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th></th>
                        @foreach ($days as $day)
                            @foreach ($teachers as $teacher)
                                <td class="bg-accent-500 py-1 text-xs font-bold tabular-nums text-on-accent">{{ $perDay->get($day->toDateString().'|'.$teacher->id, 0) }}</td>
                            @endforeach
                            <td></td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </section>

        <div class="grid gap-4 md:gap-6 lg:grid-cols-[minmax(0,320px)_minmax(0,1fr)]">
            {{-- Leyenda: número, teacher, horas asignadas y horas a cumplir --}}
            <x-ui.card :title="__('availability.legend')">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th scope="col" class="py-1 text-left">{{ __('availability.teacher') }}</th>
                            <th scope="col" class="py-1 text-right" title="{{ __('availability.assigned_hint') }}">{{ __('availability.assigned') }}</th>
                            <th scope="col" class="py-1 text-right" title="{{ __('availability.to_fulfill_hint') }}">{{ __('availability.to_fulfill_short') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teachers as $teacher)
                            <tr wire:key="legend-{{ $teacher->id }}">
                                <td class="py-0.5">
                                    <span class="flex items-center gap-2">
                                        <span class="flex size-6 flex-none items-center justify-center rounded text-xs font-bold {{ \App\Support\RoomColors::for($teacher->panel_number ?? 1) }}">{{ $teacher->panel_number ?? '–' }}</span>
                                        <span class="font-semibold">{{ $teacher->panelName() }}</span>
                                    </span>
                                </td>
                                @php($assigned = $weekly->get($teacher->id, 0))
                                <td @class(['py-0.5 text-right font-bold tabular-nums', 'text-danger-700' => ! $teacher->is_waitlist && $assigned > 48])>{{ $assigned }}</td>
                                <td class="py-0.5 text-right tabular-nums text-ink-500">{{ $teacher->is_waitlist ? '—' : ($teacher->weekly_hours ?? '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.card>

            {{-- Gráfica: promedio de teachers por hora (lunes a viernes) --}}
            <x-ui.card :title="__('availability.chart_title')" :description="__('availability.chart_hint')">
                <div class="flex h-56 items-end gap-1.5 border-b border-l border-line pl-1" role="img" aria-label="{{ __('availability.chart_title') }}">
                    @foreach ($scheduleSlots as $slot)
                        @php($value = $average[$slot->id])
                        <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" wire:key="bar-{{ $slot->id }}">
                            <span class="text-[11px] font-bold tabular-nums text-ink-700">{{ $value > 0 ? $value : '' }}</span>
                            <div class="w-full rounded-t bg-primary-500" style="height: {{ round($value / $maxAverage * 85) }}%" title="{{ $slot->range() }}: {{ $value }}"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-1.5 pl-1">
                    @foreach ($scheduleSlots as $slot)
                        <span class="flex-1 text-center text-[11px] tabular-nums text-ink-500">{{ (int) substr($slot->starts_at, 0, 2) }}</span>
                    @endforeach
                </div>
            </x-ui.card>
        </div>

        {{-- Selector de aula (uno para todo el panel) --}}
        @if ($canManage)
            <div x-cloak x-show="cell" x-on:click.outside="cell = null" x-transition.opacity
                 class="absolute z-50 w-52 rounded-xl border border-line bg-white p-2 shadow-pop" x-bind:style="`top:${top}px;left:${left}px`">
                <p class="px-1 pb-1 text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('availability.choose_classroom') }}</p>
                <div class="grid grid-cols-4 gap-1">
                    @foreach ($classrooms as $classroom)
                        <button type="button" x-on:click="$wire.setClassroom(cell, {{ $classroom->id }}); cell = null"
                                class="flex h-9 items-center justify-center rounded-md text-sm font-bold {{ \App\Support\RoomColors::for($classroomNumbers[$classroom->id]) }} hover:opacity-80"
                                title="{{ __('availability.classroom') }} {{ $classroom->name }}">{{ $classroom->name }}</button>
                    @endforeach
                </div>
                <button type="button" x-on:click="$wire.setClassroom(cell, null); cell = null"
                        class="mt-1 w-full rounded-md px-2 py-1.5 text-left text-sm font-semibold text-ink-700 hover:bg-surface-2">{{ __('availability.clear_classroom') }}</button>
            </div>
        @endif
    @endif
</div>
