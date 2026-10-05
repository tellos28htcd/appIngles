@props(['user'])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3']) }}>
    <div class="flex items-center gap-2.5 rounded-lg bg-surface p-3">
        <span class="flex size-9 flex-none items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-800" aria-hidden="true">
            {{ $user->initials() }}
        </span>
        <div class="flex min-w-0 flex-col">
            <span class="truncate text-sm font-bold">{{ $user->name }}</span>
            <span class="truncate text-xs text-ink-500">{{ $user->role?->name }}</span>
        </div>
    </div>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="flex h-11 w-full items-center gap-2.5 rounded-md px-3 text-sm font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
            <x-ui.icon name="logout" class="size-[18px]" />{{ __('layout.logout') }}
        </button>
    </form>
    <span class="px-3 text-[11px] text-ink-400">{{ __('access.powered_by') }} AppIngles</span>
</div>
