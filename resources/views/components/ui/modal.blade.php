{{-- Modal (en móvil: hoja inferior). Se abre cuando la propiedad de wire:model tiene valor.
     Foco atrapado, Esc cierra, la acción destructiva va a la derecha y nunca recibe el foco inicial.
     Uso: <x-ui.modal wire:model="confirmingDeletionId" :title="..."> … <x-slot:footer>…</x-slot:footer></x-ui.modal> --}}
@props(['title', 'size' => 'sm'])
@php
$model = $attributes->wire('model')->value();
$width = ['sm' => 'md:max-w-[480px]', 'md' => 'md:max-w-[640px]', 'lg' => 'md:max-w-[880px]'][$size];
@endphp
<div x-data="{ open: $wire.entangle('{{ $model }}') }" x-cloak x-show="open" x-on:keydown.escape.window="open = null"
     class="fixed inset-0 z-50 flex items-end justify-center md:items-center md:p-6" role="dialog" aria-modal="true" aria-labelledby="modal-{{ $model }}-title">
    <div x-show="open" x-transition.opacity class="absolute inset-0 bg-ink-900/45" x-on:click="open = null"></div>
    <div x-show="open" x-trap.inert.noscroll="open" x-transition
         class="relative flex max-h-[92dvh] w-full flex-col gap-5 overflow-y-auto overscroll-contain rounded-t-2xl bg-white p-5 shadow-pop md:max-h-[88dvh] md:rounded-2xl md:p-6 {{ $width }}">
        <h2 id="modal-{{ $model }}-title" class="pr-8 font-display text-h3 font-bold">{{ $title }}</h2>
        <div class="flex flex-col gap-4 text-[15px] text-ink-700">{{ $slot }}</div>
        @isset($footer)
            <div class="flex flex-col-reverse gap-2.5 md:flex-row md:justify-end">{{ $footer }}</div>
        @endisset
    </div>
</div>
