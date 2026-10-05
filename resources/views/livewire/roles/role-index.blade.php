<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('roles.title')" :subtitle="__('roles.subtitle')">
        <x-slot:actions>
            <a href="{{ route('roles.create') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                <span aria-hidden="true">+</span> {{ __('roles.new') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    @include('livewire.roles.partials.tabs')

    <ul class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->roles as $role)
            <li wire:key="role-{{ $role->id }}" @class([
                'flex flex-col gap-4 rounded-xl border bg-white p-5 shadow-xs',
                'border-line' => $role->is_active,
                'border-dashed border-line-strong' => ! $role->is_active,
            ])>
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-col gap-1">
                        <h2 class="font-display text-h4 font-bold">{{ $role->name }}</h2>
                        <div class="flex flex-wrap gap-1.5">
                            <span class="rounded-full bg-surface-2 px-2 py-0.5 text-[11px] font-bold text-ink-700">{{ __('roles.scope.'.$role->scope->value) }}</span>
                            @unless ($role->is_system)
                                <span class="rounded-full bg-accent-100 px-2 py-0.5 text-[11px] font-bold text-ink-900">{{ __('roles.custom') }}</span>
                            @endunless
                        </div>
                    </div>
                    <x-ui.badge :variant="$role->is_active ? 'success' : 'neutral'">{{ __($role->is_active ? 'roles.status.active' : 'roles.status.inactive') }}</x-ui.badge>
                </div>

                <p class="text-sm text-ink-700">{{ $role->description ?: '—' }}</p>

                <dl class="grid grid-cols-2 gap-3 rounded-lg bg-surface p-3">
                    <div class="flex flex-col">
                        <dt class="text-xs font-semibold text-ink-500">{{ __('roles.columns.users') }}</dt>
                        <dd class="font-display text-h4 font-bold tabular-nums">{{ $role->users_count }}</dd>
                    </div>
                    <div class="flex flex-col">
                        <dt class="text-xs font-semibold text-ink-500">{{ __('roles.columns.modules') }}</dt>
                        <dd class="font-display text-h4 font-bold tabular-nums">
                            @if ($role->isPlatformAdmin())
                                {{ __('roles.full_access') }}
                            @else
                                {{ $role->modules_count }}<span class="text-sm font-semibold text-ink-500"> / {{ $totalModules }}</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-auto flex flex-wrap gap-1">
                    @php($action = 'inline-flex h-10 items-center justify-center rounded-[10px] px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
                    @can('update', $role)
                        <a href="{{ route('roles.edit', $role) }}" wire:navigate class="{{ $action }} text-primary-700 hover:bg-primary-50">{{ __('roles.actions.edit') }}</a>
                        <button type="button" wire:click="toggleActive({{ $role->id }})"
                                class="{{ $action }} {{ $role->is_active ? 'text-warning-700 hover:bg-warning-50' : 'text-success-700 hover:bg-success-50' }}">
                            {{ __($role->is_active ? 'roles.actions.deactivate' : 'roles.actions.activate') }}
                        </button>
                    @else
                        <span class="inline-flex h-10 items-center gap-1.5 px-3 text-sm text-ink-500">
                            <x-ui.icon name="check" class="size-4" />{{ __('roles.locked') }}
                        </span>
                    @endcan
                    @can('delete', $role)
                        <button type="button" wire:click="confirmDeletion({{ $role->id }})" class="{{ $action }} text-danger-700 hover:bg-danger-50">{{ __('roles.actions.delete') }}</button>
                    @endcan
                </div>
            </li>
        @endforeach
    </ul>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('roles.confirm_delete.title', ['name' => $this->roleToDelete?->name])">
        <p>{{ __('roles.confirm_delete.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('roles.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('roles.confirm_delete.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
