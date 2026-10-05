@if (! $user->isActive())
    <x-ui.badge variant="neutral">{{ $user->status->label() }}</x-ui.badge>
@elseif (! $user->hasPassword())
    <x-ui.badge variant="warning">{{ __('users.pending_password') }}</x-ui.badge>
@else
    <x-ui.badge variant="success">{{ $user->status->label() }}</x-ui.badge>
@endif
