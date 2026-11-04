{{-- Opciones del día por hora: Práctica (el alumno elige presencial o en línea) y clubs con lugar.
     Cada opción es un botón: se recorre con Tab y se elige con Enter o clic (queda en "option"). --}}
@if ($options === [])
    <p class="rounded-lg border border-line bg-surface p-4 text-sm text-ink-500">{{ __('agenda.no_options') }}</p>
@else
    @php($chip = 'flex min-w-36 flex-col rounded-lg border px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
    <div class="flex flex-col gap-3" role="group" aria-label="{{ __('agenda.available') }}">
        <p class="text-sm font-semibold text-ink-700">{{ __('agenda.available') }}</p>
        @foreach ($options as $hour)
            @php($sessions = collect($hour['options'])->where('kind', 'session'))
            @php($clubs = collect($hour['options'])->where('kind', 'club'))
            <div wire:key="hour-{{ $hour['slot']->id }}" class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                <span class="w-14 flex-none pt-2 font-display font-bold tabular-nums text-primary-700">{{ substr((string) $hour['slot']->starts_at, 0, 5) }}</span>
                <div class="flex flex-1 flex-col gap-2">
                    @if ($sessions->isNotEmpty())
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('agenda.option_session') }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($sessions as $item)
                                    @php($value = $hour['slot']->id.'|'.$item['kind'].'|'.$item['modality'].'|')
                                    @php($selected = $option === $value)
                                    <button type="button" wire:key="option-{{ $value }}" wire:click="$set('option', '{{ $value }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                            @class([$chip, 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $selected, 'border-line hover:border-primary-500' => ! $selected])>
                                        <span class="font-semibold">{{ $item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person') }}</span>
                                        <span @class(['text-xs', 'text-warning-700' => $item['waitlist'], 'text-ink-500' => ! $item['waitlist']])>
                                            {{ $item['waitlist'] ? __('agenda.goes_to_waitlist') : trans_choice('agenda.seats', $item['free']) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if ($clubs->isNotEmpty())
                        <div class="flex flex-col gap-1.5">
                            <span class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('agenda.kinds.club') }}</span>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($clubs as $item)
                                    @php($value = $hour['slot']->id.'|'.$item['kind'].'|'.$item['modality'].'|'.$item['club_session_id'])
                                    @php($selected = $option === $value)
                                    <button type="button" wire:key="option-{{ $value }}" wire:click="$set('option', '{{ $value }}')" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                            @class([$chip, 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $selected, 'border-line hover:border-primary-500' => ! $selected])>
                                        <span class="font-semibold">{{ $item['label'] }}</span>
                                        <span class="text-xs text-ink-500">
                                            {{ $item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person') }} · {{ trans_choice('agenda.seats', $item['free']) }}
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
