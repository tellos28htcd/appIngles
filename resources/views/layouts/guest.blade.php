<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? '' }} · {{ $brand->name }}</title>
    @include('layouts.partials.brand', ['school' => $brand])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-white">
    <div class="flex min-h-dvh flex-col md:flex-row">
        {{-- Panel de marca: arriba en móvil, a la izquierda en escritorio --}}
        <aside class="relative flex flex-none flex-col justify-between gap-5 overflow-hidden bg-primary-600 px-6 pb-9 pt-7 text-on-primary md:w-[46%] md:max-w-[560px] md:p-14">
            <div class="absolute -bottom-36 -right-30 size-[420px] rounded-full bg-primary-500" aria-hidden="true"></div>
            <div class="absolute bottom-44 right-30 hidden size-18 rounded-full bg-accent-500 md:block" aria-hidden="true"></div>
            <div class="absolute -left-15 -top-15 size-50 rotate-[18deg] rounded-[48px] bg-primary-700" aria-hidden="true"></div>

            <div class="relative flex items-center gap-3.5">
                <x-ui.brand-mark :brand="$brand" size="lg" inverse />
                <div class="flex flex-col gap-0.5">
                    <span class="font-display text-xl font-extrabold">{{ $brand->name }}</span>
                    <span class="text-[13px] opacity-85">{{ __('access.platform_caption') }}</span>
                </div>
            </div>

            <div class="relative flex max-w-[420px] flex-col gap-3">
                <p class="font-display text-2xl font-extrabold leading-[1.1] tracking-tight md:text-[44px]">{{ __('access.headline') }}</p>
                <p class="hidden text-[17px] leading-relaxed opacity-90 md:block">{{ __('access.tagline') }}</p>
            </div>
        </aside>

        <main class="flex flex-1 items-start justify-center px-5 pb-8 pt-7 md:items-center md:p-12">
            <div class="flex w-full max-w-[400px] flex-col gap-6">
                {{ $slot }}
                <p class="text-center text-xs text-ink-500">{{ __('access.powered_by') }} <b>AppIngles</b></p>
            </div>
        </main>
    </div>
    @livewireScripts
</body>
</html>
