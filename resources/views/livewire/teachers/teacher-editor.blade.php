<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($editing ? 'teachers.edit_title' : 'teachers.create_title')"
                      :subtitle="$editing ? $form->teacher->fullName() : __('teachers.create_subtitle')" />

    <form wire:submit="save" class="flex max-w-5xl flex-col gap-4 md:gap-6" novalidate>
        {{-- Datos personales --}}
        <x-ui.card :title="__('teachers.sections.personal')">
            <div class="flex flex-col gap-5 md:flex-row">
                <div class="flex flex-col items-center gap-2 md:w-40 md:flex-none">
                    @if ($photoPreview)
                        <img src="{{ $photoPreview }}" alt="" class="size-28 rounded-full object-cover ring-1 ring-line">
                    @elseif ($form->teacher?->hasPhoto() && ! $form->remove_photo)
                        @include('livewire.teachers.partials.avatar', ['teacher' => $form->teacher, 'size' => 'size-28 text-2xl'])
                    @else
                        <span class="flex size-28 items-center justify-center rounded-full bg-surface-2 text-ink-400"><x-ui.icon name="student" class="size-10" /></span>
                    @endif
                    <label for="teacher-photo" class="cursor-pointer rounded-[10px] px-3 py-1.5 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-within:ring-4 focus-within:ring-primary-200">
                        {{ __('teachers.fields.photo_choose') }}
                        <input id="teacher-photo" type="file" wire:model="form.photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <span wire:loading wire:target="form.photo" class="text-xs text-ink-500">{{ __('access.reset.submitting') }}</span>
                    @if ($form->teacher?->hasPhoto() && ! $form->photo)
                        <label class="flex items-center gap-1.5 text-xs text-ink-700">
                            <input type="checkbox" wire:model.live="form.remove_photo" class="size-4 accent-primary-600">{{ __('teachers.fields.photo_remove') }}
                        </label>
                    @endif
                    <p class="text-center text-[11px] text-ink-500">{{ __('teachers.fields.photo_hint') }}</p>
                    @error('form.photo')
                        <p class="text-center text-[13px] font-medium text-danger-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid flex-1 gap-4 md:grid-cols-3">
                    <x-ui.input name="form.first_name" :label="__('teachers.fields.first_name')" wire:model="form.first_name" maxlength="80" autocomplete="given-name" required />
                    <x-ui.input name="form.last_name" :label="__('teachers.fields.last_name')" wire:model="form.last_name" maxlength="80" required />
                    <x-ui.input name="form.second_last_name" :label="__('teachers.fields.second_last_name')" wire:model="form.second_last_name" maxlength="80" />
                    <div class="md:col-span-2">
                        <x-ui.input name="form.curp" :label="__('teachers.fields.curp')" :hint="__('teachers.fields.optional')" wire:model="form.curp" maxlength="18" class="font-mono uppercase" />
                    </div>
                    <x-ui.input name="form.rfc" :label="__('teachers.fields.rfc')" :hint="__('teachers.fields.optional')" wire:model="form.rfc" maxlength="13" class="font-mono uppercase" />
                </div>
            </div>
        </x-ui.card>

        {{-- Domicilio --}}
        <x-ui.card :title="__('teachers.sections.address')">
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-3"><x-ui.input name="form.street" :label="__('schools.fields.street')" wire:model="form.street" autocomplete="address-line1" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.exterior_number" :label="__('schools.fields.exterior_number')" wire:model="form.exterior_number" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.interior_number" :label="__('schools.fields.interior_number')" wire:model="form.interior_number" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.postal_code" :label="__('schools.fields.postal_code')" wire:model="form.postal_code" inputmode="numeric" maxlength="5" autocomplete="postal-code" /></div>
                <div class="md:col-span-2"><x-ui.input name="form.neighborhood" :label="__('schools.fields.neighborhood')" wire:model="form.neighborhood" /></div>
                <div class="md:col-span-2">
                    <x-ui.select name="form.state_id" :label="__('schools.fields.state_id')" :options="$this->states"
                                 :placeholder="__('schools.fields.select_state')" wire:model.live="form.state_id" />
                </div>
                <div class="md:col-span-2">
                    <x-ui.select name="form.municipality_id" :label="__('schools.fields.municipality_id')" :options="$this->municipalities"
                                 :placeholder="__($form->state_id ? 'schools.fields.select_municipality' : 'schools.fields.select_state_first')"
                                 wire:model="form.municipality_id" wire:key="teacher-municipalities-{{ $form->state_id }}" :disabled="! $form->state_id" />
                </div>
            </div>
        </x-ui.card>

        <div class="grid gap-4 md:gap-6 lg:grid-cols-2">
            {{-- Contratación --}}
            <x-ui.card :title="__('teachers.sections.contract')" :description="__('teachers.sections.contract_hint')">
                <x-ui.choice name="form.contract_type" :label="__('teachers.fields.contract_type')" :options="$contractTypes" wire:model.live="form.contract_type" />
                <x-ui.input name="form.weekly_hours" type="number" :label="__('teachers.fields.weekly_hours')"
                            :hint="$fixedHours ? __('teachers.fields.weekly_hours_fixed') : __('teachers.fields.weekly_hours_hint')"
                            wire:model="form.weekly_hours" min="1" max="48" inputmode="numeric" class="tabular-nums"
                            :disabled="$fixedHours" required />
            </x-ui.card>

            {{-- Acceso --}}
            <x-ui.card :title="__('teachers.sections.access')" :description="$editing ? __('teachers.sections.access_hint_edit') : __('teachers.sections.access_hint')">
                @if ($isPlatform && ! $editing)
                    <x-ui.select name="form.school_id" :label="__('teachers.fields.school_id')" :options="$schoolOptions"
                                 :placeholder="__('users.fields.select_school')" wire:model="form.school_id" required />
                @endif
                <x-ui.input name="form.email" type="email" :label="__('teachers.fields.email')" wire:model="form.email"
                            autocomplete="email" inputmode="email" maxlength="255" required />
                <x-ui.select name="form.status" :label="__('teachers.fields.status')" :options="$statuses"
                             :hint="__('teachers.fields.status_hint')" wire:model="form.status" required />
            </x-ui.card>
        </div>

        <div class="sticky bottom-20 z-20 -mx-4 flex flex-col-reverse gap-2.5 border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:flex-row md:justify-end md:border-0 md:bg-transparent md:p-0">
            <a href="{{ route('teachers.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-5 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('teachers.actions.cancel') }}
            </a>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('teachers.actions.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
