{{-- Opciones del día por hora. Cada opción es un botón: se recorre con Tab y se elige con Enter o clic.
     - Con $multiple (Nueva reservación de recepción y del alumno): primero Presencial / En línea ($modality) y luego el
       tipo: Práctica, Club, Mentoría especial, Entrega de material, Presentación 1 a 1 o Presentación en club; se eligen
       hasta 2 horas distintas (toggleChoice, en $choices).
     - Sin $multiple ("Cambiar reservación"): una sola opción en $model ("option" por omisión; otro clic la quita), con
       la modalidad en cada botón. Variables: $options, $model y $current (valor elegido).
     - Una opción con 'blocked' (club que no le toca, lleno, presentación sin aula libre) sale deshabilitada con el motivo. --}}
@php($model ??= 'option')
@php($multiple ??= false)
@php($current = $current ?? '')
@php($chosen = $multiple ? ($choices ?? []) : [$current])
@php($chip = 'flex min-w-36 flex-col rounded-lg border px-3 py-2 text-left text-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
@php($valueOf = fn ($hour, array $item) => $hour['slot']->id.'|'.$item['kind'].'|'.$item['modality'].'|'.($item['club_session_id'] ?? ''))
@php($clickOf = fn (string $value, bool $selected) => $multiple ? 'toggleChoice(\''.$value.'\')' : '$set(\''.$model.'\', \''.($selected ? '' : $value).'\')')
@if ($multiple)
    @php($mode = $modality ?? 'in_person')
    {{-- 1) Modalidad --}}
    <div class="grid grid-cols-2 gap-2" role="group" aria-label="{{ __('agenda.modality') }}">
        @foreach (['in_person' => __('agenda.in_person'), 'online' => __('agenda.online')] as $value => $label)
            <button type="button" wire:click="$set('modality', '{{ $value }}')" aria-pressed="{{ $mode === $value ? 'true' : 'false' }}"
                    @class(['h-11 rounded-lg border-[1.5px] text-[15px] font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
                        'border-primary-600 bg-primary-600 text-on-primary' => $mode === $value, 'border-line-strong bg-white text-ink-700 hover:bg-surface' => $mode !== $value])>{{ $label }}</button>
        @endforeach
    </div>
    @php($options = collect($options)->map(fn ($hour) => [...$hour, 'options' => array_values(array_filter($hour['options'], fn ($item) => $item['modality'] === $mode))])->filter(fn ($hour) => $hour['options'] !== [])->values()->all())
@endif
@if ($options === [])
    <p class="rounded-lg border border-line bg-surface p-4 text-sm text-ink-500">{{ __('agenda.no_options') }}</p>
@else
    <div class="flex flex-col gap-3" role="group" aria-label="{{ __('agenda.available') }}">
        <p class="text-sm font-semibold text-ink-700">{{ __('agenda.available') }}@if ($multiple) <span class="font-normal text-ink-500">· {{ __('agenda.choose_up_to', ['max' => $maxChoices ?? 2]) }}</span>@endif</p>
        @foreach ($options as $hour)
            <div wire:key="hour-{{ $hour['slot']->id }}" class="flex flex-col gap-2 sm:flex-row sm:items-start sm:gap-3">
                <span class="w-14 flex-none pt-2 font-display font-bold tabular-nums text-primary-700">{{ substr((string) $hour['slot']->starts_at, 0, 5) }}</span>
                @if ($multiple)
                    {{-- 2) Tipo de reservación de esa hora en la modalidad elegida --}}
                    <div class="flex flex-1 flex-wrap gap-1.5">
                        @foreach ($hour['options'] as $item)
                            @php($value = $valueOf($hour, $item))
                            @php($selected = in_array($value, $chosen, true))
                            @php($blocked = $item['blocked'] ?? null)
                            @if ($blocked)
                                <button type="button" wire:key="option-{{ $value }}" disabled title="{{ $blocked }}" @class([$chip, 'cursor-not-allowed border-dashed border-line bg-surface-2 text-ink-400'])>
                                    <span class="font-semibold">{{ $item['label'] }}</span>
                                    <span class="text-xs font-semibold text-warning-700">{{ $blocked }}</span>
                                </button>
                            @else
                            <button type="button" wire:key="option-{{ $value }}" wire:click="{{ $clickOf($value, $selected) }}" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                    @class([$chip, 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $selected, 'border-line hover:border-primary-500' => ! $selected])>
                                <span class="font-semibold">{{ $item['label'] }}</span>
                                <span @class(['text-xs', 'text-warning-700' => $item['waitlist'], 'text-ink-500' => ! $item['waitlist']])>
                                    {{ $item['waitlist'] ? __('agenda.goes_to_waitlist') : ($item['kind'] === 'presentation' ? trans_choice('agenda.free_rooms', $item['free']) : trans_choice('agenda.seats', $item['free'])) }}
                                </span>
                            </button>
                            @endif
                        @endforeach
                    </div>
                @else
                    @php($groups = collect($hour['options'])->groupBy('kind'))
                    <div class="flex flex-1 flex-col gap-2">
                        @foreach ($groups as $kind => $items)
                            <div class="flex flex-col gap-1.5">
                                <span class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ \App\Enums\BookingKind::from($kind)->label() }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($items as $item)
                                        @php($value = $valueOf($hour, $item))
                                        @php($selected = in_array($value, $chosen, true))
                                        @if ($item['blocked'] ?? null)
                                            <button type="button" wire:key="option-{{ $value }}" disabled title="{{ $item['blocked'] }}" @class([$chip, 'cursor-not-allowed border-dashed border-line bg-surface-2 text-ink-400'])>
                                                <span class="font-semibold">{{ $item['club_session_id'] ? $item['label'] : ($item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person')) }}</span>
                                                <span class="text-xs font-semibold text-warning-700">{{ $item['blocked'] }}</span>
                                            </button>
                                            @continue
                                        @endif
                                        <button type="button" wire:key="option-{{ $value }}" wire:click="{{ $clickOf($value, $selected) }}" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                                                @class([$chip, 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $selected, 'border-line hover:border-primary-500' => ! $selected])>
                                            <span class="font-semibold">{{ $item['club_session_id'] ? $item['label'] : ($item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person')) }}</span>
                                            <span @class(['text-xs', 'text-warning-700' => $item['waitlist'], 'text-ink-500' => ! $item['waitlist']])>
                                                @if ($item['club_session_id']){{ $item['modality'] === 'online' ? __('agenda.online') : __('agenda.in_person') }} · @endif{{ $item['waitlist'] ? __('agenda.goes_to_waitlist') : trans_choice('agenda.seats', $item['free']) }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
@endif
