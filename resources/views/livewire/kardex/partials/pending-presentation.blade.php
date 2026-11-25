{{-- Aviso junto al alumno: tiene una presentación (A7) pendiente en el Kardex. Variable: $student (con pendingPresentation cargado). --}}
@if ($student?->pendingPresentation)
    <span class="ml-1 inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs font-bold text-warning-700"
          title="{{ __('kardex.pending_presentation_of', ['practice' => $student->pendingPresentation->practiceLabel()]) }}">
        <x-ui.icon name="alert" class="size-3.5" />{{ __('kardex.pending_presentation_short', ['practice' => $student->pendingPresentation->practiceLabel()]) }}
    </span>
@endif
