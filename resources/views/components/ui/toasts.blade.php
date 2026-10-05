{{-- Avisos: escritorio abajo a la derecha, móvil arriba a lo ancho. 5 s; los errores persisten.
     Desde Livewire: $this->dispatch('toast', type: 'success', message: '...');
     Tras redirigir: session()->flash('toast', ['type' => 'success', 'message' => '...']); --}}
<div x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, type: detail.type ?? 'success', message: detail.message });
            if ((detail.type ?? 'success') !== 'error') setTimeout(() => this.remove(id), 5000);
        },
        remove(id) { this.toasts = this.toasts.filter(t => t.id !== id) },
     }"
     x-init="@if (session('toast')) add(@js(session('toast'))) @endif"
     x-on:toast.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-4 top-4 z-[60] flex flex-col items-center gap-2 md:inset-x-auto md:bottom-8 md:right-8 md:top-auto md:items-end"
     aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div role="status" x-transition
             class="pointer-events-auto flex w-full max-w-[380px] items-center gap-3 rounded-lg border bg-white px-4 py-3.5 shadow-pop"
             :class="{ 'border-success-200': toast.type === 'success', 'border-danger-200': toast.type === 'error', 'border-warning-200': toast.type === 'warning' }">
            <span class="flex size-7 flex-none items-center justify-center rounded-full"
                  :class="{ 'bg-success-50 text-success-700': toast.type === 'success', 'bg-danger-50 text-danger-700': toast.type === 'error', 'bg-warning-50 text-warning-700': toast.type === 'warning' }">
                <template x-if="toast.type === 'success'"><x-ui.icon name="check" class="size-4" /></template>
                <template x-if="toast.type !== 'success'"><x-ui.icon name="alert" class="size-4" /></template>
            </span>
            <span class="flex-1 text-[15px] font-semibold text-ink-900" x-text="toast.message"></span>
            <button type="button" x-on:click="remove(toast.id)" aria-label="{{ __('layout.close') }}"
                    class="flex size-9 flex-none items-center justify-center rounded-[10px] text-ink-500 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                <x-ui.icon name="close" class="size-4" />
            </button>
        </div>
    </template>
</div>
