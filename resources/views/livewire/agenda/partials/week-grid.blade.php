{{-- Calendario semanal de la agenda: por día y hora, lugares de asesoría (presencial / en línea) y de cada club.
     Variables: $days, $scheduleSlots, $availability, $holidays, $planner, $selectedDate (o null), $canPick (clic en la celda), $canSelectDay. --}}
<section class="overflow-x-auto rounded-xl border border-line bg-white shadow-xs">
    <table class="w-full min-w-[760px] border-separate border-spacing-0 text-center text-sm">
        <thead>
            <tr>
                <th scope="col" class="w-20 border-b border-line bg-primary-700 px-2 py-2 text-xs font-bold uppercase text-on-primary">{{ __('agenda.hour') }}</th>
                @foreach ($days as $day)
                    @php($holiday = $holidays[$day->toDateString()] ?? null)
                    @php($selected = $selectedDate === $day->toDateString())
                    <th scope="col" @class([
                        'border-b border-l border-line p-0',
                        'bg-primary-700 text-on-primary' => ! $holiday && ! $selected,
                        'bg-accent-500 text-on-accent' => ! $holiday && $selected,
                        'bg-warning-500 text-white' => $holiday,
                    ])>
                        @if ($canSelectDay)
                            <button type="button" wire:click="selectDay('{{ $day->toDateString() }}')" class="w-full px-2 py-2 transition hover:opacity-85 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white"
                                    aria-pressed="{{ $selected ? 'true' : 'false' }}" title="{{ __('agenda.see_day') }}">
                        @else
                            <div class="px-2 py-2">
                        @endif
                            <span class="block font-display text-base font-bold">{{ \Illuminate\Support\Str::ucfirst($day->translatedFormat('l')) }}</span>
                            <span class="block text-xs font-semibold">{{ $day->translatedFormat('j M') }}</span>
                            @if ($holiday)
                                <span class="block truncate text-[11px]">{{ $holiday }}</span>
                            @endif
                        @if ($canSelectDay)
                            </button>
                        @else
                            </div>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($scheduleSlots as $slot)
                <tr wire:key="agenda-slot-{{ $slot->id }}" @class(['bg-white' => $loop->odd, 'bg-row-alt' => $loop->even])>
                    <th scope="row" class="border-b border-line px-2 py-1 font-mono text-xs font-bold tabular-nums text-ink-700">{{ substr($slot->starts_at, 0, 5) }}</th>
                    @foreach ($days as $day)
                        @php($cell = isset($holidays[$day->toDateString()]) || ! $planner->slotAppliesOn($slot, $day->dayOfWeekIso) ? null : ($availability[$day->toDateString().'|'.$slot->id] ?? null))
                        @php($hasPlaces = $cell && ($cell['session']['capacity'] > 0 || $cell['online']['capacity'] > 0 || $cell['clubs'] !== []))
                        @php($clickable = $canPick && $hasPlaces && ! $cell['past'])
                        <td @class(['h-14 min-w-32 border-b border-l border-line p-1 align-top', 'bg-surface-2' => ! $hasPlaces, 'opacity-60' => $cell && $cell['past']])>
                            @if ($hasPlaces)
                                <button type="button" @if ($clickable) wire:click="openSlot('{{ $day->toDateString() }}', {{ $slot->id }})" @else disabled @endif
                                        @class(['flex w-full flex-col gap-1 rounded-md p-0.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500', 'hover:bg-accent-100' => $clickable, 'cursor-default' => ! $clickable])
                                        aria-label="{{ __('agenda.book_at', ['day' => $day->translatedFormat('l j'), 'hour' => $slot->range()]) }}">
                                    @if ($cell['session']['capacity'] > 0)
                                        @php($sessionFree = $cell['session']['free'])
                                        <span @class([
                                            'flex items-center justify-between gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold leading-tight',
                                            'bg-success-50 text-success-700' => $sessionFree > 0,
                                            'bg-warning-50 text-warning-700' => $sessionFree === 0 && $cell['waitlist'],
                                            'bg-danger-50 text-danger-700' => $sessionFree === 0 && ! $cell['waitlist'],
                                        ])>
                                            <span>{{ __('agenda.grid.session') }}</span>
                                            <span class="tabular-nums">{{ $sessionFree > 0 ? $sessionFree.'/'.$cell['session']['capacity'] : ($cell['waitlist'] ? __('agenda.grid.waitlist') : __('agenda.grid.full')) }}</span>
                                        </span>
                                    @endif
                                    @if ($cell['online']['capacity'] > 0)
                                        <span @class(['flex items-center justify-between gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold leading-tight',
                                            'bg-info-50 text-info-700' => $cell['online']['free'] > 0, 'bg-danger-50 text-danger-700' => $cell['online']['free'] === 0])>
                                            <span>{{ __('agenda.grid.online') }}</span>
                                            <span class="tabular-nums">{{ $cell['online']['free'] > 0 ? $cell['online']['free'].'/'.$cell['online']['capacity'] : __('agenda.grid.full') }}</span>
                                        </span>
                                    @endif
                                    @foreach ($cell['clubs'] as $club)
                                        <span @class(['flex items-center justify-between gap-1 rounded px-1.5 py-0.5 text-[11px] font-bold leading-tight', \App\Support\RoomColors::for($club['color'])])
                                              title="{{ $club['name'] }} · {{ $club['modality']->label() }}">
                                            <span>{{ $club['code'] }}@if ($club['modality'] === \App\Enums\ClubModality::Online) · {{ __('agenda.grid.online_short') }}@endif</span>
                                            <span class="tabular-nums">{{ $club['free'] > 0 ? $club['free'].'/'.$club['capacity'] : __('agenda.grid.full') }}</span>
                                        </span>
                                    @endforeach
                                    @if ($cell['booked'] > 0 && $canSelectDay)
                                        <span class="px-1 text-[10px] font-semibold text-ink-500">
                                            {{ trans_choice('agenda.grid.booked', $cell['booked']) }}@if ($cell['waiting'] > 0) · {{ __('agenda.grid.waiting', ['count' => $cell['waiting']]) }}@endif
                                        </span>
                                    @endif
                                </button>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
