{{-- Encuesta (Comunicación): acepta aparecer en publicidad (se guarda en el alumno). --}}
<div class="md:w-1/2">
    <x-ui.toggle name="form.advertising_consent" :label="__('students.fields.advertising_consent')" wire:model="form.advertising_consent" />
</div>
