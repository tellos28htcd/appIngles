{{-- Menú dinámico (menu_items × rol). Módulos con submódulos se pliegan;
     los que aún no existen se muestran deshabilitados con "Próximamente".
     variant: "light" (fondo blanco, hoja del menú móvil) | "brand" (barra lateral sobre primary-600). --}}
@props(['items', 'variant' => 'light'])
@php
$brand = $variant === 'brand';
$focus = $brand ? 'focus-visible:ring-on-primary/40' : 'focus-visible:ring-primary-200';
$groupActive = $brand ? 'font-extrabold text-on-primary' : 'font-extrabold text-primary-700';
$groupIdle = $brand ? 'font-semibold text-on-primary/85 hover:bg-on-primary/10 hover:text-on-primary' : 'font-semibold text-ink-700 hover:bg-surface-2';
$chevron = $brand ? 'text-on-primary/70' : 'text-ink-500';
$rail = $brand ? 'border-on-primary/25' : 'border-line';
@endphp
<nav aria-label="{{ __('layout.main_navigation') }}" {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    @foreach ($items as $item)
        @if ($item->children->isNotEmpty())
            <div x-data="{ open: @js($item->isActiveRoute()) }">
                <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" aria-controls="submenu-{{ $variant }}-{{ $item->slug }}"
                        @class([
                            'flex h-11 w-full items-center gap-3 rounded-md px-3 text-left text-[15px] transition focus-visible:outline-none focus-visible:ring-4',
                            $focus,
                            $groupActive => $item->isActiveRoute(),
                            $groupIdle => ! $item->isActiveRoute(),
                        ])>
                    <x-ui.icon :name="$item->icon" />
                    <span class="flex-1 truncate">{{ $item->label }}</span>
                    <x-ui.icon name="chevron-down" class="size-4 transition-transform {{ $chevron }}" x-bind:class="open && 'rotate-180'" />
                </button>
                <ul id="submenu-{{ $variant }}-{{ $item->slug }}" x-show="open" x-collapse class="ml-[22px] mt-1 flex flex-col gap-0.5 border-l pl-3 {{ $rail }}">
                    @foreach ($item->children as $child)
                        <li><x-layout.menu-link :item="$child" :variant="$variant" nested /></li>
                    @endforeach
                </ul>
            </div>
        @else
            <x-layout.menu-link :item="$item" :variant="$variant" />
        @endif
    @endforeach
</nav>
