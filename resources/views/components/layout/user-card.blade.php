{{-- Usuario conectado + cerrar sesión. variant: "light" | "brand" (sobre primary-600). --}}
@props(['user', 'variant' => 'light'])
@php
$brand = $variant === 'brand';
@endphp
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <div @class(['flex items-center gap-2.5 rounded-lg p-3', 'bg-on-primary/10' => $brand, 'bg-surface' => ! $brand])>
        <span @class([
            'flex size-9 flex-none items-center justify-center rounded-full text-[13px] font-bold',
            'bg-on-primary text-primary-700' => $brand,
            'bg-primary-100 text-primary-800' => ! $brand,
        ]) aria-hidden="true">
            {{ $user->initials() }}
        </span>
        <div class="flex min-w-0 flex-col">
            <span @class(['truncate text-sm font-bold', 'text-on-primary' => $brand])>{{ $user->name }}</span>
            <span @class(['truncate text-xs', 'text-on-primary/70' => $brand, 'text-ink-500' => ! $brand])>{{ $user->role?->name }}</span>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" @class([
            'flex h-11 w-full items-center gap-2.5 rounded-md px-3 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4',
            'text-on-primary/85 hover:bg-on-primary/10 hover:text-on-primary focus-visible:ring-on-primary/40' => $brand,
            'text-ink-700 hover:bg-surface-2 focus-visible:ring-primary-200' => ! $brand,
        ])>
            <x-ui.icon name="logout" class="size-[18px]" />{{ __('layout.logout') }}
        </button>
    </form>
    <span @class(['px-3 text-[11px]', 'text-on-primary/60' => $brand, 'text-ink-400' => ! $brand])>{{ __('access.powered_by') }} AppIngles</span>
</div>
