{{-- Semáforo: forma + color + texto (nunca solo color).
     verde = círculo, naranja = triángulo, rojo = cuadrado.
     Uso: <x-ui.semaforo level="naranja" label="Vence en 3 días" pill /> --}}
@props(['level' => 'verde', 'label' => null, 'pill' => false])
@php
$shape = [
    'verde'   => 'size-3 rounded-full bg-success-600',
    'naranja' => 'size-3 bg-warning-500 [clip-path:polygon(50%_0,100%_100%,0_100%)]',
    'rojo'    => 'size-2.5 rounded-[2px] bg-danger-700',
][$level];
$text = ['verde' => 'text-success-700', 'naranja' => 'text-warning-700', 'rojo' => 'text-danger-700'][$level];
$bg   = ['verde' => 'bg-success-50',   'naranja' => 'bg-warning-50',   'rojo' => 'bg-danger-50'][$level];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 text-sm font-semibold $text".($pill ? " h-7 rounded-full px-2.5 $bg" : '')]) }}>
    <span class="{{ $shape }}" aria-hidden="true"></span>{{ $label ?? $slot }}
</span>
