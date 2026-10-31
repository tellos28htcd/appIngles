{{-- Opciones del día por hora (asesoría presencial / en línea y clubs con lugar). Se elige con wire:model="option". --}}
@if ($options === [])
    <p class="rounded-lg border border-line bg-surface p-4 text-sm text-ink-500">{{ __('agenda.no_options') }}</p>
@else
    <fieldset class="flex flex-col gap-3">
        <legend class="mb-2 text-sm font-semibold text-ink-700">{{ __('agenda.available') }}</legend>
        @foreach ($options as $hour)
            <div wire:key="hour-{{ $hour['slot']->id }}" class="flex flex-col gap-1.5 sm:flex-row sm:items-start sm:gap-3">
                <span class="w-14 flex-none pt-2 font-display font-bold tabular-nums text-primary-700">{{ substr((string) $hour['slot']->starts_at, 0, 5) }}</span>
                <div class="flex flex-1 flex-wrap gap-1.5">
                    @foreach ($hour['options'] as $item)
                        @php($value = $hour['slot']->id.'|'.$item['kind'].'|'.$item['modality'].'|'.$item['club_session_id'])
                        <label wire:key="option-{{ $value }}"
                               class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm transition has-checked:border-primary-500 has-checked:bg-primary-50 hover:border-primary-500">
                            <input type="radio" name="option" value="{{ $value }}" wire:model.live="option" class="size-4 accent-primary-600">
                            <span class="flex flex-col">
                                <span class="font-semibold">{{ $item['label'] }}</span>
                                <span @class(['text-xs', 'text-warning-700' => $item['waitlist'], 'text-ink-500' => ! $item['waitlist']])>
                                    {{ $item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person') }}
                                    · {{ $item['waitlist'] ? __('agenda.goes_to_waitlist') : trans_choice('agenda.seats', $item['free']) }}
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </fieldset>
@endif
