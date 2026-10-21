<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('availability.schedule_heading')" :subtitle="$teacher->fullName().($teacher->contract_type ? ' · '.$teacher->contract_type->label() : '')">
        <x-slot:actions>
            <a href="{{ route('teachers.edit', $teacher) }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-4 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('students.actions.back') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :description="__('availability.schedule_hint')" class="max-w-5xl">
        <livewire:teachers.base-schedule-editor :teacher-id="$teacher->id" />
    </x-ui.card>
</div>
