<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('students.guardians.title')" :subtitle="__('students.guardians.subtitle')" />

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        <div class="border-b border-line p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="guardians-search" class="sr-only">{{ __('students.guardians.search') }}</label>
                <input id="guardians-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('students.guardians.search') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->guardians->isEmpty())
                <x-ui.empty-state icon="heart" :title="__('students.guardians.list_empty')" :description="__('students.guardians.list_empty_hint')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->guardians as $guardian)
                        <li wire:key="guardian-{{ $guardian->id }}" class="flex flex-col gap-3 p-4 hover:bg-surface md:flex-row md:items-center md:gap-4 md:px-5">
                            <div class="flex min-w-0 flex-1 flex-col">
                                <span class="font-bold [overflow-wrap:anywhere]">{{ $guardian->name }}</span>
                                <span class="text-sm text-ink-500">{{ collect([$guardian->mobile_phone, $guardian->email])->filter()->implode(' · ') ?: '—' }}</span>
                            </div>
                            <div class="flex min-w-0 flex-col gap-0.5 text-sm md:w-72 md:flex-none">
                                @foreach ($guardian->students as $student)
                                    <a href="{{ route('students.show', $student) }}" wire:navigate class="truncate text-primary-700 hover:underline">
                                        <span class="font-mono text-xs">{{ $student->enrollment_number }}</span> · {{ $student->fullName() }}
                                    </a>
                                @endforeach
                            </div>
                            <div class="md:w-36 md:flex-none">
                                @if ($guardian->user === null)
                                    <x-ui.badge variant="neutral">{{ __('students.guardians.no_access') }}</x-ui.badge>
                                @elseif (! $guardian->user->hasPassword())
                                    <x-ui.badge variant="info">{{ __('students.access_pending') }}</x-ui.badge>
                                @else
                                    <x-ui.badge variant="success">{{ __('students.guardians.has_access') }}</x-ui.badge>
                                @endif
                            </div>
                            <div class="flex items-center justify-end gap-0.5 md:flex-none">
                                @php($button = 'flex size-9 flex-none items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
                                @can('update', $guardian)
                                    <button type="button" wire:click="edit({{ $guardian->id }})" class="{{ $button }} text-primary-700 hover:bg-primary-50"
                                            aria-label="{{ __('students.guardians.edit', ['name' => $guardian->name]) }}" title="{{ __('students.actions.edit_short') }}">
                                        <x-ui.icon name="pencil" class="size-4" />
                                    </button>
                                @endcan
                                @can('grantAccess', $guardian)
                                    <button type="button" wire:click="grantAccess({{ $guardian->id }})" wire:loading.attr="disabled" wire:target="grantAccess({{ $guardian->id }})"
                                            class="{{ $button }} text-ink-700 hover:bg-surface-2"
                                            aria-label="{{ __('students.guardians.grant_named', ['name' => $guardian->name]) }}"
                                            title="{{ __($guardian->user ? 'students.guardians.resend' : 'students.guardians.grant') }}">
                                        <x-ui.icon name="mail" class="size-4" />
                                    </button>
                                @endcan
                                @can('delete', $guardian)
                                    <button type="button" wire:click="confirmDeletion({{ $guardian->id }})" class="{{ $button }} text-danger-700 hover:bg-danger-50"
                                            aria-label="{{ __('students.guardians.delete', ['name' => $guardian->name]) }}" title="{{ __('students.actions.delete_short') }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($this->guardians->hasPages())
            <div class="border-t border-line p-4">{{ $this->guardians->links('partials.pagination') }}</div>
        @endif
    </section>

    <x-ui.modal wire:model="editing" size="md" :title="__('students.guardians.edit_title')">
        <x-ui.input name="name" :label="__('students.guardians.name')" wire:model="name" maxlength="160" class="uppercase" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="mobilePhone" type="tel" :label="__('students.guardians.phone')" wire:model="mobilePhone" inputmode="tel" maxlength="20" />
            <x-ui.input name="email" type="email" :label="__('students.guardians.email')" :hint="__('students.guardians.email_hint')" wire:model="email" inputmode="email" maxlength="255" />
        </div>
        <fieldset class="flex flex-col gap-1">
            <legend class="mb-1 text-sm font-semibold text-ink-900">{{ __('students.guardians.receives') }}</legend>
            @foreach ($reports as $value => $label)
                <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-[10px] px-2 hover:bg-surface">
                    <input type="checkbox" value="{{ $value }}" wire:model="receives" class="size-[18px] rounded accent-primary-600">
                    <span class="text-[15px]">{{ $label }}</span>
                </label>
            @endforeach
        </fieldset>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('students.guardians.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('students.guardians.confirm_delete', ['name' => $this->pendingDeletion?->name])">
        <p>{{ __('students.guardians.confirm_delete_body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('students.actions.delete_short') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
