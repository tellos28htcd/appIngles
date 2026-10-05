@php($action = 'inline-flex h-10 items-center justify-center whitespace-nowrap rounded-[10px] px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
@can('update', $user)
    <a href="{{ route('users.edit', $user) }}" wire:navigate class="{{ $action }} text-primary-700 hover:bg-primary-50">{{ __('users.actions.edit') }}</a>
@endcan
@can('resendInvitation', $user)
    <button type="button" wire:click="resendInvitation({{ $user->id }})" wire:loading.attr="disabled" wire:target="resendInvitation({{ $user->id }})"
            class="{{ $action }} text-ink-700 hover:bg-surface-2">{{ __('users.actions.resend') }}</button>
@endcan
@can('toggleStatus', $user)
    @if ($user->isActive())
        <button type="button" wire:click="confirmDeactivation({{ $user->id }})" class="{{ $action }} text-warning-700 hover:bg-warning-50">{{ __('users.actions.deactivate') }}</button>
    @else
        <button type="button" wire:click="activate({{ $user->id }})" class="{{ $action }} text-success-700 hover:bg-success-50">{{ __('users.actions.activate') }}</button>
    @endif
@endcan
@can('delete', $user)
    <button type="button" wire:click="confirmDeletion({{ $user->id }})" class="{{ $action }} text-danger-700 hover:bg-danger-50">{{ __('users.actions.delete') }}</button>
@endcan
