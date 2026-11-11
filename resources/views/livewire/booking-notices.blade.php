<div>
    @if ($this->notices->isNotEmpty())
        <div class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-labelledby="booking-notices-title">
            <div class="absolute inset-0 bg-ink-900/45" aria-hidden="true"></div>
            <div x-data x-trap.inert.noscroll="true"
                 class="relative flex w-full flex-col gap-4 overflow-y-auto rounded-t-2xl bg-white px-6 pb-6 pt-7 shadow-pop md:max-w-[520px] md:rounded-2xl">
                <h2 id="booking-notices-title" class="flex items-center gap-2 font-display text-h3 font-extrabold text-primary-700">
                    <x-ui.icon name="calendar" class="size-6" />{{ __('agenda.moved.title') }}
                </h2>
                <p class="text-[15px] text-ink-700">{{ __('agenda.moved.body') }}</p>
                <ul class="flex flex-col gap-2">
                    @foreach ($this->notices as $notice)
                        @php($data = $notice->data)
                        <li wire:key="notice-{{ $notice->id }}" class="rounded-lg border border-line bg-surface px-3 py-2">
                            <span class="block text-sm font-semibold text-ink-500 first-letter:uppercase">
                                {{ \Carbon\CarbonImmutable::parse($data['date'])->translatedFormat('l j \d\e F') }} · {{ $data['hour'] }}
                            </span>
                            <span class="font-bold">{{ $data['place'] }}</span>@if ($data['teacher'] !== '') <span class="text-ink-700">· {{ __('agenda.moved.with', ['teacher' => $data['teacher']]) }}</span>@endif
                        </li>
                    @endforeach
                </ul>
                <div class="flex flex-col-reverse gap-2.5 md:flex-row md:justify-end">
                    <x-ui.button wire:click="dismiss">{{ __('agenda.moved.ok') }}</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
