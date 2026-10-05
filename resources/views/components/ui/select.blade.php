{{-- Select nativo con etiqueta visible y error en línea.
     Uso: <x-ui.select name="role_id" :label="__('...')" :options="[id => texto]" :placeholder="__('...')" wire:model.live="role_id" /> --}}
@props(['name', 'label', 'options' => [], 'placeholder' => null, 'id' => null, 'hint' => null, 'srOnlyLabel' => false])
@php
$id ??= str_replace('.', '-', $name);
$hasError = $errors->has($name);
$classes = 'h-11 w-full appearance-none rounded-md border-[1.5px] bg-white pl-3.5 pr-10 text-base text-ink-900 transition
            focus:outline-none focus:ring-4 disabled:bg-surface-2 disabled:text-ink-500 '
    .($hasError ? 'border-danger-600 ring-4 ring-danger-50' : 'border-line-strong focus:border-primary-500 focus:ring-primary-100');
@endphp
<div class="flex flex-col gap-2">
    <label for="{{ $id }}" @class(['text-sm font-semibold text-ink-900', 'sr-only' => $srOnlyLabel])>{{ $label }}</label>
    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}"
                @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                {{ $attributes->merge(['class' => $classes]) }}>
            @if ($placeholder !== null)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}">{{ $text }}</option>
            @endforeach
        </select>
        <x-ui.icon name="chevron-down" class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-ink-500" />
    </div>
    @if ($hint && ! $hasError)
        <p class="text-[13px] text-ink-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700">
            <x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}
        </p>
    @enderror
</div>
