{{-- Tarjeta de sección de formulario o contenido. --}}
@props(['title' => null, 'description' => null])
<section {{ $attributes->merge(['class' => 'flex flex-col gap-5 rounded-xl border border-line bg-white p-5 shadow-xs md:p-6']) }}>
    @if ($title)
        <div class="flex flex-col gap-1">
            <h2 class="text-h4 font-semibold">{{ $title }}</h2>
            @if ($description)
                <p class="text-sm text-ink-500">{{ $description }}</p>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
