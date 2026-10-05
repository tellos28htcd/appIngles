{{-- Navegación inferior móvil: primeros 4 módulos + "Más" (abre el menú completo). --}}
@props(['items'])
@php
$primary = $items->take(4);
@endphp
<nav aria-label="{{ __('layout.main_navigation') }}"
     class="fixed inset-x-0 bottom-0 z-30 grid h-18 grid-cols-5 border-t border-line bg-white px-1 pb-[env(safe-area-inset-bottom)] lg:hidden">
    @foreach ($primary as $item)
        @php
            $active = $item->isActiveRoute();
            $link = $item->children->isEmpty() && ! $item->isComingSoon() && $item->route_name !== null;
            $classes = 'flex flex-col items-center justify-center gap-1 text-[11px] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 rounded-md '
                .($active ? 'font-extrabold text-primary-800' : 'font-semibold text-ink-500');
        @endphp
        @if ($link)
            <a href="{{ route($item->route_name) }}" wire:navigate class="{{ $classes }}" @if ($active) aria-current="page" @endif>
        @elseif ($item->isComingSoon())
            <span class="{{ $classes }} opacity-50" aria-disabled="true" title="{{ __('layout.coming_soon') }}">
        @else
            <button type="button" x-on:click="menuOpen = true" class="{{ $classes }}">
        @endif
                <span @class(['flex h-7.5 w-13 items-center justify-center rounded-full', 'bg-primary-100' => $active])>
                    <x-ui.icon :name="$item->icon" />
                </span>
                <span class="max-w-full truncate px-1">{{ $item->label }}</span>
        @if ($link) </a> @elseif ($item->isComingSoon()) </span> @else </button> @endif
    @endforeach
    <button type="button" x-on:click="menuOpen = true" aria-controls="menu-movil" :aria-expanded="menuOpen.toString()"
            class="flex flex-col items-center justify-center gap-1 rounded-md text-[11px] font-semibold text-ink-500 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
        <span class="flex h-7.5 w-13 items-center justify-center rounded-full"><x-ui.icon name="menu" /></span>
        {{ __('layout.more') }}
    </button>
</nav>
