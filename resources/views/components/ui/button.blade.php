@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button'])
@php
$base = 'inline-flex items-center justify-center gap-2 rounded-md font-bold transition
         focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200
         disabled:opacity-50 disabled:pointer-events-none';
$variants = [
    'primary'   => 'bg-primary-600 text-on-primary hover:bg-primary-700',
    'secondary' => 'bg-primary-50 text-primary-700 hover:bg-primary-100',
    'outline'   => 'border-[1.5px] border-line-strong bg-white text-ink-900 hover:bg-surface',
    'ghost'     => 'text-ink-700 hover:bg-surface-2',
    'accent'    => 'bg-accent-500 text-on-accent hover:bg-accent-600',
    'danger'    => 'bg-danger-600 text-white hover:bg-danger-700',
];
$sizes = [
    'sm' => 'h-9 px-3.5 text-sm rounded-[10px]',
    'md' => 'h-11 px-5 text-[15px]',
    'lg' => 'h-13 px-7 text-base rounded-[14px]',
];
@endphp
<button type="{{ $type }}" {{ $attributes->merge(['class' => "$base {$variants[$variant]} {$sizes[$size]}"]) }}>
    @if ($attributes->wire('click')->value())
        <span wire:loading wire:target="{{ $attributes->wire('click')->value() }}"
              class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent"></span>
    @endif
    {{ $slot }}
</button>
