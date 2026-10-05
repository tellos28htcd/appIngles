{{-- Grupo de opciones excluyentes (radio) con estilo de tarjeta.
     Uso: <x-ui.choice name="form.self_booking" :label="__('...')" :options="[valor => texto]" wire:model="form.self_booking" /> --}}
@props(['name', 'label', 'options' => []])
@php($group = str_replace('.', '-', $name))
<fieldset class="flex flex-col gap-2">
    <legend class="mb-2 text-sm font-semibold text-ink-900">{{ $label }}</legend>
    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap">
        @foreach ($options as $value => $text)
            <label for="{{ $group }}-{{ $value }}"
                   class="flex min-h-11 flex-1 cursor-pointer items-center gap-2.5 rounded-md border-[1.5px] border-line-strong bg-white px-3.5 py-2 text-[15px] transition hover:bg-surface has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:font-semibold has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-primary-200">
                <input id="{{ $group }}-{{ $value }}" type="radio" name="{{ $group }}" value="{{ $value }}"
                       {{ $attributes->merge(['class' => 'size-[18px] flex-none accent-primary-600']) }}>
                {{ $text }}
            </label>
        @endforeach
    </div>
    @error($name)
        <p class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700"><x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}</p>
    @enderror
</fieldset>
