<div class="flex flex-col gap-6">
    @if ($sent)
        <div class="flex flex-col items-start gap-4">
            <span class="flex size-12 items-center justify-center rounded-xl bg-success-50 text-success-700"><x-ui.icon name="mail" class="size-6" /></span>
            <div class="flex flex-col gap-1.5" role="status">
                <h1 class="text-[26px] font-extrabold tracking-tight md:text-[34px]">{{ __('access.forgot.sent_title') }}</h1>
                <p class="text-[15px] text-ink-700">{{ __('access.forgot.sent') }}</p>
            </div>
        </div>
    @else
        <div class="flex flex-col gap-1.5">
            <h1 class="text-[26px] font-extrabold tracking-tight md:text-[34px]">{{ __('access.forgot.title') }}</h1>
            <p class="text-[15px] text-ink-700">{{ __('access.forgot.subtitle') }}</p>
        </div>

        <form wire:submit="sendLink" class="flex flex-col gap-4.5" novalidate>
            <x-ui.input name="email" type="email" size="lg" :label="__('access.fields.email')"
                        wire:model="email" autocomplete="username" inputmode="email" autofocus required />

            <x-ui.button type="submit" size="lg" class="w-full" wire:loading.attr="disabled" wire:target="sendLink">
                <span wire:loading.remove wire:target="sendLink">{{ __('access.forgot.submit') }}</span>
                <span wire:loading.flex wire:target="sendLink" class="items-center gap-2">
                    <span class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                    {{ __('access.forgot.submitting') }}
                </span>
            </x-ui.button>
        </form>
    @endif

    <a href="{{ route('login') }}" wire:navigate
       class="self-start rounded text-sm font-bold text-primary-700 hover:underline focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
        ← {{ __('access.forgot.back') }}
    </a>
</div>
