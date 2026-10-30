<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('clubs.title')" :subtitle="__('clubs.subtitle')" />

    {{-- Mes --}}
    <section class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <x-ui.button variant="outline" wire:click="previousMonth" aria-label="{{ __('clubs.previous_month') }}">‹</x-ui.button>
            <h2 class="min-w-48 text-center font-display text-h3 font-bold">{{ \Illuminate\Support\Str::ucfirst($monthStart->translatedFormat('F Y')) }}</h2>
            <x-ui.button variant="outline" wire:click="nextMonth" aria-label="{{ __('clubs.next_month') }}">›</x-ui.button>
        </div>
        <p class="text-sm text-ink-500">
            {{ trans_choice('clubs.physical_rooms', $physical->count(), ['names' => $physical->pluck('name')->implode(', ')]) }}
            · {{ trans_choice('clubs.virtual_rooms', $virtual->count(), ['names' => $virtual->pluck('name')->implode(', ')]) }}
            · {{ trans_choice('clubs.sessions_count', $this->sessions->count()) }}
        </p>
    </section>

    {{-- Resumen: por teacher y por tipo (mes elegido y mes anterior) --}}
    <section class="sticky top-0 z-30 flex flex-col rounded-xl border border-line bg-white shadow-pop">
        <div class="flex flex-wrap gap-1 border-b border-line px-3 pt-2" role="tablist">
            @foreach (['teacher', 'type'] as $tab)
                <button type="button" role="tab" aria-selected="{{ $summaryTab === $tab ? 'true' : 'false' }}" aria-expanded="{{ $summaryTab === $tab ? 'true' : 'false' }}"
                        wire:click="toggleSummary('{{ $tab }}')"
                        @class([
                            '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-bold transition',
                            'border-primary-600 text-primary-700' => $summaryTab === $tab,
                            'border-transparent text-ink-500 hover:text-ink-900' => $summaryTab !== $tab,
                        ])>
                    {{ __('clubs.summary.'.$tab) }}
                    <span aria-hidden="true" class="text-xs">{{ $summaryTab === $tab ? '▲' : '▼' }}</span>
                </button>
            @endforeach
            <span class="ml-auto self-center whitespace-nowrap px-2 pb-1 text-sm font-bold tabular-nums text-ink-700">
                {{ trans_choice('clubs.sessions_count', $this->sessions->count()) }} · {{ (float) $codeHours->sum() }} h
            </span>        </div>
        @if ($summaryTab !== '')
        <div class="grid max-h-[45vh] gap-4 overflow-y-auto p-4 lg:grid-cols-2">
            @foreach ($summaries as $index => $summary)
                <div class="flex min-w-0 flex-col gap-2" wire:key="summary-{{ $summaryTab }}-{{ $index }}">
                    <h3 class="text-sm font-bold uppercase tracking-wide text-ink-500">{{ $summary['label'] }}{{ $index === 1 ? ' · '.__('clubs.summary.previous') : '' }}</h3>
                    @if ($summary['teachers'] === [])
                        <p class="text-sm text-ink-500">{{ __('clubs.summary.empty') }}</p>
                    @elseif ($summaryTab === 'teacher')
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase tracking-wide text-ink-500">
                                <tr><th scope="col" class="py-1 text-left">{{ __('clubs.fields.teacher') }}</th><th scope="col" class="py-1 text-right">{{ __('clubs.summary.hours') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($summary['teachers'] as $row)
                                    <tr><td class="py-1 font-semibold">{{ $row['name'] }}</td><td class="py-1 text-right font-bold tabular-nums">{{ (float) $row['hours'] }} h</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr class="border-t border-line-strong"><th scope="row" class="py-1 text-left">{{ __('clubs.summary.total') }}</th><td class="py-1 text-right font-extrabold tabular-nums">{{ (float) $summary['total'] }} h</td></tr></tfoot>
                        </table>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-xs uppercase tracking-wide text-ink-500">
                                    <tr>
                                        <th scope="col" class="py-1 pr-2 text-left">{{ __('clubs.fields.teacher') }}</th>
                                        @foreach ($summary['codes'] as $code)
                                            <th scope="col" class="px-1.5 py-1 text-right font-mono">{{ $code }}</th>
                                        @endforeach
                                        <th scope="col" class="py-1 pl-2 text-right">{{ __('clubs.summary.total') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($summary['teachers'] as $row)
                                        <tr>
                                            <td class="py-1 pr-2 font-semibold">{{ $row['name'] }}</td>
                                            @foreach ($summary['codes'] as $code)
                                                <td class="px-1.5 py-1 text-right tabular-nums {{ isset($row['codes'][$code]) ? 'font-semibold' : 'text-ink-400' }}">{{ isset($row['codes'][$code]) ? (float) $row['codes'][$code] : '—' }}</td>
                                            @endforeach
                                            <td class="py-1 pl-2 text-right font-bold tabular-nums">{{ (float) $row['hours'] }} h</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                {{-- Total por tipo de club (abreviatura) y total general. --}}
                                <tfoot>
                                    <tr class="border-t border-line-strong">
                                        <th scope="row" class="py-1 pr-2 text-left">{{ __('clubs.summary.total') }}</th>
                                        @foreach ($summary['codes'] as $code)
                                            <td class="px-1.5 py-1 text-right font-extrabold tabular-nums">{{ (float) collect($summary['teachers'])->sum(fn ($row) => $row['codes'][$code] ?? 0) }}</td>
                                        @endforeach
                                        <td class="py-1 pl-2 text-right font-extrabold tabular-nums">{{ (float) $summary['total'] }} h</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        @endif
    </section>

    @if ($physical->isEmpty() && $virtual->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building" :title="__('clubs.no_rooms')" :description="__('clubs.no_rooms_hint')" />
        </x-ui.card>
    @endif

    {{-- Leyenda de abreviaturas con las horas programadas en el mes --}}
    @if ($this->codeCatalog)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->codeCatalog as $key => $code)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-1 text-xs font-semibold text-ink-700" wire:key="legend-{{ $key }}">
                    <span class="size-3 rounded-full {{ \App\Support\RoomColors::for($code['color']) }}" aria-hidden="true"></span>{{ $code['label'] }}
                    <span class="rounded-full bg-surface-2 px-1.5 font-bold tabular-nums text-ink-900">{{ (float) ($codeHours[$key] ?? 0) }} h</span>
                </span>
            @endforeach
        </div>
    @endif

    {{-- Una cuadrícula por semana --}}
    @foreach ($weeks as $days)
        <section class="overflow-x-auto rounded-xl border border-line bg-white shadow-xs" wire:key="week-{{ $days[0]->toDateString() }}">
            <table class="w-full min-w-[720px] border-separate border-spacing-0 text-center text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="w-20 border-b border-line bg-primary-700 px-2 py-2 text-xs font-bold uppercase text-on-primary">{{ __('clubs.hour') }}</th>
                        @foreach ($days as $day)
                            @php($inMonth = $day->month === $monthStart->month)
                            @php($holiday = $holidays[$day->toDateString()] ?? null)
                            <th scope="col" @class([
                                'border-b border-l border-line px-2 py-2',
                                'bg-primary-700 text-on-primary' => $inMonth && ! $holiday,
                                'bg-warning-500 text-white' => $inMonth && $holiday,
                                'bg-surface-2 text-ink-400' => ! $inMonth,
                            ])>
                                <span class="block font-display text-base font-bold">{{ \Illuminate\Support\Str::ucfirst($day->translatedFormat('l')) }}</span>
                                <span class="block text-xs font-semibold">{{ $day->translatedFormat('j M') }}</span>
                                @if ($inMonth && $holiday)
                                    <span class="block truncate text-[11px]">{{ $holiday }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scheduleSlots as $slot)
                        {{-- Renglones alternos: blanco y azul muy claro. --}}
                        <tr wire:key="week-{{ $days[0]->toDateString() }}-slot-{{ $slot->id }}" @class(['bg-white' => $loop->odd, 'bg-row-alt' => $loop->even])>
                            <th scope="row" class="border-b border-line px-2 py-1 font-mono text-xs font-bold tabular-nums text-ink-700">{{ substr($slot->starts_at, 0, 5) }}</th>
                            @foreach ($days as $day)
                                @php($key = $day->toDateString().'|'.$slot->id)
                                @php($cellSessions = $grid->get($key, collect()))
                                @php($open = $day->month === $monthStart->month && ! isset($holidays[$day->toDateString()]) && $planner->slotAppliesOn($slot, $day->dayOfWeekIso))
                                @php($reserved = $day->month === $monthStart->month ? $reservedAt($day, $slot) : collect())
                                @php($freeRooms = $open ? $free($day, $slot) : 0)
                                {{-- Días de otro mes: deshabilitados pero con el renglón alterno; gris solo festivos y horas que no se trabajan. --}}
                                <td @class(['h-12 min-w-28 border-b border-l border-line p-1 align-top', 'bg-surface-2' => ! $open && $day->month === $monthStart->month])>
                                    <div class="flex flex-col gap-1">
                                        @foreach ($reserved as $closure)
                                            <span class="rounded-md border border-dashed border-ink-400 bg-white px-1 py-0.5 text-[10px] font-bold uppercase leading-tight text-ink-700"
                                                  title="{{ __('clubs.room', ['name' => $closure->classroom?->name]) }} · {{ $closure->reason }}">
                                                {{ __('clubs.reserved') }}<span class="block font-semibold normal-case">{{ __('clubs.room', ['name' => $closure->classroom?->name]) }}</span>
                                            </span>
                                        @endforeach
                                        @foreach ($cellSessions as $session)
                                            @php($orphan = in_array($session->id, $orphans, true))
                                            <button type="button" @if ($canManage) wire:click="edit({{ $session->id }})" @else disabled @endif
                                                    @class([
                                                        'flex w-full flex-col items-center rounded-md px-1 py-0.5 leading-tight focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                        \App\Support\RoomColors::for($session->color()),
                                                        'ring-2 ring-danger-600' => $orphan,
                                                        'hover:opacity-85' => $canManage,
                                                    ])
                                                    title="{{ $session->club?->name }} · {{ $session->teacher?->fullName() }} · {{ $session->modality->label() }} · {{ __('clubs.room', ['name' => $session->classroom?->name]) }}{{ $orphan ? ' · '.__('clubs.orphan') : '' }}">
                                                <span class="text-sm font-extrabold">{{ $session->code }}</span>
                                                <span class="text-[11px] font-bold uppercase">{{ $session->teacher?->panelName() }}</span>
                                                <span class="mt-0.5 rounded-full bg-white/90 px-2 text-[10px] font-bold uppercase tracking-wide text-ink-900">{{ $session->modality->label() }}</span>
                                            </button>
                                        @endforeach
                                        @if ($canManage && $freeRooms > 0)
                                            <button type="button" wire:click="add('{{ $day->toDateString() }}', {{ $slot->id }})"
                                                    class="flex h-7 w-full items-center justify-center rounded-md text-ink-400 hover:bg-accent-100 hover:text-ink-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                    aria-label="{{ __('clubs.add_at', ['day' => $day->translatedFormat('l j'), 'hour' => $slot->range()]) }}">
                                                <x-ui.icon name="plus" class="size-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @if ($canManage)
        <x-ui.modal wire:model="editing" size="md" :title="__($sessionId ? 'clubs.edit' : 'clubs.add')">
            <p class="-mt-3 text-sm font-semibold text-ink-500">
                {{ $date ? \Illuminate\Support\Str::ucfirst(\Carbon\CarbonImmutable::parse($date)->translatedFormat('l j \d\e F')) : '' }} · {{ $slotLabel }}
            </p>
            @error('slot') <p role="alert" class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            <x-ui.select name="codeKey" :label="__('clubs.fields.code')" :options="$this->codeOptions" :placeholder="__('students.select')" wire:model="codeKey" required />
            @error('code') <p role="alert" class="-mt-3 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            <x-ui.select name="teacherId" :label="__('clubs.fields.teacher')" :options="$this->teacherOptions"
                         :placeholder="__($this->teacherOptions ? 'students.select' : 'clubs.no_teachers')" :hint="__('clubs.teacher_hint')" wire:model="teacherId" required />
            <x-ui.choice name="modality" :label="__('clubs.fields.modality')" :options="$modalities" wire:model.live="modality" />
            <x-ui.select name="classroomId" :label="__($modality === 'online' ? 'clubs.fields.virtual_room' : 'clubs.fields.room')" :options="$this->roomOptions"
                         :placeholder="__($this->roomOptions ? 'students.select' : 'clubs.no_free_room')" wire:model="classroomId" wire:key="rooms-{{ $modality }}" required
                         :hint="$modality === 'online' && ! $this->roomOptions ? __('clubs.no_virtual_hint') : null" />
            <x-ui.toggle name="repeat" :label="__('clubs.repeat')" :description="__('clubs.repeat_hint')" wire:model="repeat" />
            <x-slot:footer>
                @if ($sessionId)
                    <x-ui.button variant="danger" wire:click="delete" wire:confirm="{{ __('clubs.confirm_delete') }}" class="md:mr-auto">{{ __('clubs.delete') }}</x-ui.button>
                @endif
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="save">{{ __('clubs.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
