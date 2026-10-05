@props(['name', 'label', 'id' => null, 'rows' => 3, 'hint' => null])
@php
$id ??= $name;
$hasError = $errors->has($name);
$classes = 'w-full rounded-md border-[1.5px] bg-white px-3.5 py-2.5 text-base text-ink-900 placeholder:text-ink-400 transition focus:outline-none focus:ring-4 '
    .($hasError ? 'border-danger-600 ring-4 ring-danger-50' : 'border-line-strong focus:border-primary-500 focus:ring-primary-100');
@endphp
<div class="flex flex-col gap-2">
    <label for="{{ $id }}" class="text-sm font-semibold text-ink-900">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
              @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
              {{ $attributes->merge(['class' => $classes]) }}></textarea>
    @if ($hint && ! $hasError)
        <p class="text-[13px] text-ink-500">{{ $hint }}</p>
    @endif
    @error($name)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700">
            <x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}
        </p>
    @enderror
</div>
