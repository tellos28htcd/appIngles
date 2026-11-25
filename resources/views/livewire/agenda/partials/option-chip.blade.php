{{-- Un botón de opción de "Nueva reservación" (modo múltiple). Variables: $item, $value, $selected, $click, $chip y,
     opcional, $label (texto en lugar de $item['label']). Con 'blocked' sale deshabilitado con el motivo. --}}
@php($label ??= $item['label'])
@if ($item['blocked'] ?? null)
    <button type="button" wire:key="option-{{ $value }}" disabled title="{{ $item['blocked'] }}" @class([$chip, 'cursor-not-allowed border-dashed border-line bg-surface-2 text-ink-400'])>
        <span class="font-semibold">{{ $label }}</span>
        <span class="text-xs font-semibold text-warning-700">{{ $item['blocked'] }}</span>
    </button>
@else
    <button type="button" wire:key="option-{{ $value }}" wire:click="{{ $click }}" aria-pressed="{{ $selected ? 'true' : 'false' }}"
            @class([$chip, 'border-primary-500 bg-primary-50 ring-2 ring-primary-500' => $selected, 'border-line hover:border-primary-500' => ! $selected])>
        <span class="font-semibold">{{ $label }}</span>
        <span @class(['text-xs', 'text-warning-700' => $item['waitlist'], 'text-ink-500' => ! $item['waitlist']])>
            {{ $item['waitlist'] ? __('agenda.goes_to_waitlist') : ($item['kind'] === 'presentation' ? trans_choice('agenda.free_rooms', $item['free']) : trans_choice('agenda.seats', $item['free'])) }}
        </span>
    </button>
@endif
