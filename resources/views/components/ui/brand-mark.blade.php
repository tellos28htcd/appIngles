{{-- Logo de la escuela o, si no tiene, monograma sobre primary-600.
     Alto: 32 px en barras (sm), 40 px en barra lateral (md), 56–64 px en login (lg).
     inverse: fondo blanco con monograma en color de marca (para fondos primary).
     scoped: usa los colores de ESTA marca aunque la página tenga otra (p. ej. listado de escuelas). --}}
@props(['brand', 'size' => 'md', 'inverse' => false, 'scoped' => false])
@php
$box = [
    'sm' => 'size-8 rounded-[10px] text-[13px]',
    'md' => 'size-10 rounded-[12px] text-lg',
    'lg' => 'size-14 rounded-[16px] text-2xl md:size-16',
][$size];
$dot = ['sm' => 'right-1 top-1 size-1.5', 'md' => 'right-[3px] top-[3px] size-2', 'lg' => 'right-1.5 top-1.5 size-2.5'][$size];
$colors = $inverse ? 'bg-white text-primary-600' : 'bg-primary-600 text-on-primary';
$tokens = $scoped
    ? '--brand-primary: '.$brand->brand_primary.'; --brand-accent: '.$brand->brand_accent.'; --brand-on-primary: '.\App\Support\BrandColor::onPrimary($brand->brand_primary).';'
    : null;
@endphp
@if ($brand->logo_url)
    <img src="{{ $brand->logo_url }}" alt="{{ $brand->name }}"
         {{ $attributes->merge(['class' => ($size === 'lg' ? 'h-14 md:h-16' : ($size === 'md' ? 'h-10' : 'h-8')).' w-auto object-contain']) }}>
@else
    <span {{ $attributes->merge(['class' => "relative inline-flex shrink-0 items-center justify-center font-display font-extrabold $box $colors"]) }}
          @if ($tokens) style="{{ $tokens }}" @endif
          role="img" aria-label="{{ $brand->name }}">
        {{ $brand->monogram }}
        <span class="absolute rounded-full bg-accent-500 {{ $dot }}" aria-hidden="true"></span>
    </span>
@endif
