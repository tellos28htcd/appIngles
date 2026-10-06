{{-- Acciones de una fila de catálogo: editar, activar/desactivar, eliminar.
     Uso: <x-catalog.row-actions :active="$item->is_active" :name="$item->name"
            edit="editShift({{ $item->id }})" toggle="toggleShift({{ $item->id }})" delete="confirmShiftDeletion({{ $item->id }})" /> --}}
@props(['active', 'name', 'edit', 'toggle', 'delete'])
@php
$button = 'flex size-9 flex-none items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200';
@endphp
<div class="flex items-center justify-end gap-1">
    <button type="button" wire:click="{{ $edit }}" class="{{ $button }} text-primary-700 hover:bg-primary-50"
            aria-label="{{ __('catalogs.actions.edit', ['name' => $name]) }}" title="{{ __('catalogs.actions.edit_short') }}">
        <x-ui.icon name="pencil" class="size-4" />
    </button>
    <button type="button" role="switch" aria-checked="{{ $active ? 'true' : 'false' }}" wire:click="{{ $toggle }}"
            aria-label="{{ __($active ? 'catalogs.actions.deactivate' : 'catalogs.actions.activate', ['name' => $name]) }}"
            class="relative mx-1 inline-flex h-6 w-11 flex-none items-center rounded-full transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 {{ $active ? 'bg-primary-600' : 'bg-line-strong' }}">
        <span class="absolute left-0.5 size-5 rounded-full bg-white shadow-xs transition {{ $active ? 'translate-x-5' : '' }}"></span>
    </button>
    <button type="button" wire:click="{{ $delete }}" class="{{ $button }} text-danger-700 hover:bg-danger-50"
            aria-label="{{ __('catalogs.actions.delete', ['name' => $name]) }}" title="{{ __('catalogs.actions.delete_short') }}">
        <x-ui.icon name="trash" class="size-4" />
    </button>
</div>
