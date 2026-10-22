<div>
    @if ($greeting = $this->greeting)
        @php($data = $greeting->data)
        <div class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-labelledby="birthday-title">
            <div class="absolute inset-0 bg-ink-900/45" aria-hidden="true"></div>
            <div x-data x-trap.inert.noscroll="true"
                 class="relative flex w-full flex-col items-center gap-4 overflow-y-auto rounded-t-2xl bg-white px-6 pb-6 pt-8 text-center shadow-pop md:max-w-[480px] md:rounded-2xl">
                <span class="flex size-20 items-center justify-center rounded-full bg-accent-100 text-5xl" aria-hidden="true">🎂</span>
                <h2 id="birthday-title" class="font-display text-h2 font-extrabold text-primary-700">
                    {{ ($data['age'] ?? null) ? __('birthdays.title_age', ['age' => $data['age'], 'name' => $data['first_name']]) : __('birthdays.title', ['name' => $data['first_name']]) }}
                </h2>
                <p class="text-[15px] leading-relaxed text-ink-700">{{ __('birthdays.body', ['school' => $data['school'] ?: config('appingles.brand.name')]) }}</p>
                <div class="mt-2 flex w-full flex-col-reverse gap-2.5 md:flex-row md:justify-center">
                    <x-ui.button variant="ghost" wire:click="dismiss">{{ __('birthdays.close') }}</x-ui.button>
                    <x-ui.button wire:click="dismiss">{{ __('birthdays.mark_read') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
