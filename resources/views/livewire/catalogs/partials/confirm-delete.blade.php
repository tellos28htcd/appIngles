<x-ui.modal wire:model="confirmingDeletion" :title="__('catalogs.confirm_delete.title')">
    <p>{{ __('catalogs.confirm_delete.body') }}</p>
    <x-slot:footer>
        <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
        <x-ui.button variant="danger" wire:click="delete">{{ __('catalogs.confirm_delete.confirm') }}</x-ui.button>
    </x-slot:footer>
</x-ui.modal>
