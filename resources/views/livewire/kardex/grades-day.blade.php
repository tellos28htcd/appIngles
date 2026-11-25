<div class="flex flex-col gap-6">
    {{-- Dentro de la Agenda (botón "Calificaciones" de cada reservación) solo se usa la ventana de calificar. --}}
    @unless ($embedded)
    <x-ui.page-header :title="__('kardex.grades_title')" :subtitle="__('kardex.grades_subtitle', ['days' => $daysBack])" />

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Día elegido: hoy o hasta 4 días antes (el Coordinador y el Administrador pueden ir más atrás para corregir). --}}
        @php($nav = 'flex size-11 items-center justify-center rounded-md border-[1.5px] border-line-strong bg-white text-ink-700 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 disabled:opacity-40')
        <div class="flex flex-wrap items-center gap-2 border-b border-line p-4">
            <button type="button" wire:click="shiftDay(-1)" class="{{ $nav }}" aria-label="{{ __('agenda.previous_day') }}" title="{{ __('agenda.previous_day') }}"
                    @disabled(! $canCorrect && $day->toDateString() <= $earliest->toDateString())>‹</button>
            <button type="button" wire:click="shiftDay(1)" class="{{ $nav }}" aria-label="{{ __('agenda.next_day') }}" title="{{ __('agenda.next_day') }}"
                    @disabled($day->toDateString() >= $today->toDateString())>›</button>
            <h2 class="font-display text-lg font-bold first-letter:uppercase">{{ $day->translatedFormat('l j \d\e F Y') }}</h2>
            <label for="kardex-date" class="sr-only">{{ __('agenda.date') }}</label>
            <input id="kardex-date" type="date" wire:model.live="date" max="{{ $today->toDateString() }}" @unless ($canCorrect) min="{{ $earliest->toDateString() }}" @endunless
                   class="h-11 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            <x-ui.button variant="ghost" size="sm" wire:click="goToday">{{ __('agenda.today') }}</x-ui.button>
            @if ($day->toDateString() < $today->toDateString())
                <x-ui.badge variant="warning">{{ __('kardex.late_day') }}</x-ui.badge>
            @endif
        </div>

        {{-- Filtros: hora y teacher (el teacher solo ve a sus alumnos). --}}
        <div class="grid gap-3 border-b border-line p-4 sm:grid-cols-[repeat(2,minmax(0,1fr))_auto] sm:items-end">
            <x-ui.select name="filterSlot" :label="__('agenda.filters.hour')" :options="$filterSlots" :placeholder="__('agenda.filters.all_hours')" wire:model.live="filterSlot" />
            @if ($filterTeachers !== [])
                <x-ui.select name="filterTeacher" :label="__('agenda.filters.teacher')" :options="$filterTeachers" :placeholder="__('agenda.filters.all_teachers')" wire:model.live="filterTeacher" />
            @endif
            @if ($filterSlot !== '' || $filterTeacher !== '')
                <x-ui.button variant="ghost" wire:click="clearFilters">{{ __('agenda.filters.clear') }}</x-ui.button>
            @endif
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->bookings->isEmpty())
                <x-ui.empty-state icon="book" :title="__('kardex.empty')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-base">
                        <thead class="bg-surface text-left text-sm font-bold uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.place') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.teacher') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.student') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('kardex.reserved') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('kardex.graded') }}</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('agenda.actions.grades') }}</span></th>
                            </tr>
                        </thead>
                        @foreach ($this->bookings as $group)
                            <tbody wire:key="slot-{{ $group['slot']?->id }}" class="border-t border-line">
                                <tr class="bg-primary-50">
                                    <th scope="colgroup" colspan="6" class="px-4 py-1 text-left">
                                        <span class="font-display text-lg font-bold tabular-nums text-primary-700">{{ substr((string) $group['slot']?->starts_at, 0, 5) }} – {{ substr((string) $group['slot']?->ends_at, 0, 5) }}</span>
                                    </th>
                                </tr>
                                @foreach ($group['bookings'] as $booking)
                                    <tr wire:key="booking-{{ $booking->id }}" class="border-t border-line leading-tight even:bg-row-alt">
                                        <td class="whitespace-nowrap px-4 py-1.5 font-bold">{{ $booking->placeLabel() }}</td>
                                        <td class="whitespace-nowrap px-4 py-1.5 font-semibold">{{ $booking->teacher?->panelName() ?? '—' }}</td>
                                        <td class="px-4 py-1.5">
                                            {{-- Clic en el alumno: ventana para calificar. --}}
                                            <button type="button" wire:click="open({{ $booking->id }})" title="{{ __('kardex.open') }}"
                                                    class="rounded text-left font-bold text-primary-700 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">{{ $booking->student?->fullName() }}</button>
                                            <span class="text-sm tabular-nums text-ink-700">· {{ $booking->student?->enrollment_number }}</span>
                                            @include('livewire.kardex.partials.pending-presentation', ['student' => $booking->student])
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-1.5">
                                            @if ($booking->kind === \App\Enums\BookingKind::Club)
                                                <span class="text-sm font-bold uppercase text-ink-700">{{ $booking->kind->label() }}</span>
                                            @endif
                                            <span class="font-bold tabular-nums">{{ $booking->activityLabel() }}</span>
                                        </td>
                                        <td class="px-4 py-1.5">
                                            @if ($booking->kardexEntries->isNotEmpty())
                                                <span class="flex flex-wrap gap-1.5">
                                                    @foreach ($booking->kardexEntries as $entry)
                                                        <span @class(['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-semibold', 'bg-success-50 text-success-700' => ! $entry->is_pending, 'bg-warning-50 text-warning-700' => $entry->is_pending])>
                                                            <span class="tabular-nums">{{ $entry->practiceLabel() }}</span><span class="font-bold">{{ $entry->is_pending ? __('kardex.pending_short') : $entry->grade?->code }}</span>
                                                        </span>
                                                    @endforeach
                                                </span>
                                            @elseif ($booking->ungradedReason)
                                                <x-ui.badge variant="warning">{{ __('kardex.ungraded_short', ['reason' => $booking->ungradedReason->name]) }}</x-ui.badge>
                                                @if ($booking->ungraded_comment)
                                                    <span class="block text-sm text-ink-700">{{ $booking->ungraded_comment }}</span>
                                                @endif
                                            @else
                                                <span class="text-sm text-ink-500">{{ __('kardex.pending') }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-1.5 text-right">
                                            {{-- Se califica cuando ya se registró la asistencia en la Agenda. --}}
                                            @if ($booking->status === \App\Enums\BookingStatus::Attended)
                                                <x-ui.button size="sm" variant="outline" class="h-7 text-[15px]" wire:click="open({{ $booking->id }})">{{ __('agenda.actions.grades') }}</x-ui.button>
                                            @else
                                                <span class="text-sm text-ink-500">{{ __('kardex.no_attendance') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </section>
    @endunless

    {{-- Ventana de calificar --}}
    @php($booking = $grading['booking'])
    <x-ui.modal wire:model="gradingId" size="md" :title="__('kardex.modal_title')">
        @if ($booking)
            <div class="flex flex-col gap-1">
                <p class="font-display text-lg font-bold text-ink-900">{{ $booking->student?->fullName() }}</p>
                <p class="text-sm text-ink-700">
                    {{ $booking->date->translatedFormat('l j \d\e F') }} · {{ $booking->slot?->range() }} · {{ $booking->teacher?->panelName() ?? '—' }}
                    · {{ __('kardex.reserved') }}: <span class="font-bold">{{ $booking->activityLabel() }}</span>
                </p>
            </div>

            {{-- Lo ya calificado en esta reservación (el Coordinador y el Administrador lo corrigen). --}}
            @if ($booking->kardexEntries->isNotEmpty())
                <div class="flex flex-col gap-2 rounded-lg border border-line bg-surface p-3">
                    <p class="text-sm font-bold text-ink-900">{{ __('kardex.already_graded') }}</p>
                    @foreach ($booking->kardexEntries as $entry)
                        <div wire:key="entry-{{ $entry->id }}" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="min-w-40 font-bold tabular-nums">{{ $entry->practiceLabel() }}</span>
                            @if ($canCorrect)
                                @php($entryType = ! $entry->kind->isPractice() ? 'signature' : (\App\Models\Activity::gradeTypesFor($entry->school_id)[$entry->activity_number] ?? \App\Enums\GradeType::forActivityNumber((int) $entry->activity_number))->value)
                                <div class="w-44">
                                    <x-ui.select name="corrections.{{ $entry->id }}" :label="__('kardex.grade')" :sr-only-label="true" :options="$grading['byType'][$entryType] ?? []" wire:model="corrections.{{ $entry->id }}" />
                                </div>
                                <x-ui.button variant="ghost" size="sm" wire:click="changeGrade({{ $entry->id }})">{{ __('kardex.actions.correct') }}</x-ui.button>
                                <x-ui.button variant="ghost" size="sm" wire:click="removeEntry({{ $entry->id }})" wire:confirm="{{ __('kardex.confirm_remove') }}">{{ __('kardex.actions.remove') }}</x-ui.button>
                            @elseif ($entry->is_pending)
                                <span class="rounded-full bg-warning-50 px-2.5 py-0.5 font-bold text-warning-700">{{ __('kardex.pending_presentation') }}</span>
                            @else
                                <span class="rounded-full bg-success-50 px-2.5 py-0.5 font-bold text-success-700">{{ $entry->grade?->code }} · {{ $entry->grade?->name }}</span>
                            @endif
                            @if ($entry->observationReason)
                                <span class="rounded-full bg-primary-50 px-2.5 py-0.5 text-xs font-semibold text-primary-700">{{ $entry->observationReason->name }}</span>
                            @endif
                            <span class="text-ink-500">{{ $entry->user?->name }}@if ($entry->late_reason_id) · {{ __('kardex.late') }}@endif</span>
                        </div>
                    @endforeach
                    @error('correction')
                        <p class="text-[13px] font-medium text-danger-700">{{ $message }}</p>
                    @enderror
                </div>
            @endif

            @if (! $grading['gradable'])
                <p class="rounded-lg bg-warning-50 p-3 text-sm font-semibold text-warning-700">{{ __('kardex.too_old', ['days' => $daysBack]) }}</p>
            @elseif ($grading['steps'] === [])
                <p class="rounded-lg bg-surface p-3 text-sm text-ink-700">{{ __('kardex.nothing_to_grade') }}</p>
            @else
                <div class="flex gap-2" role="tablist">
                    <x-ui.button :variant="$ungraded ? 'ghost' : 'primary'" size="sm" wire:click="$set('ungraded', false)">{{ __('kardex.tab_grade') }}</x-ui.button>
                    <x-ui.button :variant="$ungraded ? 'primary' : 'ghost'" size="sm" wire:click="$set('ungraded', true)">{{ __('kardex.tab_ungraded') }}</x-ui.button>
                </div>

                @if (! $ungraded && ! $grading['attended'])
                    {{-- Primero se registra la asistencia en la Agenda; "Sin calificación" sí se puede. --}}
                    <p class="rounded-lg bg-warning-50 p-3 text-sm font-semibold text-warning-700">{{ __('kardex.not_attended') }}</p>
                @elseif (! $ungraded)
                    {{-- Las 3 prácticas que siguen en el Kardex: se califican en orden, sin saltarse ninguna. --}}
                    <p class="text-sm text-ink-700">{{ __('kardex.in_order_hint') }}</p>
                    <ol class="flex flex-col gap-3">
                        @foreach ($grading['steps'] as $position => $step)
                            <li wire:key="step-{{ $gradingId }}-{{ $position }}" class="grid items-center gap-2 rounded-lg border border-line p-3 sm:grid-cols-[1fr_12rem_14rem]">
                                <span>
                                    <span class="block font-bold tabular-nums">{{ $step['label'] }}</span>
                                    <span class="text-xs text-ink-500">
                                        {{ $position === 0 ? __('kardex.kardex_says') : __('kardex.if_advanced') }}
                                        @isset($step['order']) · {{ __('catalogs.practices.order') }} {{ $step['order'] }} @endisset
                                        · {{ $step['type']->label() }}
                                        @if ($step['is_presentation'] ?? false) · <span class="font-semibold text-warning-700">{{ __('kardex.presentation_step') }}</span>@endif
                                    </span>
                                </span>
                                <x-ui.select name="grades.{{ $position }}" :label="__('kardex.grade_for', ['practice' => $step['label']])" :sr-only-label="true"
                                             :options="$grading['options'][$position]" :placeholder="__('kardex.no_grade')" wire:model="grades.{{ $position }}" />
                                <x-ui.select name="observations.{{ $position }}" :label="__('kardex.observation_for', ['practice' => $step['label']])" :sr-only-label="true"
                                             :options="$reasonOptions" :placeholder="__('kardex.no_observation')" wire:model="observations.{{ $position }}" />
                            </li>
                        @endforeach
                    </ol>
                    @error('grades')
                        <p class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700"><x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}</p>
                    @enderror

                    @if ($grading['late'])
                        {{-- Calificada días después: motivo y comentario. --}}
                        <div class="flex flex-col gap-3 rounded-lg bg-warning-50 p-3">
                            <p class="text-sm font-semibold text-warning-700">{{ __('kardex.late_hint') }}</p>
                            <x-ui.select name="lateReasonId" :label="__('kardex.reason')" :options="$reasonOptions" :placeholder="__('kardex.choose_reason')" wire:model="lateReasonId" />
                            <x-ui.textarea name="lateComment" :label="__('kardex.comment')" wire:model="lateComment" rows="2" maxlength="255" />
                        </div>
                    @endif
                @else
                    {{-- Sin calificación: motivo del catálogo y comentario; el Kardex no avanza. --}}
                    <p class="text-sm text-ink-700">{{ __('kardex.ungraded_hint') }}</p>
                    <x-ui.select name="ungradedReasonId" :label="__('kardex.reason')" :options="$reasonOptions" :placeholder="__('kardex.choose_reason')" wire:model="ungradedReasonId" />
                    <x-ui.textarea name="ungradedComment" :label="__('kardex.comment')" wire:model="ungradedComment" rows="2" maxlength="255" />
                @endif
            @endif
        @endif
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
            @if ($booking && $grading['gradable'] && $grading['steps'] !== [])
                @if ($ungraded)
                    <x-ui.button wire:click="saveUngraded">{{ __('kardex.actions.save_ungraded') }}</x-ui.button>
                @elseif ($grading['attended'])
                    <x-ui.button wire:click="save">{{ __('kardex.actions.save') }}</x-ui.button>
                @endif
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
