{{-- Fila de una opción del menú (módulo o submódulo). Variables: $item, $id, $nested, $loop --}}
@php
$arrow = 'flex size-9 flex-none items-center justify-center rounded-[10px] text-ink-700 hover:bg-surface-2 disabled:opacity-30 disabled:hover:bg-transparent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200';
@endphp
<div @class(['flex flex-col gap-2 sm:flex-row sm:items-center', 'opacity-60' => ! $item->is_enabled])>
    <div class="flex min-w-0 flex-1 items-center gap-2">
    @unless ($nested)
        <x-ui.icon :name="$item->icon" class="text-ink-500" />
    @endunless

    <label for="label-{{ $id }}" class="sr-only">{{ __('menu.fields.label') }}</label>
    <input id="label-{{ $id }}" type="text" wire:model="labels.{{ $id }}" maxlength="100"
           @class([
               'min-w-0 flex-1 rounded-md border-[1.5px] bg-white px-3.5 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100',
               'h-11 border-line-strong font-bold' => ! $nested,
               'h-10 border-line text-[15px]' => $nested,
           ])>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-1 sm:flex-none">
        @if (! $item->is_enabled)
            <span class="rounded-full bg-danger-50 px-2 py-0.5 text-[11px] font-bold text-danger-700">{{ __('menu.status.disabled') }}</span>
        @elseif ($item->isComingSoon() && $item->children_count === 0)
            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-500">{{ __('layout.coming_soon') }}</span>
        @endif
        @unless ($item->is_system)
            <span class="rounded-full bg-accent-100 px-2 py-0.5 text-[11px] font-bold text-ink-900">{{ __('menu.custom') }}</span>
        @endunless

        @can('toggle', $item)
            <button type="button" role="switch" aria-checked="{{ $item->is_enabled ? 'true' : 'false' }}"
                    wire:click="toggleEnabled({{ $id }})" wire:loading.attr="disabled" wire:target="toggleEnabled({{ $id }})"
                    aria-label="{{ __($item->is_enabled ? 'menu.actions.disable' : 'menu.actions.enable', ['name' => $labels[$id]]) }}"
                    class="relative ml-1 inline-flex h-6 w-11 flex-none items-center rounded-full transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 {{ $item->is_enabled ? 'bg-primary-600' : 'bg-line-strong' }}">
                <span class="absolute left-0.5 size-5 rounded-full bg-white shadow-xs transition {{ $item->is_enabled ? 'translate-x-5' : '' }}"></span>
            </button>
        @else
            <span class="ml-1 inline-flex h-6 items-center gap-1 text-[11px] font-semibold text-ink-500" title="{{ __('menu.protected') }}">
                <x-ui.icon name="check" class="size-3.5" />{{ __('menu.always_on') }}
            </span>
        @endcan

        <button type="button" wire:click="move('{{ $id }}', -1)" @disabled($loop->first) class="{{ $arrow }}" aria-label="{{ __('menu.actions.up', ['name' => $labels[$id]]) }}">
            <x-ui.icon name="chevron-down" class="size-4 rotate-180" />
        </button>
        <button type="button" wire:click="move('{{ $id }}', 1)" @disabled($loop->last) class="{{ $arrow }}" aria-label="{{ __('menu.actions.down', ['name' => $labels[$id]]) }}">
            <x-ui.icon name="chevron-down" class="size-4" />
        </button>

        @can('delete', $item)
            <button type="button" wire:click="confirmDeletion({{ $id }})" class="{{ $arrow }} text-danger-700 hover:bg-danger-50"
                    aria-label="{{ __('menu.actions.delete', ['name' => $labels[$id]]) }}">
                <x-ui.icon name="trash" class="size-4" />
            </button>
        @endcan
    </div>
</div>
@error("labels.$id")
    <p class="pt-1 text-[13px] font-medium text-danger-700">{{ $message }}</p>
@enderror
