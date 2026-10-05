@php($action = 'inline-flex h-10 items-center justify-center rounded-[10px] px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
<a href="{{ route('schools.edit', $school) }}" wire:navigate class="{{ $action }} text-primary-700 hover:bg-primary-50">{{ __('schools.actions.edit') }}</a>
@if ($school->isActive())
    <button type="button" wire:click="confirmSuspend({{ $school->id }})" class="{{ $action }} text-danger-700 hover:bg-danger-50">{{ __('schools.actions.suspend') }}</button>
@else
    <button type="button" wire:click="activate({{ $school->id }})" class="{{ $action }} text-success-700 hover:bg-success-50">{{ __('schools.actions.activate') }}</button>
@endif
