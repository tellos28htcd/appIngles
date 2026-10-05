<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold tracking-tight md:text-[34px]">{{ __('invitations.title') }}</h1>
        <p class="text-[15px] text-ink-700">{{ __('invitations.subtitle') }}</p>
    </div>

    <div class="flex items-center gap-2.5 rounded-lg bg-surface px-3.5 py-3 text-sm">
        <x-ui.icon name="mail" class="size-[18px] text-ink-500" />
        <span class="truncate font-semibold">{{ $email }}</span>
    </div>

    <form wire:submit="save" class="flex flex-col gap-4.5" novalidate>
        <x-ui.input name="password" size="lg" :label="__('access.fields.password')" revealable
                    :hint="__('access.reset.subtitle')"
                    wire:model="password" autocomplete="new-password" autofocus required />

        <x-ui.input name="password_confirmation" size="lg" :label="__('access.fields.password_confirmation')" revealable
                    wire:model="password_confirmation" autocomplete="new-password" required />

        <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">{{ __('invitations.submit') }}</span>
            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('access.reset.submitting') }}
            </span>
        </x-ui.button>
    </form>
</div>
