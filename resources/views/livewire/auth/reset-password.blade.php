<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold tracking-tight md:text-[34px]">{{ __('access.reset.title') }}</h1>
        <p class="text-[15px] text-ink-700">{{ __('access.reset.subtitle') }}</p>
    </div>

    @auth
        @include('livewire.auth.partials.signed-in-notice')
    @else
    <form wire:submit="resetPassword" class="flex flex-col gap-4.5" novalidate>
        <x-ui.input name="email" type="email" size="lg" :label="__('access.fields.email')"
                    wire:model="email" autocomplete="username" inputmode="email" required />

        <x-ui.input name="password" size="lg" :label="__('access.fields.password')" revealable
                    wire:model="password" autocomplete="new-password" autofocus required />

        <x-ui.input name="password_confirmation" size="lg" :label="__('access.fields.password_confirmation')" revealable
                    wire:model="password_confirmation" autocomplete="new-password" required />

        <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">{{ __('access.reset.submit') }}</span>
            <span wire:loading.flex wire:target="resetPassword" class="items-center gap-2">
                <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('access.reset.submitting') }}
            </span>
        </x-ui.button>
    </form>
    @endauth
</div>
