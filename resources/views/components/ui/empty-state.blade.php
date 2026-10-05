@props(['icon' => 'sparkles', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center gap-4 px-6 py-12 text-center']) }}>
    <span class="flex size-14 items-center justify-center rounded-xl bg-primary-50 text-primary-700">
        <x-ui.icon :name="$icon" class="size-7" />
    </span>
    <div class="flex max-w-prose flex-col gap-1.5">
        <p class="font-display text-h4 font-bold">{{ $title }}</p>
        @if ($description)
            <p class="text-sm text-ink-700">{{ $description }}</p>
        @endif
    </div>
    {{ $slot }}
</div>
