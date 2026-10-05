{{-- Badge con punto. Variantes semánticas fijas (nunca colores de marca para estados).
     Uso: <x-ui.badge variant="success">Activo</x-ui.badge> --}}
@props(['variant' => 'neutral'])
@php
$map = [
    'success' => ['bg-success-50 text-success-700 ring-success-200', 'bg-success-500'],
    'warning' => ['bg-warning-50 text-warning-700 ring-warning-200', 'bg-warning-500'],
    'danger' => ['bg-danger-50 text-danger-700 ring-danger-200', 'bg-danger-600'],
    'info' => ['bg-info-50 text-info-700 ring-info-200', 'bg-info-600'],
    'neutral' => ['bg-surface-2 text-ink-700 ring-line', 'bg-ink-400'],
][$variant];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex h-7 items-center gap-2 whitespace-nowrap rounded-full px-3 text-[13px] font-bold ring-1 ring-inset {$map[0]}"]) }}>
    <span class="size-2 rounded-full {{ $map[1] }}" aria-hidden="true"></span>{{ $slot }}
</span>
