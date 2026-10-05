<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1.5">
        <h1 class="text-[26px] font-extrabold tracking-tight md:text-[34px]">{{ __('access.login.title') }}</h1>
        <p class="text-[15px] text-ink-700">{{ __('access.login.subtitle') }}</p>
    </div>

    @if (session('status'))
        <div role="status" class="flex items-start gap-2.5 rounded-lg border border-success-200 bg-success-50 p-3.5 text-sm font-medium text-success-700">
            <x-ui.icon name="check" class="mt-px size-[18px]" />{{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="flex flex-col gap-4.5" novalidate>
        <x-ui.input name="email" type="email" size="lg" :label="__('access.fields.email')"
                    wire:model="email" autocomplete="username" inputmode="email" autofocus required />

        <x-ui.input name="password" size="lg" :label="__('access.fields.password')" revealable
                    wire:model="password" autocomplete="current-password" required />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label class="flex min-h-11 items-center gap-2.5 text-sm">
                <input type="checkbox" wire:model="remember" class="size-5 rounded accent-primary-600">
                {{ __('access.login.remember') }}
            </label>
            <a href="{{ route('password.request') }}" wire:navigate
               class="rounded text-sm font-bold text-primary-700 hover:underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('access.login.forgot') }}
            </a>
        </div>

        <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">{{ __('access.login.submit') }}</span>
            <span wire:loading.flex wire:target="login" class="items-center gap-2">
                <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('access.login.submitting') }}
            </span>
        </x-ui.button>
    </form>
</div>
