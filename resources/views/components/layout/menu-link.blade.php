@props(['item', 'nested' => false, 'variant' => 'light'])
@php
$brand = $variant === 'brand';
$base = $nested
    ? 'flex min-h-10 items-center gap-2 rounded-[10px] px-3 py-2 text-sm'
    : 'flex h-11 items-center gap-3 rounded-md px-3 text-[15px]';
$styles = $brand
    ? [
        'focus' => 'focus-visible:ring-on-primary/40',
        'active' => 'bg-on-primary font-extrabold text-primary-700 shadow-xs',
        'idle' => 'font-semibold text-on-primary/85 hover:bg-on-primary/10 hover:text-on-primary',
        'soon' => 'text-on-primary/55',
        'badge' => 'bg-on-primary/15 text-on-primary/80',
    ]
    : [
        'focus' => 'focus-visible:ring-primary-200',
        'active' => 'bg-primary-50 font-extrabold text-primary-700',
        'idle' => 'font-semibold text-ink-700 hover:bg-surface-2',
        'soon' => 'text-ink-400',
        'badge' => 'bg-surface-2 text-ink-500',
    ];
@endphp
@if ($item->isComingSoon() || $item->route_name === null)
    <span class="{{ $base }} {{ $styles['soon'] }} cursor-default font-semibold" aria-disabled="true">
        @unless ($nested)<x-ui.icon :name="$item->icon" />@endunless
        <span @class(['flex-1', 'truncate' => ! $nested, 'leading-tight' => $nested])>{{ $item->label }}</span>
        <span class="flex-none rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $styles['badge'] }}">{{ __('layout.coming_soon') }}</span>
    </span>
@else
    <a href="{{ route($item->route_name) }}" wire:navigate
       @if ($item->isActiveRoute()) aria-current="page" @endif
       @class([
           $base,
           'transition focus-visible:outline-none focus-visible:ring-4',
           $styles['focus'],
           $styles['active'] => $item->isActiveRoute(),
           $styles['idle'] => ! $item->isActiveRoute(),
       ])>
        @unless ($nested)<x-ui.icon :name="$item->icon" />@endunless
        <span @class(['flex-1', 'truncate' => ! $nested, 'leading-tight' => $nested])>{{ $item->label }}</span>
    </a>
@endif
