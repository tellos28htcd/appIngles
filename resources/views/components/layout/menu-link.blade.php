@props(['item', 'nested' => false])
@php
$base = $nested
    ? 'flex min-h-10 items-center gap-2 rounded-[10px] px-3 py-2 text-sm'
    : 'flex h-11 items-center gap-3 rounded-md px-3 text-[15px]';
@endphp
@if ($item->isComingSoon() || $item->route_name === null)
    <span class="{{ $base }} cursor-default font-semibold text-ink-400" aria-disabled="true">
        @unless ($nested)<x-ui.icon :name="$item->icon" />@endunless
        <span class="flex-1 truncate">{{ $item->label }}</span>
        <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-500">{{ __('layout.coming_soon') }}</span>
    </span>
@else
    <a href="{{ route($item->route_name) }}" wire:navigate
       @if ($item->isActiveRoute()) aria-current="page" @endif
       @class([
           $base,
           'transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
           'bg-primary-50 font-extrabold text-primary-700' => $item->isActiveRoute(),
           'font-semibold text-ink-700 hover:bg-surface-2' => ! $item->isActiveRoute(),
       ])>
        @unless ($nested)<x-ui.icon :name="$item->icon" />@endunless
        <span class="flex-1 truncate">{{ $item->label }}</span>
    </a>
@endif
