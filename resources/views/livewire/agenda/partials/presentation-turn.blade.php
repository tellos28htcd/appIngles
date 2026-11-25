{{-- Presentación (A7) que le toca o que dejó pendiente. Variables: $turn (AgendaService::presentationTurn) y, en
     "Nueva reservación", $canLeavePending (teacher o Recepción: "Dejar pendiente y continuar"). --}}
@if ($turn)
    <div class="flex flex-col gap-2 rounded-lg border border-warning-200 bg-warning-50 p-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="flex items-start gap-2 text-sm font-semibold text-warning-700">
            <x-ui.icon name="alert" class="mt-px size-4 flex-none" />
            <span>{{ __($turn['pending'] ? 'agenda.presentation.pending' : 'agenda.presentation.turn', ['practice' => $turn['step']['label']]) }}</span>
        </p>
        @if (! $turn['pending'] && ($canLeavePending ?? false))
            <x-ui.button size="sm" variant="secondary" class="flex-none" wire:click="leavePresentationPending"
                         wire:confirm="{{ __('agenda.presentation.confirm_pending', ['practice' => $turn['step']['label']]) }}">
                {{ __('agenda.presentation.leave_pending') }}
            </x-ui.button>
        @endif
    </div>
@endif
