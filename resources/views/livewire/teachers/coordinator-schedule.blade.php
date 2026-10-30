<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('coordination.title')" :subtitle="__('coordination.subtitle')" />

    {{-- Mes --}}
    <section class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <x-ui.button variant="outline" wire:click="previousMonth" aria-label="{{ __('clubs.previous_month') }}">‹</x-ui.button>
            <h2 class="min-w-48 text-center font-display text-h3 font-bold">{{ \Illuminate\Support\Str::ucfirst($monthStart->translatedFormat('F Y')) }}</h2>
            <x-ui.button variant="outline" wire:click="nextMonth" aria-label="{{ __('clubs.next_month') }}">›</x-ui.button>
        </div>
        <p class="text-sm text-ink-500">{{ __('coordination.month_totals', ['programmed' => (float) $totalProgrammed, 'authorized' => (float) $totalAuthorized]) }}</p>
    </section>

    {{-- Resumen: por teacher y por actividad (mes elegido y mes anterior), colapsado --}}
    <section class="sticky top-0 z-30 flex flex-col rounded-xl border border-line bg-white shadow-pop">
        <div class="flex flex-wrap gap-1 border-b border-line px-3 pt-2" role="tablist">
            @foreach (['teacher', 'activity'] as $tab)
                <button type="button" role="tab" aria-selected="{{ $summaryTab === $tab ? 'true' : 'false' }}" aria-expanded="{{ $summaryTab === $tab ? 'true' : 'false' }}"
                        wire:click="toggleSummary('{{ $tab }}')"
                        @class([
                            '-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-bold transition',
                            'border-primary-600 text-primary-700' => $summaryTab === $tab,
                            'border-transparent text-ink-500 hover:text-ink-900' => $summaryTab !== $tab,
                        ])>
                    {{ __('coordination.summary.'.$tab) }}
                    <span aria-hidden="true" class="text-xs">{{ $summaryTab === $tab ? '▲' : '▼' }}</span>
                </button>
            @endforeach
            <span @class(['ml-auto self-center whitespace-nowrap px-2 pb-1 text-sm font-bold tabular-nums', 'text-success-700' => $allMatch, 'text-warning-700' => ! $allMatch])>
                HP {{ (float) $totalProgrammed }} / HA {{ (float) $totalAuthorized }} · {{ __($allMatch ? 'coordination.all_match' : 'coordination.not_matching') }}
            </span>        </div>
        @if ($summaryTab !== '')
            <div class="grid max-h-[45vh] gap-4 overflow-y-auto p-4 lg:grid-cols-2">
                @foreach ($summaries as $index => $summary)
                    @php($rows = $summaryTab === 'teacher' ? $summary['teachers'] : $summary['activities'])
                    <div class="flex min-w-0 flex-col gap-2" wire:key="summary-{{ $summaryTab }}-{{ $index }}">
                        <h3 class="text-sm font-bold uppercase tracking-wide text-ink-500">{{ $summary['label'] }}{{ $index === 1 ? ' · '.__('clubs.summary.previous') : '' }}</h3>
                        @if ($rows === [])
                            <p class="text-sm text-ink-500">{{ __('coordination.summary.empty') }}</p>
                        @else
                            <table class="w-full text-sm">
                                <thead class="text-xs uppercase tracking-wide text-ink-500">
                                    <tr>
                                        <th scope="col" class="py-1 text-left">{{ __($summaryTab === 'teacher' ? 'coordination.fields.teacher' : 'coordination.fields.activity') }}</th>
                                        <th scope="col" class="py-1 text-right">{{ __('clubs.summary.hours') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($rows as $row)
                                        <tr><td class="py-1 font-semibold">{{ $row['name'] }}</td><td class="py-1 text-right font-bold tabular-nums">{{ (float) $row['hours'] }} h</td></tr>
                                    @endforeach
                                </tbody>
                                <tfoot><tr class="border-t border-line-strong"><th scope="row" class="py-1 text-left">{{ __('clubs.summary.total') }}</th><td class="py-1 text-right font-extrabold tabular-nums">{{ (float) $summary['total'] }} h</td></tr></tfoot>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Catálogo: horas autorizadas (HA) y programadas en el mes (HP), en dos columnas --}}
    <section class="grid gap-4 lg:grid-cols-2 lg:items-start">
        @foreach ($columns as $number => $rows)
            <div class="overflow-x-auto rounded-xl border border-line bg-white shadow-xs" wire:key="hours-column-{{ $number }}">
                <table class="w-full text-sm">
                    <thead class="bg-ink-900 text-left text-on-primary">
                        <tr>
                            <th scope="col" class="px-4 py-2 font-bold">{{ __('coordination.fields.activity') }}</th>
                            <th scope="col" class="w-16 px-3 py-2 text-center font-bold" title="{{ __('coordination.authorized') }}">HA</th>
                            <th scope="col" class="w-16 px-3 py-2 text-center font-bold" title="{{ __('coordination.programmed') }}">HP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @if ($row === null)
                                <tr class="h-8 bg-white" aria-hidden="true"><td colspan="3"></td></tr>
                                @continue
                            @endif
                            @php($state = abs($row['programmed'] - $row['authorized']) < 0.001 ? 'match' : ($row['programmed'] > $row['authorized'] ? 'over' : 'under'))
                            <tr @class(['bg-white' => $loop->odd, 'bg-row-alt' => $loop->even])>
                                <td class="px-4 py-1.5">
                                    @if ($canManage && isset($row['teacher_id']) && $row['activity']->evaluatesClubs())
                                        {{-- Evaluación de Club: abre los clubs del teacher en el mes para programarla en uno. --}}
                                        <button type="button" wire:click="openClubPicker({{ $row['activity']->id }}, {{ $row['teacher_id'] }})"
                                                class="rounded text-left font-semibold text-primary-700 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                title="{{ __('coordination.club_picker.open') }}">{{ $row['label'] }}</button>
                                    @else
                                        <span class="font-semibold">{{ $row['label'] }}</span>
                                    @endif
                                    @if ($row['activity']->uses_teacher_hour)
                                        <span class="ml-1 inline-flex size-5 items-center justify-center rounded bg-ink-900 text-[11px] font-extrabold text-white" title="{{ __('coordination.uses_teacher_hour') }}">{{ $row['activity']->panel_mark ?: '•' }}</span>
                                    @elseif (! $row['activity']->needsTeacher())
                                        <span class="ml-1 text-xs text-ink-500">· {{ __('coordination.coordination_short') }}</span>
                                    @endif
                                </td>
                                <td class="px-3 py-1.5 text-center tabular-nums">{{ (float) $row['authorized'] }}</td>
                                <td @class(['px-3 py-1.5 text-center font-bold tabular-nums',
                                    'text-success-700' => $state === 'match',
                                    'bg-danger-50 text-danger-700' => $state === 'over',
                                    'text-ink-700' => $state === 'under'])
                                    title="{{ __('coordination.state.'.$state) }}">{{ (float) $row['programmed'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-3 text-ink-500">{{ __('coordination.no_activities') }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($loop->last)
                        <tfoot>
                            <tr class="border-t-2 border-ink-900">
                                <th scope="row" class="px-4 py-2 text-left">
                                    <span @class(['inline-flex items-center gap-2', 'text-success-700' => $allMatch, 'text-warning-700' => ! $allMatch])>
                                        <x-ui.icon :name="$allMatch ? 'check' : 'close'" class="size-4" />{{ __($allMatch ? 'coordination.all_match' : 'coordination.not_matching') }}
                                    </span>
                                </th>
                                <td class="px-3 py-2 text-center font-extrabold tabular-nums">{{ (float) $totalAuthorized }}</td>
                                <td class="px-3 py-2 text-center font-extrabold tabular-nums">{{ (float) $totalProgrammed }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        @endforeach
    </section>

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
                        <tr wire:key="week-{{ $days[0]->toDateString() }}-slot-{{ $slot->id }}" @class(['bg-white' => $loop->odd, 'bg-row-alt' => $loop->even])>
                            <th scope="row" class="border-b border-line px-2 py-1 font-mono text-xs font-bold tabular-nums text-ink-700">{{ substr($slot->starts_at, 0, 5) }}</th>
                            @foreach ($days as $day)
                                @php($session = $grid->get($day->toDateString().'|'.$slot->id))
                                @php($open = $day->month === $monthStart->month && ! isset($holidays[$day->toDateString()]) && $planner->slotAppliesOn($slot, $day->dayOfWeekIso))
                                {{-- Días de otro mes: deshabilitados pero con el renglón alterno; gris solo festivos y horas que no se trabajan. --}}
                                <td @class(['h-12 min-w-28 border-b border-l border-line p-1 align-top', 'bg-surface-2' => ! $open && ! $session && $day->month === $monthStart->month])>
                                    @if ($session)
                                        <button type="button" @if ($canManage) wire:click="edit({{ $session->id }})" @else disabled @endif
                                                @class([
                                                    'flex w-full flex-col items-center rounded-md px-1 py-0.5 leading-tight focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                    'bg-ink-900 text-white' => $session->activity?->uses_teacher_hour,
                                                    'bg-primary-100 text-primary-700' => ! $session->activity?->uses_teacher_hour,
                                                    'hover:opacity-85' => $canManage,
                                                ])
                                                title="{{ $session->activity?->name }} · {{ $session->teacher?->fullName() ?? __('coordination.coordination_option') }}">
                                            <span class="line-clamp-2 text-[11px] font-extrabold">{{ $session->activity?->name }}</span>
                                            <span class="text-[10px] font-bold uppercase">{{ $session->teacher?->panelName() ?? __('coordination.coordination_short') }}</span>
                                        </button>
                                    @elseif ($canManage && $open)
                                        <button type="button" wire:click="add('{{ $day->toDateString() }}', {{ $slot->id }})"
                                                class="flex h-7 w-full items-center justify-center rounded-md text-ink-400 hover:bg-accent-100 hover:text-ink-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                aria-label="{{ __('coordination.add_at', ['day' => $day->translatedFormat('l j'), 'hour' => $slot->range()]) }}">
                                            <x-ui.icon name="plus" class="size-4" />
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @if ($canManage)
        {{-- Clubs del teacher en el mes: clic para programar la Evaluación de Club en esa fecha y hora. --}}
        <x-ui.modal wire:model="clubPickerTeacherId" size="md" :title="__('coordination.club_picker.title')">
            <p class="-mt-3 text-sm text-ink-500">{{ __('coordination.club_picker.hint') }}</p>
            <ul class="flex flex-col gap-1.5">
                @forelse ($this->teacherClubs as $club)
                    <li wire:key="picker-club-{{ $club['id'] }}">
                        <button type="button" wire:click="evaluateClub({{ $club['id'] }})" @disabled($club['busy'])
                                @class([
                                    'flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left font-semibold tabular-nums transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                    'border-success-200 bg-success-50 text-success-700' => $club['evaluated'],
                                    'border-line bg-surface-2 text-ink-400' => $club['busy'] && ! $club['evaluated'],
                                    'border-line hover:border-primary-500 hover:bg-primary-50' => ! $club['busy'],
                                ])>
                            <span>{{ $club['label'] }}</span>
                            <span class="text-xs font-bold">
                                {{ $club['evaluated'] ? __('coordination.club_picker.evaluated') : ($club['busy'] ? __('coordination.club_picker.busy') : __('coordination.club_picker.add')) }}
                            </span>
                        </button>
                    </li>
                @empty
                    <li class="text-sm text-ink-500">{{ __('coordination.club_picker.empty') }}</li>
                @endforelse
            </ul>
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('coordination.club_picker.close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal wire:model="editing" size="md" :title="__($sessionId ? 'coordination.edit' : 'coordination.add')">
            <p class="-mt-3 text-sm font-semibold text-ink-500">
                {{ $date ? \Illuminate\Support\Str::ucfirst(\Carbon\CarbonImmutable::parse($date)->translatedFormat('l j \d\e F')) : '' }} · {{ $slotLabel }}
            </p>
            @error('slot') <p role="alert" class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            <x-ui.select name="activityId" :label="__('coordination.fields.activity')" :options="$activityOptions" :placeholder="__('students.select')" wire:model.live="activityId" required />
            <x-ui.select name="teacherKey" :label="__('coordination.fields.teacher')" :options="$this->teacherOptions" wire:key="teachers-{{ $activityId }}"
                         :placeholder="__($activityId ? ($this->teacherOptions ? 'students.select' : 'clubs.no_teachers') : 'coordination.choose_activity_first')"
                         :hint="__('coordination.teacher_hint')" wire:model="teacherKey" required />
            <x-ui.toggle name="repeat" :label="__('clubs.repeat')" :description="__('coordination.repeat_hint')" wire:model="repeat" />
            <x-slot:footer>
                @if ($sessionId)
                    <x-ui.button variant="danger" wire:click="delete" wire:confirm="{{ __('coordination.confirm_delete') }}" class="md:mr-auto">{{ __('coordination.delete') }}</x-ui.button>
                @endif
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="save">{{ __('coordination.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
