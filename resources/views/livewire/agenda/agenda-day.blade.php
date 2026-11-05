<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('agenda.title')" :subtitle="__('agenda.subtitle')">
        <x-slot:actions>
            @if ($canManage && $isReady)
                <x-ui.button wire:click="create"><x-ui.icon name="plus" class="size-4" />{{ __('agenda.actions.new') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Semana: navegación, fecha y leyenda --}}
    @php($nav = 'flex size-11 items-center justify-center rounded-md border-[1.5px] border-line-strong bg-white text-ink-700 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" wire:click="shiftWeek(-1)" class="{{ $nav }}" aria-label="{{ __('agenda.previous_week') }}" title="{{ __('agenda.previous_week') }}">‹</button>
        <h2 class="min-w-56 text-center font-display text-h3 font-bold">{{ __('agenda.week_of', ['from' => $days[0]->translatedFormat('j M'), 'to' => end($days)->translatedFormat('j M Y')]) }}</h2>
        <button type="button" wire:click="shiftWeek(1)" class="{{ $nav }}" aria-label="{{ __('agenda.next_week') }}" title="{{ __('agenda.next_week') }}">›</button>
        <label for="agenda-date" class="sr-only">{{ __('agenda.date') }}</label>
        <input id="agenda-date" type="date" wire:model.live="date"
               class="h-11 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
        <x-ui.button variant="ghost" size="sm" wire:click="goToday">{{ __('agenda.today') }}</x-ui.button>
        @include('livewire.agenda.partials.legend')
    </div>

    @if ($isReady)
        {{-- Calendario colapsable: al ocultarlo queda a la vista el listado del día. --}}
        <div class="flex flex-col gap-2">
            <button type="button" wire:click="toggleCalendar" aria-expanded="{{ $calendarCollapsed ? 'false' : 'true' }}" aria-controls="agenda-calendar"
                    class="flex w-full items-center justify-between gap-3 rounded-xl border border-line bg-white px-4 py-3 text-left shadow-xs transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                <span class="flex items-center gap-2 font-display font-bold text-primary-700">
                    <x-ui.icon name="calendar" class="size-5" />{{ __('agenda.calendar') }}
                    <span class="text-sm font-semibold text-ink-500">· {{ __($calendarCollapsed ? 'agenda.calendar_show' : 'agenda.calendar_hide') }}</span>
                </span>
                <x-ui.icon name="chevron-down" @class(['size-5 text-ink-500 transition-transform', 'rotate-180' => ! $calendarCollapsed]) />
            </button>
            @unless ($calendarCollapsed)
                <div id="agenda-calendar" wire:loading.delay.class="opacity-60" class="transition-opacity">
                    @include('livewire.agenda.partials.week-grid', ['selectedDate' => $day->toDateString(), 'canPick' => $canManage, 'canSelectDay' => true])
                </div>
            @endunless
        </div>
    @endif

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Día elegido --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-line p-4">
            <button type="button" wire:click="shiftDay(-1)" class="{{ $nav }}" aria-label="{{ __('agenda.previous_day') }}" title="{{ __('agenda.previous_day') }}">‹</button>
            <button type="button" wire:click="shiftDay(1)" class="{{ $nav }}" aria-label="{{ __('agenda.next_day') }}" title="{{ __('agenda.next_day') }}">›</button>
            <h2 class="font-display text-lg font-bold first-letter:uppercase">{{ $day->translatedFormat('l j \d\e F Y') }}</h2>
        </div>

        {{-- Lugares liberados con alumnos en lista de espera --}}
        @if ($canManage && $this->proposals->isNotEmpty())
            <div class="flex flex-col gap-2 border-b border-line bg-warning-50 p-4">
                <p class="text-sm font-bold text-warning-700">{{ __('agenda.proposals') }} · <span class="font-normal">{{ __('agenda.proposals_hint') }}</span></p>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($this->proposals as $proposal)
                        <li wire:key="proposal-{{ $proposal->id }}" class="flex items-center gap-2 rounded-lg border border-warning-200 bg-white px-3 py-1.5 text-sm">
                            <span class="font-semibold tabular-nums">{{ substr((string) $proposal->slot?->starts_at, 0, 5) }}</span>
                            <span>{{ $proposal->student?->fullName() }}</span>
                            <x-ui.button size="sm" variant="secondary" wire:click="promote({{ $proposal->id }})">{{ __('agenda.actions.promote') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Filtros del listado: hora, aula y teacher --}}
        <div class="grid gap-3 border-b border-line p-4 sm:grid-cols-[repeat(3,minmax(0,1fr))_auto] sm:items-end">
            <x-ui.select name="filterSlot" :label="__('agenda.filters.hour')" :options="$filterSlots" :placeholder="__('agenda.filters.all_hours')" wire:model.live="filterSlot" />
            <x-ui.select name="filterRoom" :label="__('agenda.filters.room')" :options="$filterRooms" :placeholder="__('agenda.filters.all_rooms')" wire:model.live="filterRoom" />
            <x-ui.select name="filterTeacher" :label="__('agenda.filters.teacher')" :options="$filterTeachers" :placeholder="__('agenda.filters.all_teachers')" wire:model.live="filterTeacher" />
            @if ($filterSlot !== '' || $filterRoom !== '' || $filterTeacher !== '')
                <x-ui.button variant="ghost" wire:click="clearFilters">{{ __('agenda.filters.clear') }}</x-ui.button>
            @endif
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if (! $isReady)
                <x-ui.empty-state icon="calendar" :title="__('agenda.week_not_ready')" />
            @elseif ($this->bookings->isEmpty())
                <x-ui.empty-state icon="calendar" :title="__('agenda.empty')" :description="$canManage ? __('agenda.empty_hint') : null" />
            @else
                {{-- Letra un poco más grande para leer de lejos; cada aula con su color (como en el panel). --}}
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-base">
                        <thead class="bg-surface text-left text-sm font-bold uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.place') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.teacher') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.student') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.activity') }}</th>
                                <th scope="col" class="px-4 py-3">{{ __('agenda.status') }}</th>
                                @if ($canManage)
                                    <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('agenda.actions.cancel') }}</span></th>
                                @endif
                            </tr>
                        </thead>
                        @foreach ($this->bookings as $group)
                            <tbody wire:key="slot-{{ $group['slot']?->id }}" class="border-t border-line">
                                <tr class="bg-primary-50">
                                    <th scope="colgroup" colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-2.5 text-left font-display text-lg font-bold tabular-nums text-primary-700">
                                        {{ substr((string) $group['slot']?->starts_at, 0, 5) }} – {{ substr((string) $group['slot']?->ends_at, 0, 5) }}
                                    </th>
                                </tr>
                                @foreach ($group['bookings'] as $booking)
                                    @php($active = $booking->status->isActive())
                                    @php($tint = $booking->classroom_id && ! $booking->is_waitlist ? \App\Support\RoomColors::tint($roomNumbers[$booking->classroom_id] ?? 1) : 'even:bg-row-alt')
                                    <tr wire:key="booking-{{ $booking->id }}" @class(['border-t border-line', $tint, 'text-ink-400' => ! $active])>
                                        <td class="px-4 py-3">
                                            <span @class(['font-bold', 'text-warning-700' => $booking->is_waitlist && $active])>{{ $booking->placeLabel() }}</span>
                                            <span class="block text-sm text-ink-700">{{ $booking->modality === \App\Enums\ClubModality::Online ? __('agenda.online') : __('agenda.in_person') }}</span>
                                        </td>
                                        <td class="px-4 py-3 font-semibold">{{ $booking->teacher?->panelName() ?? '—' }}</td>
                                        <td class="px-4 py-3">
                                            @if ($canManage && $booking->canBeChanged())
                                                <button type="button" wire:click="editBooking({{ $booking->id }})" title="{{ __('agenda.edit.open') }}"
                                                        class="rounded text-left font-bold text-primary-700 underline-offset-2 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                                    {{ $booking->student?->fullName() }}
                                                </button>
                                            @else
                                                <span class="font-bold">{{ $booking->student?->fullName() }}</span>
                                            @endif
                                            <span class="block text-sm text-ink-700 tabular-nums">{{ $booking->student?->enrollment_number }}@if ($booking->booked_by_reception) · {{ __('agenda.by_reception') }}@endif</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="text-sm font-bold uppercase text-ink-700">{{ $booking->kind->label() }}</span>
                                            <span class="block font-bold tabular-nums">{{ $booking->activityLabel() }}</span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <x-ui.badge :variant="match ($booking->status) { \App\Enums\BookingStatus::Attended => 'success', \App\Enums\BookingStatus::Cancelled => 'neutral', default => $booking->is_waitlist ? 'warning' : 'info' }">
                                                {{ $booking->status->label() }}
                                            </x-ui.badge>
                                            @if ($booking->cancellationReason)
                                                <span class="mt-1 block text-sm">{{ $booking->cancellationReason->name }}</span>
                                            @endif
                                        </td>
                                        @if ($canManage)
                                            <td class="px-4 py-3">
                                                @if ($active)
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        @if ($booking->canBeChanged())
                                                            <x-ui.button size="sm" variant="outline" class="text-[15px]" wire:click="editBooking({{ $booking->id }})" :title="__('agenda.edit.open')">{{ __('agenda.actions.change') }}</x-ui.button>
                                                        @endif
                                                        @if ($booking->status !== \App\Enums\BookingStatus::Attended && ! $booking->is_waitlist)
                                                            <x-ui.button size="sm" variant="secondary" class="text-[15px]" wire:click="markAttended({{ $booking->id }})">{{ __('agenda.actions.attended') }}</x-ui.button>
                                                        @endif
                                                        <x-ui.button size="sm" variant="ghost" class="text-[15px] text-danger-700 hover:bg-danger-50" wire:click="confirmCancel({{ $booking->id }})">{{ __('agenda.actions.cancel') }}</x-ui.button>
                                                    </div>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </section>

    @if ($canManage)
        {{-- Nueva reservación: se elige al alumno y una opción; el aula la asigna el sistema. --}}
        <x-ui.modal wire:model="creating" size="md" :title="__('agenda.form.title')">
            <p class="-mt-3 text-sm font-semibold text-ink-500 first-letter:uppercase">{{ $day->translatedFormat('l j \d\e F Y') }}@if ($slotLabel) · {{ $slotLabel }}@endif</p>

            @if ($this->selectedStudent)
                <div class="flex items-center justify-between gap-3 rounded-lg border border-line bg-surface p-3">
                    <div class="flex min-w-0 flex-col">
                        <span class="font-bold">{{ $this->selectedStudent->fullName() }}</span>
                        <span class="text-xs text-ink-500 tabular-nums">
                            {{ $this->selectedStudent->enrollment_number }}
                            @if ($nextActivity) · {{ __('agenda.next_activity', ['label' => $nextActivity['label']]) }}@endif
                            · {{ __('agenda.daily_limit_note', ['hours' => $dailyLimit]) }}
                        </span>
                    </div>
                    <x-ui.button size="sm" variant="ghost" wire:click="clearStudent">{{ __('agenda.actions.change_student') }}</x-ui.button>
                </div>

                @include('livewire.agenda.partials.options', ['options' => $this->options])
            @else
                <x-ui.input name="search" :label="__('agenda.form.search')" :placeholder="__('agenda.form.search_placeholder')" wire:model.live.debounce.300ms="search" autocomplete="off" />
                @if (mb_strlen(trim($search)) >= 2)
                    <ul class="flex flex-col gap-1.5">
                        @forelse ($this->students as $student)
                            <li wire:key="pick-{{ $student->id }}">
                                <button type="button" wire:click="pickStudent({{ $student->id }})"
                                        class="flex w-full items-center justify-between gap-3 rounded-lg border border-line px-3 py-2 text-left transition hover:border-primary-500 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                    <span class="font-semibold">{{ $student->fullName() }}</span>
                                    <span class="text-xs text-ink-500 tabular-nums">{{ $student->enrollment_number }}</span>
                                </button>
                            </li>
                        @empty
                            <li class="text-sm text-ink-500">{{ __('agenda.form.no_results') }}</li>
                        @endforelse
                    </ul>
                @endif
            @endif

            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
                @if ($this->selectedStudent)
                    <x-ui.button wire:click="book" :disabled="$option === ''" wire:loading.attr="disabled">{{ __('agenda.actions.book') }}</x-ui.button>
                @endif
            </x-slot:footer>
        </x-ui.modal>

        {{-- Cambiar reservación: aula y teacher por separado, o pasar el lugar a otro alumno. --}}
        <x-ui.modal wire:model="editingId" size="md" :title="__('agenda.edit.title')">
            @if ($edit['booking'])
                @php($editable = $edit['booking']->kind === \App\Enums\BookingKind::Session)
                <p class="-mt-3 text-sm font-semibold text-ink-500 first-letter:uppercase">
                    {{ $edit['booking']->date->translatedFormat('l j \d\e F') }} · {{ $edit['booking']->slot?->range() }} · {{ $edit['booking']->kind->label() }} {{ $edit['booking']->activityLabel() }}
                </p>
                @if ($editable)
                    <x-ui.select name="editRoomId" :label="__('agenda.edit.room')" :options="$edit['rooms']" :hint="__('agenda.edit.room_hint')" wire:model.live="editRoomId" required />
                    @php($chosen = $edit['choices'][$editRoomId] ?? null)
                    @if ($chosen)
                        <p class="-mt-2 rounded-lg bg-primary-50 px-3 py-2 text-[15px] text-primary-700">
                            {{ __('agenda.edit.teacher_of_room') }} <span class="font-bold">{{ $chosen['teacher'] !== '' ? $chosen['teacher'] : '—' }}</span>
                        </p>
                    @endif
                @else
                    <p class="rounded-lg bg-surface p-3 text-sm text-ink-700">{{ __('agenda.edit.club_note') }}</p>
                @endif

                {{-- Pasar el lugar: solo a alumnos de la lista de espera de ese día y hora. --}}
                <div class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-ink-700">{{ __('agenda.edit.student') }}</span>
                    <div class="rounded-lg border border-line bg-surface px-3 py-2">
                        <span class="font-bold">{{ $edit['booking']->student?->fullName() }}</span>
                        <span class="ml-2 text-sm text-ink-500 tabular-nums">{{ $edit['booking']->student?->enrollment_number }}</span>
                    </div>
                    @if ($editable && ! $edit['booking']->is_waitlist)
                        <span class="text-sm font-semibold text-ink-700">{{ __('agenda.edit.give_seat') }}</span>
                        @if ($edit['waiting']->isEmpty())
                            <p class="text-sm text-ink-500">{{ __('agenda.edit.no_waiting') }}</p>
                        @else
                            <ul class="flex flex-col gap-1.5">
                                @foreach ($edit['waiting'] as $waiting)
                                    @php($picked = $editWaitingId === $waiting->id)
                                    <li wire:key="waiting-{{ $waiting->id }}">
                                        <button type="button" wire:click="$set('editWaitingId', {{ $picked ? 'null' : $waiting->id }})" aria-pressed="{{ $picked ? 'true' : 'false' }}"
                                                @class(['flex w-full items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                    'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $picked, 'border-line hover:border-primary-500' => ! $picked])>
                                            <span class="font-semibold">{{ $waiting->student?->fullName() }}</span>
                                            <span class="text-xs text-ink-500 tabular-nums">{{ $waiting->student?->enrollment_number }} · {{ $waiting->modality === \App\Enums\ClubModality::Online ? __('agenda.online') : __('agenda.in_person') }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($editWaitingId)
                                <x-ui.select name="editReasonId" :label="__('agenda.edit.reason_for', ['name' => $edit['booking']->student?->fullName()])" :options="$reasonOptions"
                                             :placeholder="__('students.select')" wire:model="editReasonId" required />
                            @endif
                        @endif
                    @endif
                </div>
            @endif
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
                <x-ui.button wire:click="saveChange" wire:loading.attr="disabled">{{ __('agenda.edit.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal wire:model="cancellingId" :title="__('agenda.form.cancel_title')">
            <x-ui.select name="reasonId" :label="__('agenda.form.reason')" :options="$reasonOptions" :placeholder="__('students.select')" wire:model="reasonId" required />
            <p class="-mt-2 text-xs text-ink-500">{{ __('agenda.form.reason_hint') }}</p>
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="cancel">{{ __('agenda.actions.confirm_cancel') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
