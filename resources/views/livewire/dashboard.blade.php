<div class="flex flex-col gap-6">
    <header class="flex flex-col gap-1">
        <h1 class="text-[28px] font-extrabold tracking-tight md:text-h1">{{ __('dashboard.greeting', ['name' => $firstName]) }}</h1>
        <p class="text-[15px] text-ink-500">{{ $todayLabel }} · {{ __('dashboard.role', ['role' => $user->role?->name]) }}</p>
    </header>

    <div class="grid gap-4 md:gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section class="flex flex-col items-center gap-4 rounded-xl border border-line bg-white px-6 py-12 text-center shadow-xs">
            <span class="flex size-14 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
                <x-ui.icon name="sparkles" class="size-7" />
            </span>
            <div class="flex max-w-prose flex-col gap-2">
                <h2 class="text-h3 font-bold">{{ __('dashboard.empty.title') }}</h2>
                <p class="text-ink-700">{{ __('dashboard.empty.body') }}</p>
            </div>
        </section>

        <section class="flex flex-col gap-4 rounded-xl border border-line bg-white p-5 shadow-xs md:p-6" aria-labelledby="roadmap-title">
            <h2 id="roadmap-title" class="text-h4 font-semibold">{{ __('dashboard.roadmap.title') }}</h2>
            <ol class="flex flex-col gap-3">
                @foreach ($roadmap as $step => $state)
                    <li class="flex items-center gap-3">
                        @if ($state === 'done')
                            <span class="flex size-7 flex-none items-center justify-center rounded-full bg-success-50 text-success-700"><x-ui.icon name="check" class="size-4" /></span>
                        @else
                            <span @class([
                                'flex size-7 flex-none items-center justify-center rounded-full text-xs font-extrabold',
                                'bg-accent-500 text-on-accent' => $state === 'next',
                                'bg-surface-2 text-ink-500' => $state === 'pending',
                            ])>{{ $loop->iteration }}</span>
                        @endif
                        <span class="flex-1 text-sm font-semibold">{{ __("dashboard.roadmap.items.$step") }}</span>
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-xs font-bold',
                            'bg-success-50 text-success-700' => $state === 'done',
                            'bg-accent-100 text-ink-900' => $state === 'next',
                            'bg-surface-2 text-ink-500' => $state === 'pending',
                        ])>{{ __("dashboard.roadmap.$state") }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    </div>
</div>
