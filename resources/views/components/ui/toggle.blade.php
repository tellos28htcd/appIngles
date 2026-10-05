{{-- Interruptor accesible (checkbox con role="switch").
     Uso: <x-ui.toggle name="works_sundays" :label="__('...')" wire:model="form.works_sundays" /> --}}
@props(['name', 'label', 'id' => null, 'description' => null])
@php($id ??= str_replace('.', '-', $name))
<label for="{{ $id }}" class="flex min-h-11 cursor-pointer items-start gap-3 py-1.5">
    <span class="relative mt-0.5 inline-flex flex-none">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" role="switch" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-6 w-11 rounded-full bg-line-strong transition peer-checked:bg-primary-600 peer-focus-visible:ring-4 peer-focus-visible:ring-primary-200"></span>
        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-white shadow-xs transition peer-checked:translate-x-5"></span>
    </span>
    <span class="flex flex-col gap-0.5">
        <span class="text-[15px] font-semibold text-ink-900">{{ $label }}</span>
        @if ($description)
            <span class="text-[13px] text-ink-500">{{ $description }}</span>
        @endif
    </span>
</label>
