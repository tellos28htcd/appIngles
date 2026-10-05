{{-- Menú dinámico (menu_items × rol). Módulos con submódulos se pliegan;
     los que aún no existen se muestran deshabilitados con "Próximamente". --}}
@props(['items'])
<nav aria-label="{{ __('layout.main_navigation') }}" {{ $attributes->merge(['class' => 'flex flex-col gap-1']) }}>
    @foreach ($items as $item)
        @if ($item->children->isNotEmpty())
            <div x-data="{ open: @js($item->isActiveRoute()) }">
                <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" aria-controls="submenu-{{ $item->slug }}"
                        @class([
                            'flex h-11 w-full items-center gap-3 rounded-md px-3 text-left text-[15px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
                            'font-extrabold text-primary-700' => $item->isActiveRoute(),
                            'font-semibold text-ink-700 hover:bg-surface-2' => ! $item->isActiveRoute(),
                        ])>
                    <x-ui.icon :name="$item->icon" />
                    <span class="flex-1 truncate">{{ $item->label }}</span>
                    <x-ui.icon name="chevron-down" class="size-4 text-ink-500 transition-transform" x-bind:class="open && 'rotate-180'" />
                </button>
                <ul id="submenu-{{ $item->slug }}" x-show="open" x-collapse class="ml-[22px] mt-1 flex flex-col gap-0.5 border-l border-line pl-3">
                    @foreach ($item->children as $child)
                        <li><x-layout.menu-link :item="$child" nested /></li>
                    @endforeach
                </ul>
            </div>
        @else
            <x-layout.menu-link :item="$item" />
        @endif
    @endforeach
</nav>
