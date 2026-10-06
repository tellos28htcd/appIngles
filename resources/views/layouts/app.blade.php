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
<body class="min-h-dvh" x-data="{ menuOpen: false }" x-on:keydown.escape.window="menuOpen = false">
    <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:shadow-pop">
        {{ __('layout.skip_to_content') }}
    </a>

    <div class="flex min-h-dvh">
        {{-- Barra lateral (escritorio) --}}
        {{-- Barra lateral (escritorio): mismo panel de marca que el login --}}
        <aside class="sticky top-0 hidden h-dvh w-64 flex-none overflow-hidden bg-primary-600 text-on-primary lg:block">
            <div class="pointer-events-none absolute -left-15 -top-15 size-50 rotate-[18deg] rounded-[48px] bg-primary-700" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-40 -right-36 size-[360px] rounded-full bg-primary-500" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-3 top-3 size-9 rounded-full bg-accent-500" aria-hidden="true"></div>

            <div class="relative flex h-full flex-col gap-7 overflow-y-auto px-4 py-6 [scrollbar-width:thin] [scrollbar-color:var(--color-primary-400)_transparent]">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-md px-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-on-primary/40">
                    <x-ui.brand-mark :brand="$brand" inverse />
                    <span class="flex min-w-0 flex-col">
                        <span class="truncate font-display text-base font-extrabold leading-tight">{{ $brand->name }}</span>
                        @if ($currentUser->isPlatformAdmin())
                            <span class="truncate text-xs text-on-primary/75">{{ __('access.platform_caption') }}</span>
                        @endif
                    </span>
                </a>

                <x-layout.menu :items="$menu" variant="brand" />

                <x-layout.user-card :user="$currentUser" variant="brand" class="mt-auto" />
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Barra superior (móvil y tablet) --}}
            <header class="sticky top-0 z-30 flex h-15 flex-none items-center gap-3 border-b border-line bg-white pl-4 pr-3 lg:hidden">
                <x-ui.brand-mark :brand="$brand" size="sm" />
                <div class="flex min-w-0 flex-1 flex-col">
                    <span class="truncate font-display text-[17px] font-extrabold">{{ $title ?? '' }}</span>
                    <span class="truncate text-xs text-ink-500">{{ $brand->name }}</span>
                </div>
                <button type="button" x-on:click="menuOpen = true" aria-controls="menu-movil" :aria-expanded="menuOpen.toString()"
                        aria-label="{{ __('layout.user_menu') }}"
                        class="flex size-11 items-center justify-center rounded-full bg-primary-100 text-[13px] font-extrabold text-primary-800 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    {{ $currentUser->initials() }}
                </button>
            </header>

            <main id="contenido" class="flex flex-1 flex-col gap-4 px-4 pb-28 pt-4 md:gap-6 md:px-6 lg:px-10 lg:pb-12 lg:pt-8">
                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Navegación inferior (móvil y tablet) --}}
    <x-layout.bottom-nav :items="$menu" />

    {{-- Menú completo en hoja inferior (móvil y tablet) --}}
    <div x-cloak x-show="menuOpen" class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" id="menu-movil" aria-label="{{ __('layout.menu') }}">
        <div x-show="menuOpen" x-transition.opacity class="absolute inset-0 bg-ink-900/45" x-on:click="menuOpen = false"></div>
        <div x-show="menuOpen" x-trap.inert.noscroll="menuOpen"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute inset-x-0 bottom-0 flex max-h-[88dvh] flex-col gap-5 overflow-y-auto rounded-t-2xl bg-white px-4 pb-6 pt-3 shadow-pop">
            <div class="mx-auto h-1.5 w-10 rounded-full bg-line-strong" aria-hidden="true"></div>
            <div class="flex items-center justify-between">
                <span class="font-display text-h4 font-bold">{{ __('layout.menu') }}</span>
                <button type="button" x-on:click="menuOpen = false" aria-label="{{ __('layout.close_menu') }}"
                        class="flex size-11 items-center justify-center rounded-md text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <x-ui.icon name="close" />
                </button>
            </div>
            <x-layout.menu :items="$menu" />
            <x-layout.user-card :user="$currentUser" />
        </div>
    </div>

    <x-ui.toasts />

    @livewireScripts
</body>
</html>
