{{-- Paginación de Livewire con tokens del diseño. Uso: {{ $items->links('partials.pagination') }} --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('layout.pagination.label') }}" class="flex items-center justify-between gap-3">
        <p class="hidden text-sm text-ink-500 sm:block">
            {{ __('layout.pagination.showing', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>
        <div class="flex flex-1 items-center justify-between gap-1 sm:flex-none sm:justify-end">
            @php($button = 'flex h-10 min-w-10 items-center justify-center rounded-[10px] px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
            @if ($paginator->onFirstPage())
                <span class="{{ $button }} text-ink-400" aria-disabled="true">{{ __('layout.pagination.previous') }}</span>
            @else
                <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" class="{{ $button }} text-ink-700 hover:bg-surface-2">{{ __('layout.pagination.previous') }}</button>
            @endif

            <span class="text-sm font-semibold text-ink-700 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="{{ $button }} text-ink-400">{{ $element }}</span>
                    @endif
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="{{ $button }} bg-primary-600 text-on-primary tabular-nums" aria-current="page">{{ $page }}</span>
                            @else
                                <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" class="{{ $button }} text-ink-700 tabular-nums hover:bg-surface-2">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            @if ($paginator->hasMorePages())
                <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" class="{{ $button }} text-ink-700 hover:bg-surface-2">{{ __('layout.pagination.next') }}</button>
            @else
                <span class="{{ $button }} text-ink-400" aria-disabled="true">{{ __('layout.pagination.next') }}</span>
            @endif
        </div>
    </nav>
@endif
