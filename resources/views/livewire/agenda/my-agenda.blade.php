<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($clubsOnly ? 'agenda.my_clubs_title' : 'agenda.my_title')" :subtitle="__('agenda.my_subtitle')" />

    @if (! $this->student)
        <x-ui.empty-state icon="calendar" :title="__('agenda.no_student')" />
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <section class="flex flex-col gap-4 rounded-xl border border-line bg-white p-4 shadow-xs md:p-5">
                <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('agenda.date') }}">
                    @foreach ($days as $day)
                        <button type="button" role="tab" wire:key="day-{{ $day->toDateString() }}" wire:click="selectDay('{{ $day->toDateString() }}')"
                                aria-selected="{{ $day->toDateString() === $date ? 'true' : 'false' }}"
                                @class([
                                    'flex min-w-16 flex-col items-center rounded-lg border px-3 py-1.5 text-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                    'border-primary-500 bg-primary-50 text-primary-700' => $day->toDateString() === $date,
                                    'border-line hover:border-primary-500' => $day->toDateString() !== $date,
                                ])>
                            <span class="text-xs font-semibold uppercase">{{ $day->translatedFormat('D') }}</span>
                            <span class="font-display text-lg font-bold tabular-nums">{{ $day->day }}</span>
                        </button>
                    @endforeach
                </div>

                <p class="text-sm text-ink-500">
                    @if ($nextActivity){{ __('agenda.next_activity', ['label' => $nextActivity['label']]) }} · @endif
                    {{ __('agenda.daily_limit_note', ['hours' => $dailyLimit]) }}
                </p>

                <div wire:loading.delay.class="opacity-60" class="transition-opacity">
                    @include('livewire.agenda.partials.options', ['options' => $this->options])
                </div>

                <div class="flex justify-end">
                    <x-ui.button wire:click="book" :disabled="$option === ''" wire:loading.attr="disabled">{{ __('agenda.actions.book') }}</x-ui.button>
                </div>
            </section>

            <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
                <h2 class="border-b border-line px-4 py-3 font-display text-h3 font-bold">{{ __('agenda.my_bookings') }}</h2>
                @if ($this->bookings->isEmpty())
                    <p class="p-4 text-sm text-ink-500">{{ __('agenda.my_bookings_empty') }}</p>
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($this->bookings as $booking)
                            <li wire:key="mine-{{ $booking->id }}" class="flex items-start gap-3 p-4">
                                <div class="flex min-w-0 flex-1 flex-col gap-0.5 text-sm">
                                    <span class="font-bold first-letter:uppercase">{{ $booking->date->translatedFormat('D j M') }} · {{ substr((string) $booking->slot?->starts_at, 0, 5) }}</span>
                                    <span>{{ $booking->kind->label() }} · {{ $booking->modality === \App\Enums\ClubModality::Online ? __('agenda.online') : __('agenda.in_person') }} · <span class="font-semibold tabular-nums">{{ $booking->activityLabel() }}</span></span>
                                    <span @class(['font-semibold', 'text-warning-700' => $booking->is_waitlist, 'text-primary-700' => ! $booking->is_waitlist])>{{ $booking->placeLabel() }}</span>
                                    @if ($booking->is_waitlist)
                                        <span class="text-xs text-warning-700">{{ __('agenda.waitlist_note') }}</span>
                                    @endif
                                </div>
                                @if ($booking->status === \App\Enums\BookingStatus::Booked)
                                    <x-ui.button size="sm" variant="ghost" class="text-danger-700 hover:bg-danger-50" wire:click="cancel({{ $booking->id }})"
                                                 wire:confirm="{{ __('agenda.actions.confirm_cancel') }}?">{{ __('agenda.actions.cancel') }}</x-ui.button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif
</div>
