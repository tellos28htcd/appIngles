{{-- Badge de estado del alumno: activo | pausado | baja | egresado --}}
@props(['status'])
@php
$map = [
    'activo'   => ['bg-success-50 text-success-700 ring-success-200', 'bg-success-500', 'Activo'],
    'pausado'  => ['bg-warning-50 text-warning-700 ring-warning-200', 'bg-warning-500', 'Pausado'],
    'baja'     => ['bg-danger-50 text-danger-700 ring-danger-200',    'bg-danger-600',  'Baja'],
    'egresado' => ['bg-info-50 text-info-700 ring-info-200',          'bg-info-600',    'Egresado'],
][$status] ?? ['bg-surface-2 text-ink-700 ring-line', 'bg-ink-400', ucfirst($status)];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex h-7 items-center gap-2 rounded-full px-3 text-[13px] font-bold ring-1 ring-inset {$map[0]}"]) }}>
    <span class="size-2 rounded-full {{ $map[1] }}" aria-hidden="true"></span>{{ $map[2] }}
</span>
