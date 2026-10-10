<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('teachers.title')" :subtitle="__('teachers.subtitle')">
        <x-slot:actions>
            @can('create', \App\Models\Teacher::class)
                <a href="{{ route('teachers.create') }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <x-ui.icon name="plus" class="size-4" /> {{ __('teachers.new') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        <div class="flex flex-col gap-3 border-b border-line p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="teachers-search" class="sr-only">{{ __('teachers.search') }}</label>
                <input id="teachers-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('teachers.search') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <div @class(['grid gap-3', 'sm:grid-cols-2' => $isPlatform, 'sm:max-w-xs' => ! $isPlatform])>
                @if ($isPlatform)
                    <x-ui.select name="school" :label="__('teachers.fields.school_id')" :options="$schoolOptions" :placeholder="__('users.all_schools')" wire:model.live="school" sr-only-label />
                @endif
                <x-ui.select name="status" :label="__('teachers.fields.status')" :options="$statusOptions" :placeholder="__('teachers.all_statuses')" wire:model.live="status" sr-only-label />
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->teachers->isEmpty())
                <x-ui.empty-state icon="users" :title="__('teachers.empty')" :description="__('teachers.empty_hint')">
                    @can('create', \App\Models\Teacher::class)
                        <a href="{{ route('teachers.create') }}" wire:navigate class="inline-flex h-11 items-center rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary hover:bg-primary-700">{{ __('teachers.new') }}</a>
                    @endcan
                </x-ui.empty-state>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->teachers as $teacher)
                        <li wire:key="teacher-{{ $teacher->id }}" class="flex flex-col gap-3 p-4 hover:bg-surface md:flex-row md:items-center md:gap-4 md:px-5">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                @include('livewire.teachers.partials.avatar', ['teacher' => $teacher, 'size' => 'size-11'])
                                <div class="flex min-w-0 flex-col">
                                    <span class="font-bold leading-snug [overflow-wrap:anywhere]">{{ $teacher->fullName() }}</span>
                                    <span class="truncate text-sm text-ink-500">{{ $teacher->user?->email }}</span>
                                    @if ($isPlatform)
                                        <span class="text-xs text-ink-500"><span class="font-mono">{{ $teacher->school->code }}</span> · {{ $teacher->school->name }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 md:w-56 md:flex-none">
                                @if ($teacher->isIncomplete())
                                    <x-ui.badge variant="warning">{{ __('teachers.incomplete') }}</x-ui.badge>
                                @else
                                    <span class="text-sm">
                                        <span class="font-semibold">{{ $teacher->contract_type->label() }}</span>
                                        <span class="block text-xs text-ink-500 tabular-nums">{{ trans_choice('teachers.hours_per_week', $teacher->weekly_hours) }}</span>
                                    </span>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2 md:w-48 md:flex-none">
                                <x-ui.badge :variant="$teacher->status->badge()">{{ $teacher->status->label() }}</x-ui.badge>
                                @if ($teacher->status->canWork() && ! $teacher->user?->hasPassword())
                                    <x-ui.badge variant="info">{{ __('users.pending_password') }}</x-ui.badge>
                                @endif
                            </div>
                            <div class="flex items-center justify-end gap-0.5 md:flex-none">
                                @php($button = 'flex size-9 flex-none items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
                                @can('update', $teacher)
                                    <a href="{{ route('teachers.edit', $teacher) }}" wire:navigate class="{{ $button }} text-primary-700 hover:bg-primary-50"
                                       aria-label="{{ __('teachers.actions.edit', ['name' => $teacher->fullName()]) }}" title="{{ __('teachers.actions.edit_short') }}">
                                        <x-ui.icon name="pencil" class="size-4" />
                                    </a>
                                @endcan
                                @can('resendInvitation', $teacher)
                                    <button type="button" wire:click="resendInvitation({{ $teacher->id }})" class="{{ $button }} text-ink-700 hover:bg-surface-2"
                                            aria-label="{{ __('users.actions.resend_named', ['name' => $teacher->fullName()]) }}" title="{{ __('users.actions.resend') }}">
                                        <x-ui.icon name="mail" class="size-4" />
                                    </button>
                                @endcan
                                @can('delete', $teacher)
                                    <button type="button" wire:click="confirmDeletion({{ $teacher->id }})" class="{{ $button }} text-danger-700 hover:bg-danger-50"
                                            aria-label="{{ __('teachers.actions.delete', ['name' => $teacher->fullName()]) }}" title="{{ __('teachers.actions.delete_short') }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($this->teachers->hasPages())
            <div class="border-t border-line p-4">{{ $this->teachers->links('partials.pagination') }}</div>
        @endif
    </section>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('teachers.confirm_delete.title', ['name' => $this->pendingDeletion?->fullName()])">
        <p>{{ __('teachers.confirm_delete.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('teachers.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('teachers.confirm_delete.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
