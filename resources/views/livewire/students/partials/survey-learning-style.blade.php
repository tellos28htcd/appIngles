{{-- Encuesta: ¿Modo de aprendizaje? (se guarda en el alumno: visual, auditivo, kinestésico). --}}
<div class="flex flex-col gap-1">
    <p class="text-sm font-semibold text-ink-900">{{ __('students.fields.learning_mode_question') }}</p>
    <div class="grid gap-x-6 sm:grid-cols-3">
        <x-ui.toggle name="form.learns_visual" :label="__('students.fields.learns_visual')" wire:model="form.learns_visual" />
        <x-ui.toggle name="form.learns_auditory" :label="__('students.fields.learns_auditory')" wire:model="form.learns_auditory" />
        <x-ui.toggle name="form.learns_kinesthetic" :label="__('students.fields.learns_kinesthetic')" wire:model="form.learns_kinesthetic" />
    </div>
</div>
