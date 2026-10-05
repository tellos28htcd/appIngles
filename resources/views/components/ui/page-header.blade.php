{{-- Encabezado de página: título, subtítulo y acción principal (slot "actions"). --}}
@props(['title', 'subtitle' => null])
<header {{ $attributes->merge(['class' => 'flex flex-col gap-4 md:flex-row md:items-end md:justify-between']) }}>
    <div class="flex min-w-0 flex-col gap-1">
        <h1 class="text-[28px] font-extrabold tracking-tight md:text-[30px]">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-[15px] text-ink-500">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap gap-2.5">{{ $actions }}</div>
    @endisset
</header>
