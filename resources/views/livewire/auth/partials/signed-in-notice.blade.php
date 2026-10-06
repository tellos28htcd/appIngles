{{-- El enlace del correo se abrió con otra sesión iniciada en este navegador. --}}
<div role="alert" class="flex flex-col gap-4 rounded-xl border border-warning-200 bg-warning-50 p-4">
    <div class="flex items-start gap-3">
        <x-ui.icon name="alert" class="mt-0.5 size-5 text-warning-700" />
        <div class="flex flex-col gap-1">
            <p class="font-bold text-ink-900">{{ __('access.signed_in.title', ['name' => auth()->user()->name]) }}</p>
            <p class="text-sm text-ink-700">{{ __('access.signed_in.body') }}</p>
        </div>
    </div>
    <x-ui.button size="lg" class="w-full" wire:click="signOutAndContinue">{{ __('access.signed_in.action') }}</x-ui.button>
</div>
