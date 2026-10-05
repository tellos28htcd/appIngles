{{-- Campo con etiqueta siempre visible, foco con anillo de marca y error en línea.
     Uso: <x-ui.input name="email" type="email" :label="__('...')" wire:model="email" />
     revealable: agrega botón Mostrar/Ocultar para contraseñas. --}}
@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'hint' => null,
    'revealable' => false,
    'size' => 'md',
])
@php
$id ??= $name;
$hasError = $errors->has($name);
$height = $size === 'lg' ? 'h-12' : 'h-11';
$field = "w-full $height rounded-md border-[1.5px] bg-white px-3.5 text-base text-ink-900 placeholder:text-ink-400 transition
          focus:outline-none focus:ring-4 disabled:bg-surface-2 disabled:text-ink-500 "
    .($hasError ? 'border-danger-600 ring-4 ring-danger-50 focus:border-danger-600 focus:ring-danger-50'
                : 'border-line-strong focus:border-primary-500 focus:ring-primary-100')
    .($revealable ? ' pr-24' : '');
$describedBy = trim(($hint ? "$id-hint " : '').($hasError ? "$id-error" : ''));
@endphp
<div class="flex flex-col gap-2">
    <label for="{{ $id }}" class="text-sm font-semibold text-ink-900">{{ $label }}</label>

    @if ($revealable)
        <div class="relative flex items-center" x-data="{ shown: false }">
            <input id="{{ $id }}" name="{{ $name }}" :type="shown ? 'text' : 'password'" type="password"
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                   @if ($hasError) aria-invalid="true" @endif
                   {{ $attributes->merge(['class' => $field]) }}>
            <button type="button" x-on:click="shown = !shown"
                    class="absolute right-1.5 h-9 rounded-[9px] px-3 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                    :aria-label="shown ? @js(__('access.fields.hide_password')) : @js(__('access.fields.show_password'))"
                    :aria-pressed="shown.toString()">
                <span x-text="shown ? @js(__('access.fields.hide')) : @js(__('access.fields.show'))">{{ __('access.fields.show') }}</span>
            </button>
        </div>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               @if ($hasError) aria-invalid="true" @endif
               {{ $attributes->merge(['class' => $field]) }}>
    @endif

    @if ($hint && ! $hasError)
        <p id="{{ $id }}-hint" class="text-[13px] text-ink-500">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $id }}-error" class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700">
            <x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}
        </p>
    @enderror
</div>
