{{-- Campos de la inscripción (propiedad Livewire "enrollment"). Se usa en el alta y en el expediente. --}}
<div class="grid gap-4 md:grid-cols-3">
    <x-ui.input name="enrollment.contract_number" :label="__('students.enrollment.fields.contract_number')" wire:model="enrollment.contract_number" maxlength="30" class="font-mono uppercase" />
    <x-ui.select name="enrollment.sales_advisor_id" :label="__('students.enrollment.fields.sales_advisor_id')" :options="$this->advisorOptions"
                 :placeholder="__('students.enrollment.select_advisor')" wire:model="enrollment.sales_advisor_id" />
    <x-ui.input name="enrollment.registered_on" type="date" :label="__('students.enrollment.fields.registered_on')" wire:model="enrollment.registered_on" />

    <x-ui.select name="enrollment.student_plan_id" :label="__('students.enrollment.fields.student_plan_id')" :options="$this->planOptions"
                 :placeholder="__('students.enrollment.select_plan')" wire:model.live="enrollment.student_plan_id" />
    <x-ui.input name="enrollment.installments" type="number" :label="__('students.enrollment.fields.installments')" wire:model="enrollment.installments" min="0" max="60" class="tabular-nums" />
    <x-ui.input name="enrollment.material_serial" :label="__('students.enrollment.fields.material_serial')" :hint="__('students.enrollment.material_serial_hint')"
                wire:model="enrollment.material_serial" maxlength="40" class="font-mono uppercase" />

    <x-ui.input name="enrollment.starts_on" type="date" :label="__('students.enrollment.fields.starts_on')" :hint="__('students.enrollment.starts_on_hint')" wire:model="enrollment.starts_on" />
    <x-ui.input name="enrollment.ends_on" type="date" :label="__('students.enrollment.fields.ends_on')" wire:model="enrollment.ends_on" />
    <x-ui.input name="enrollment.reenrolled_on" type="date" :label="__('students.enrollment.fields.reenrolled_on')" wire:model="enrollment.reenrolled_on" />

    <x-ui.select name="enrollment.start_book_id" :label="__('students.enrollment.fields.start_book_id')" :options="$this->bookOptions"
                 :placeholder="__('students.enrollment.select_book')" wire:model.live="enrollment.start_book_id" />
    <x-ui.select name="enrollment.start_lesson_id" :label="__('students.enrollment.fields.start_lesson_id')" :options="$this->lessonOptions"
                 :placeholder="__($enrollment->start_book_id ? 'students.enrollment.select_lesson' : 'students.enrollment.select_book_first')"
                 wire:model="enrollment.start_lesson_id" wire:key="lessons-{{ $enrollment->start_book_id }}" :disabled="! $enrollment->start_book_id" />
    <x-ui.select name="enrollment.start_activity_id" :label="__('students.enrollment.fields.start_activity_id')" :options="$this->activityOptions"
                 :placeholder="__('students.enrollment.select_activity')" wire:model="enrollment.start_activity_id" />
</div>

<div class="grid gap-x-6 sm:grid-cols-2 lg:grid-cols-3">
    <x-ui.toggle name="enrollment.includes_term_extension" :label="__('students.enrollment.fields.includes_term_extension')" wire:model="enrollment.includes_term_extension" />
    <x-ui.toggle name="enrollment.includes_toefl" :label="__('students.enrollment.fields.includes_toefl')" wire:model="enrollment.includes_toefl" />
    <x-ui.toggle name="enrollment.includes_gold_card" :label="__('students.enrollment.fields.includes_gold_card')" wire:model="enrollment.includes_gold_card" />
    <x-ui.toggle name="enrollment.material_assigned" :label="__('students.enrollment.fields.material_assigned')" wire:model="enrollment.material_assigned" />
    <x-ui.toggle name="enrollment.commission_applies" :label="__('students.enrollment.fields.commission_applies')" wire:model="enrollment.commission_applies" />
</div>

@if ($enrollment->enrollment !== null)
    <div class="grid gap-4 md:grid-cols-3">
        <x-ui.input name="enrollment.graduated_on" type="date" :label="__('students.enrollment.fields.graduated_on')" wire:model="enrollment.graduated_on" />
        <x-ui.input name="enrollment.withdrawn_on" type="date" :label="__('students.enrollment.fields.withdrawn_on')" :hint="__('students.enrollment.withdrawn_on_hint')" wire:model="enrollment.withdrawn_on" />
    </div>
@endif
