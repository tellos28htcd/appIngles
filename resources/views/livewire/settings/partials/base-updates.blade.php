{{-- Novedades del catálogo base para esta pantalla de Configuración. --}}
@if ($this->baseUpdates->isNotEmpty())
    <div role="status" class="flex max-w-4xl flex-col gap-3 rounded-xl border border-accent-300 bg-accent-50 p-4 sm:flex-row sm:items-center">
        <span class="flex size-10 flex-none items-center justify-center rounded-full bg-accent-500 text-on-accent">
            <x-ui.icon name="sparkles" />
        </span>
        <div class="flex min-w-0 flex-1 flex-col">
            <span class="font-bold text-ink-900">{{ trans_choice('catalogs.updates.title', $this->baseUpdates->count()) }}</span>
            <span class="text-sm text-ink-700">{{ $this->baseUpdates->pluck('name')->take(6)->implode(', ') }}{{ $this->baseUpdates->count() > 6 ? '…' : '' }}</span>
        </div>
        <x-ui.button wire:click="incorporateBaseUpdates">{{ __('catalogs.updates.incorporate_all') }}</x-ui.button>
    </div>
@endif
