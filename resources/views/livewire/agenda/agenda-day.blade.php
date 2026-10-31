<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('agenda.title')" :subtitle="__('agenda.subtitle')">
        <x-slot:actions>
            @if ($canManage && $isReady)
                <x-ui.button wire:click="create"><x-ui.icon name="plus" class="size-4" />{{ __('agenda.actions.new') }}</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Fecha --}}
        <div class="flex flex-wrap items-center gap-2 border-b border-line p-4">
            @php($nav = 'flex size-11 items-center justify-center rounded-md border-[1.5px] border-line-strong bg-white text-ink-700 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
            <button type="button" wire:click="shiftDay(-1)" class="{{ $nav }}" aria-label="{{ __('agenda.previous_day') }}" title="{{ __('agenda.previous_day') }}">
                ‹
            </button>
            <label for="agenda-date" class="sr-only">{{ __('agenda.date') }}</label>
            <input id="agenda-date" type="date" wire:model.live="date"
                   class="h-11 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            <button type="button" wire:click="shiftDay(1)" class="{{ $nav }}" aria-label="{{ __('agenda.next_day') }}" title="{{ __('agenda.next_day') }}">
                ›
            </button>
            <x-ui.button variant="ghost" size="sm" wire:click="goToday">{{ __('agenda.today') }}</x-ui.button>
            <span class="ml-auto font-display text-lg font-bold first-letter:uppercase">{{ $day->translatedFormat('l j \d\e F Y') }}</span>
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

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if (! $isReady)
                <x-ui.empty-state icon="calendar" :title="__('agenda.week_not_ready')" />
            @elseif ($this->bookings->isEmpty())
                <x-ui.empty-state icon="calendar" :title="__('agenda.empty')" :description="$canManage ? __('agenda.empty_hint') : null" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead class="bg-surface text-left text-xs font-bold uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-4 py-2.5">{{ __('agenda.place') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('agenda.teacher') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('agenda.student') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('agenda.activity') }}</th>
                                <th scope="col" class="px-4 py-2.5">{{ __('agenda.status') }}</th>
                                @if ($canManage)
                                    <th scope="col" class="px-4 py-2.5"><span class="sr-only">{{ __('agenda.actions.cancel') }}</span></th>
                                @endif
                            </tr>
                        </thead>
                        @foreach ($this->bookings as $group)
                            <tbody wire:key="slot-{{ $group['slot']?->id }}" class="border-t border-line">
                                <tr class="bg-primary-50">
                                    <th scope="colgroup" colspan="{{ $canManage ? 6 : 5 }}" class="px-4 py-2 text-left font-display font-bold tabular-nums text-primary-700">
                                        {{ substr((string) $group['slot']?->starts_at, 0, 5) }} – {{ substr((string) $group['slot']?->ends_at, 0, 5) }}
                                    </th>
                                </tr>
                                @foreach ($group['bookings'] as $booking)
                                    @php($active = $booking->status->isActive())
                                    <tr wire:key="booking-{{ $booking->id }}" @class(['border-t border-line even:bg-row-alt', 'text-ink-400' => ! $active])>
                                        <td class="px-4 py-2.5">
                                            <span @class(['font-semibold', 'text-warning-700' => $booking->is_waitlist && $active])>{{ $booking->placeLabel() }}</span>
                                            <span class="block text-xs text-ink-500">{{ $booking->modality === \App\Enums\ClubModality::Online ? __('agenda.online') : __('agenda.in_person') }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">{{ $booking->teacher?->panelName() ?? '—' }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="font-semibold">{{ $booking->student?->fullName() }}</span>
                                            <span class="block text-xs text-ink-500 tabular-nums">{{ $booking->student?->enrollment_number }}@if ($booking->booked_by_reception) · {{ __('agenda.by_reception') }}@endif</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="text-xs font-bold uppercase text-ink-500">{{ $booking->kind->label() }}</span>
                                            <span class="block font-semibold tabular-nums">{{ $booking->activityLabel() }}</span>
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <x-ui.badge :variant="match ($booking->status) { \App\Enums\BookingStatus::Attended => 'success', \App\Enums\BookingStatus::Cancelled => 'neutral', default => $booking->is_waitlist ? 'warning' : 'info' }">
                                                {{ $booking->status->label() }}
                                            </x-ui.badge>
                                            @if ($booking->cancellation_reason)
                                                <span class="mt-1 block text-xs">{{ $booking->cancellation_reason->label() }}</span>
                                            @endif
                                        </td>
                                        @if ($canManage)
                                            <td class="px-4 py-2.5">
                                                @if ($active)
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        @if ($booking->status !== \App\Enums\BookingStatus::Attended && ! $booking->is_waitlist)
                                                            <x-ui.button size="sm" variant="secondary" wire:click="markAttended({{ $booking->id }})">{{ __('agenda.actions.attended') }}</x-ui.button>
                                                        @endif
                                                        <x-ui.button size="sm" variant="ghost" class="text-danger-700 hover:bg-danger-50" wire:click="confirmCancel({{ $booking->id }})">{{ __('agenda.actions.cancel') }}</x-ui.button>
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
            <p class="-mt-3 text-sm font-semibold text-ink-500 first-letter:uppercase">{{ $day->translatedFormat('l j \d\e F Y') }}</p>

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

        <x-ui.modal wire:model="cancellingId" :title="__('agenda.form.cancel_title')">
            <x-ui.select name="reason" :label="__('agenda.form.reason')" :options="$reasonOptions" wire:model="reason" required />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
                <x-ui.button variant="danger" wire:click="cancel">{{ __('agenda.actions.confirm_cancel') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
