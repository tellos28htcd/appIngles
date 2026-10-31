<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($clubsOnly ? 'agenda.my_clubs_title' : 'agenda.my_title')" :subtitle="__('agenda.my_subtitle')" />

    @if (! $this->student)
        <x-ui.empty-state icon="calendar" :title="__('agenda.no_student')" />
    @else
        @php($nav = 'flex size-11 items-center justify-center rounded-md border-[1.5px] border-line-strong bg-white text-ink-700 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 disabled:opacity-40 disabled:pointer-events-none')
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" wire:click="shiftWeek(-1)" class="{{ $nav }}" @disabled(! $canGoBack) aria-label="{{ __('agenda.previous_week') }}" title="{{ __('agenda.previous_week') }}">‹</button>
            <h2 class="min-w-56 text-center font-display text-h3 font-bold">{{ __('agenda.week_of', ['from' => $days[0]->translatedFormat('j M'), 'to' => end($days)->translatedFormat('j M Y')]) }}</h2>
            <button type="button" wire:click="shiftWeek(1)" class="{{ $nav }}" @disabled(! $canGoForward) aria-label="{{ __('agenda.next_week') }}" title="{{ __('agenda.next_week') }}">›</button>
            <p class="text-sm text-ink-500">
                @if ($nextActivity){{ __('agenda.next_activity', ['label' => $nextActivity['label']]) }} · @endif
                {{ __('agenda.daily_limit_note', ['hours' => $dailyLimit]) }}
            </p>
            @include('livewire.agenda.partials.legend')
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($isReady)
                @include('livewire.agenda.partials.week-grid', ['selectedDate' => null, 'canPick' => true, 'canSelectDay' => false])
            @else
                <x-ui.empty-state icon="calendar" :title="__('agenda.week_not_ready')" />
            @endif
        </div>

        <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
            <h2 class="border-b border-line px-4 py-3 font-display text-h3 font-bold">{{ __('agenda.my_bookings') }}</h2>
            @if ($this->bookings->isEmpty())
                <p class="p-4 text-sm text-ink-500">{{ __('agenda.my_bookings_empty') }}</p>
            @else
                <ul class="grid divide-y divide-line md:grid-cols-2 md:divide-y-0">
                    @foreach ($this->bookings as $booking)
                        <li wire:key="mine-{{ $booking->id }}" class="flex items-start gap-3 border-line p-4 md:border-b">
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

        <x-ui.modal wire:model="choosing" size="md" :title="__('agenda.form.choose_title')">
            <p class="-mt-3 text-sm font-semibold text-ink-500 first-letter:uppercase">
                {{ $date ? \Carbon\CarbonImmutable::parse($date)->translatedFormat('l j \d\e F') : '' }}@if ($slotLabel) · {{ $slotLabel }}@endif
            </p>
            @include('livewire.agenda.partials.options', ['options' => $this->options])
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('agenda.actions.close') }}</x-ui.button>
                <x-ui.button wire:click="book" :disabled="$option === ''" wire:loading.attr="disabled">{{ __('agenda.actions.book') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
