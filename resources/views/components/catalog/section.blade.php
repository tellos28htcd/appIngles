{{-- Sección de catálogo: título, botón "Agregar" y contenido (tabla o lista). --}}
@props(['title', 'description' => null, 'add' => null, 'addLabel' => null])
<section {{ $attributes->merge(['class' => 'flex flex-col rounded-xl border border-line bg-white shadow-xs']) }}>
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line px-4 py-3 md:px-5">
        <div class="flex min-w-0 flex-col">
            <h2 class="font-display text-h4 font-semibold">{{ $title }}</h2>
            @if ($description)
                <p class="text-sm text-ink-500">{{ $description }}</p>
            @endif
        </div>
        @if ($add)
            <x-ui.button variant="secondary" size="sm" wire:click="{{ $add }}">
                <x-ui.icon name="plus" class="size-4" />{{ $addLabel ?? __('catalogs.actions.add') }}
            </x-ui.button>
        @endif
    </header>
    {{ $slot }}
</section>
