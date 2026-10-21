{{-- Campos de la inscripción (propiedad Livewire "enrollment"). Se usa en el alta y en el expediente.
     Inicio del curso, baja y graduación se capturan en datos generales; el fin del curso se calcula. --}}
<div class="grid gap-4 md:grid-cols-3">
    <x-ui.input name="enrollment.contract_number" :label="__('students.enrollment.fields.contract_number')" wire:model="enrollment.contract_number" maxlength="30" class="font-mono uppercase" />
    <x-ui.input name="enrollment.registered_on" type="date" :label="__('students.enrollment.fields.registered_on')" wire:model="enrollment.registered_on" />
    <x-ui.input name="enrollment.material_delivered_on" type="date" :label="__('students.enrollment.fields.material_delivered_on')" wire:model="enrollment.material_delivered_on"
                :hint="__('students.enrollment.material_delivered_on_hint')" />
    <x-ui.input name="enrollment.reenrolled_on" type="date" :label="__('students.enrollment.fields.reenrolled_on')" :hint="__('students.enrollment.reenrolled_on_hint')" wire:model="enrollment.reenrolled_on" />

    <x-ui.select name="enrollment.sales_advisor_id" :label="__('students.enrollment.fields.sales_advisor_id')" :options="$this->advisorOptions"
                 :placeholder="__('students.enrollment.select_advisor')" wire:model="enrollment.sales_advisor_id" />
    <div class="md:pt-7">
        <x-ui.toggle name="enrollment.commission_applies" :label="__('students.enrollment.fields.commission_applies')" :description="__('students.enrollment.commission_applies_hint')" wire:model="enrollment.commission_applies" />
    </div>

    <x-ui.select name="enrollment.student_plan_id" :label="__('students.enrollment.fields.student_plan_id')" :options="$this->planOptions"
                 :placeholder="__('students.enrollment.select_plan')" wire:model.live="enrollment.student_plan_id" />
    <x-ui.input name="enrollment.installments" type="number" :label="__('students.enrollment.fields.installments')" wire:model="enrollment.installments" min="0" max="60" class="tabular-nums" />

    <x-ui.select name="enrollment.start_book_id" :label="__('students.enrollment.fields.start_book_id')" :options="$this->bookOptions"
                 :placeholder="__('students.enrollment.select_book')" wire:model.live="enrollment.start_book_id" />
    <x-ui.select name="enrollment.start_lesson_id" :label="__('students.enrollment.fields.start_lesson_id')" :options="$this->lessonOptions"
                 :placeholder="__($enrollment->start_book_id ? 'students.enrollment.select_lesson' : 'students.enrollment.select_book_first')"
                 wire:model="enrollment.start_lesson_id" wire:key="lessons-{{ $enrollment->start_book_id }}" :disabled="! $enrollment->start_book_id" />
    <x-ui.select name="enrollment.start_activity_id" :label="__('students.enrollment.fields.start_activity_id')" :options="$this->activityOptions"
                 :placeholder="__('students.enrollment.select_activity')" wire:model="enrollment.start_activity_id" />
</div>

{{-- Servicios incluidos --}}
<div class="grid gap-4 md:grid-cols-2">
    <x-ui.toggle name="enrollment.includes_toefl" :label="__('students.enrollment.fields.includes_toefl')" wire:model="enrollment.includes_toefl" />
    <x-ui.toggle name="enrollment.includes_gold_card" :label="__('students.enrollment.fields.includes_gold_card')" wire:model="enrollment.includes_gold_card" />
</div>

{{-- Material: la serie y el tipo solo si aplica material --}}
<div class="grid gap-4 md:grid-cols-3">
    <x-ui.toggle name="enrollment.material_assigned" :label="__('students.enrollment.fields.material_assigned')" wire:model.live="enrollment.material_assigned" />
    @if ($enrollment->material_assigned)
        <x-ui.input name="enrollment.material_serial" :label="__('students.enrollment.fields.material_serial')" :hint="__('students.enrollment.material_serial_hint')"
                    wire:model="enrollment.material_serial" maxlength="40" class="font-mono uppercase" />
        <x-ui.select name="enrollment.material_type" :label="__('students.enrollment.fields.material_type')" :options="__('students.enrollment.material_types')"
                     :placeholder="__('students.select')" wire:model="enrollment.material_type" />
    @endif
</div>
