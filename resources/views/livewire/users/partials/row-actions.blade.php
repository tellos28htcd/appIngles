{{-- Acciones compactas de un usuario: editar, reenviar invitación, activar/desactivar, eliminar. --}}
@php($button = 'flex size-9 flex-none items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 disabled:opacity-40')
@can('update', $user)
    <a href="{{ route('users.edit', $user) }}" wire:navigate class="{{ $button }} text-primary-700 hover:bg-primary-50"
       aria-label="{{ __('users.actions.edit_named', ['name' => $user->name]) }}" title="{{ __('users.actions.edit') }}">
        <x-ui.icon name="pencil" class="size-4" />
    </a>
@endcan
@can('resendInvitation', $user)
    <button type="button" wire:click="resendInvitation({{ $user->id }})" wire:loading.attr="disabled" wire:target="resendInvitation({{ $user->id }})"
            class="{{ $button }} text-ink-700 hover:bg-surface-2"
            aria-label="{{ __('users.actions.resend_named', ['name' => $user->name]) }}" title="{{ __('users.actions.resend') }}">
        <x-ui.icon name="mail" class="size-4" />
    </button>
@endcan
@can('toggleStatus', $user)
    <button type="button" role="switch" aria-checked="{{ $user->isActive() ? 'true' : 'false' }}"
            wire:click="{{ $user->isActive() ? 'confirmDeactivation' : 'activate' }}({{ $user->id }})"
            aria-label="{{ __($user->isActive() ? 'users.actions.deactivate_named' : 'users.actions.activate_named', ['name' => $user->name]) }}"
            title="{{ __($user->isActive() ? 'users.actions.deactivate' : 'users.actions.activate') }}"
            class="relative mx-1 inline-flex h-6 w-11 flex-none items-center rounded-full transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 {{ $user->isActive() ? 'bg-primary-600' : 'bg-line-strong' }}">
        <span class="absolute left-0.5 size-5 rounded-full bg-white shadow-xs transition {{ $user->isActive() ? 'translate-x-5' : '' }}"></span>
    </button>
@endcan
@can('delete', $user)
    <button type="button" wire:click="confirmDeletion({{ $user->id }})" class="{{ $button }} text-danger-700 hover:bg-danger-50"
            aria-label="{{ __('users.actions.delete_named', ['name' => $user->name]) }}" title="{{ __('users.actions.delete') }}">
        <x-ui.icon name="trash" class="size-4" />
    </button>
@endcan
