<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($editing ? 'students.edit_title' : 'students.create_title')"
                      :subtitle="$editing ? $form->enrollment_number.' · '.$form->student->fullName() : __('students.create_subtitle')" />

    <form wire:submit="save" class="flex max-w-5xl flex-col gap-4 md:gap-6" novalidate>
        {{-- Datos generales --}}
        <x-ui.card :title="__('students.sections.general')">
            <div class="flex flex-col gap-5 md:flex-row">
                <div class="flex flex-col items-center gap-2 md:w-40 md:flex-none">
                    @if ($photoPreview)
                        <img src="{{ $photoPreview }}" alt="" class="size-28 rounded-full object-cover ring-1 ring-line">
                    @elseif ($form->student?->hasPhoto() && ! $form->remove_photo)
                        @include('livewire.students.partials.avatar', ['student' => $form->student, 'size' => 'size-28 text-2xl'])
                    @else
                        <span class="flex size-28 items-center justify-center rounded-full bg-surface-2 text-ink-400"><x-ui.icon name="student" class="size-10" /></span>
                    @endif
                    <label for="student-photo" class="cursor-pointer rounded-[10px] px-3 py-1.5 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-within:ring-4 focus-within:ring-primary-200">
                        {{ __('students.fields.photo_choose') }}
                        <input id="student-photo" type="file" wire:model="form.photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    </label>
                    <span wire:loading wire:target="form.photo" class="text-xs text-ink-500">{{ __('access.reset.submitting') }}</span>
                    @if ($form->student?->hasPhoto() && ! $form->photo)
                        <label class="flex items-center gap-1.5 text-xs text-ink-700">
                            <input type="checkbox" wire:model.live="form.remove_photo" class="size-4 accent-primary-600">{{ __('students.fields.photo_remove') }}
                        </label>
                    @endif
                    <p class="text-center text-[11px] text-ink-500">{{ __('students.fields.photo_hint') }}</p>
                    @error('form.photo')
                        <p class="text-center text-[13px] font-medium text-danger-700">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid flex-1 gap-4 md:grid-cols-3">
                    <x-ui.input name="form.first_name" :label="__('students.fields.first_name')" wire:model="form.first_name" maxlength="80" class="uppercase" required />
                    <x-ui.input name="form.last_name" :label="__('students.fields.last_name')" wire:model="form.last_name" maxlength="80" class="uppercase" required />
                    <x-ui.input name="form.second_last_name" :label="__('students.fields.second_last_name')" wire:model="form.second_last_name" maxlength="80" class="uppercase" />

                    <x-ui.input name="form.enrollment_number" :label="__('students.fields.enrollment_number')" wire:model="form.enrollment_number" maxlength="30"
                                class="font-mono uppercase" :disabled="$editing"
                                :placeholder="$editing ? null : $this->nextNumber"
                                :hint="$editing ? __('students.fields.enrollment_number_locked') : __('students.fields.enrollment_number_hint', ['next' => $this->nextNumber])" />
                    <x-ui.input name="form.birth_date" type="date" :label="__('students.fields.birth_date')" wire:model="form.birth_date" />
                    <x-ui.input name="form.mobile_phone" type="tel" :label="__('students.fields.mobile_phone')" wire:model="form.mobile_phone" inputmode="tel" maxlength="20" />
                    <x-ui.input name="form.landline_phone" type="tel" :label="__('students.fields.landline_phone')" wire:model="form.landline_phone" inputmode="tel" maxlength="20" />

                    <div class="md:col-span-2">
                        <x-ui.input name="form.email" type="email" :label="__('students.fields.email')" wire:model="form.email" inputmode="email" maxlength="255"
                                    :hint="__($form->student?->user_id ? 'students.fields.email_hint_user' : 'students.fields.email_hint')" />
                    </div>
                    @unless ($editing)
                        <x-ui.select name="form.status" :label="__('students.fields.status')" :options="$statuses" wire:model="form.status" required />
                    @endunless

                    <x-ui.select name="form.student_type_id" :label="__('students.fields.student_type_id')" :options="$this->typeOptions"
                                 :placeholder="__('students.select')" wire:model="form.student_type_id" />
                    <x-ui.select name="form.information_source_id" :label="__('students.fields.information_source_id')" :options="$this->sourceOptions"
                                 :placeholder="__('students.select')" wire:model="form.information_source_id" />
                </div>
            </div>

            {{-- Fechas del curso (el fin del curso se calcula) --}}
            <div class="grid gap-4 border-t border-line pt-4 md:grid-cols-3">
                <x-ui.input name="form.course_starts_on" type="date" :label="__('students.fields.course_starts_on')" :hint="__('students.fields.course_starts_on_hint')" wire:model="form.course_starts_on" />
                <x-ui.input name="form.withdrawn_on" type="date" :label="__('students.fields.withdrawn_on')" wire:model="form.withdrawn_on" />
                <x-ui.input name="form.graduated_on" type="date" :label="__('students.fields.graduated_on')" wire:model="form.graduated_on" />
            </div>

            {{-- Domicilio --}}
            <fieldset class="flex flex-col gap-4 border-t border-line pt-4">
                <legend class="sr-only">{{ __('students.fields.address') }}</legend>
                @if ($form->student?->address_needs_review)
                    <p role="status" class="rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
                        {{ __('students.fields.address_review', ['address' => $form->student->address]) }}
                    </p>
                @endif
                <div class="grid gap-4 md:grid-cols-6">
                    <div class="md:col-span-3"><x-ui.input name="form.street" :label="__('students.fields.street')" wire:model="form.street" maxlength="150" class="uppercase" autocomplete="address-line1" /></div>
                    <div class="md:col-span-1"><x-ui.input name="form.exterior_number" :label="__('students.fields.exterior_number')" wire:model="form.exterior_number" maxlength="20" class="uppercase" /></div>
                    <div class="md:col-span-1"><x-ui.input name="form.interior_number" :label="__('students.fields.interior_number')" wire:model="form.interior_number" maxlength="20" class="uppercase" /></div>
                    <div class="md:col-span-1"><x-ui.input name="form.postal_code" :label="__('students.fields.postal_code')" wire:model="form.postal_code" inputmode="numeric" maxlength="5" autocomplete="postal-code" /></div>
                    <div class="md:col-span-2"><x-ui.input name="form.neighborhood" :label="__('students.fields.neighborhood')" wire:model="form.neighborhood" maxlength="120" class="uppercase" /></div>
                    <div class="md:col-span-2">
                        <x-ui.select name="form.state_id" :label="__('students.fields.state_id')" :options="$this->states"
                                     :placeholder="__('schools.fields.select_state')" wire:model.live="form.state_id" />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.select name="form.municipality_id" :label="__('students.fields.municipality_id')" :options="$this->municipalities"
                                     :placeholder="__($form->state_id ? 'schools.fields.select_municipality' : 'schools.fields.select_state_first')"
                                     wire:model="form.municipality_id" wire:key="student-municipalities-{{ $form->state_id }}" :disabled="! $form->state_id" />
                    </div>
                </div>
            </fieldset>


            <x-ui.textarea name="form.notes" :label="__('students.fields.notes')" wire:model="form.notes" rows="2" maxlength="2000" />
        </x-ui.card>

        {{-- Primera inscripción (solo en el alta) --}}
        @unless ($editing)
            <x-ui.card :title="__('students.sections.enrollment')" :description="__('students.sections.enrollment_hint')">
                @include('livewire.students.partials.enrollment-fields')
            </x-ui.card>
        @endunless

        {{-- Encuesta. "¿Modo de aprendizaje?" va al inicio de Contexto académico y "Acepta aparecer en publicidad" en Comunicación. --}}
        @php($sectionKey = fn (string $section) => \Illuminate\Support\Str::of(\Illuminate\Support\Str::ascii($section))->lower()->toString())
        @php($learningSection = $this->surveySections->keys()->first(fn ($section) => str_contains($sectionKey($section), 'academic')))
        @php($contactSection = $this->surveySections->keys()->first(fn ($section) => str_contains($sectionKey($section), 'comunicacion')))
        <x-ui.card :title="__('students.sections.survey')" :description="__('students.sections.survey_hint')">
            <div class="flex flex-col gap-6">
                @if ($learningSection === null || $contactSection === null)
                    <fieldset class="flex flex-col gap-4">
                        @if ($learningSection === null) @include('livewire.students.partials.survey-learning-style') @endif
                        @if ($contactSection === null) @include('livewire.students.partials.survey-advertising-consent') @endif
                    </fieldset>
                @endif
                @foreach ($this->surveySections as $section => $questions)
                        <fieldset class="flex flex-col gap-4" wire:key="survey-section-{{ $loop->index }}">
                            <legend class="mb-1 text-sm font-bold uppercase tracking-wide text-ink-500">{{ $section }}</legend>
                            @if ($section === $learningSection) @include('livewire.students.partials.survey-learning-style') @endif
                            <div class="grid gap-4 md:grid-cols-2">
                                @foreach ($questions as $question)
                                    @php($current = $form->answers[$question->id] ?? '')
                                    <div wire:key="question-{{ $question->id }}">
                                        @if ($question->type === \App\Enums\SurveyQuestionType::Choice)
                                            @php($options = collect($question->options ?? [])->mapWithKeys(fn ($option) => [$option => $option])->all())
                                            @if ($current !== '' && ! array_key_exists($current, $options))
                                                @php($options[$current] = $current.' '.__('students.survey_imported'))
                                            @endif
                                            <x-ui.select name="form.answers.{{ $question->id }}" :label="$question->question" :options="$options"
                                                         :placeholder="__('students.survey_no_answer')" wire:model="form.answers.{{ $question->id }}" />
                                        @else
                                            <x-ui.input name="form.answers.{{ $question->id }}" :label="$question->question" wire:model="form.answers.{{ $question->id }}" maxlength="500" />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($section === $contactSection) @include('livewire.students.partials.survey-advertising-consent') @endif
                        </fieldset>
                @endforeach
            </div>
        </x-ui.card>

        <div class="sticky bottom-20 z-20 -mx-4 flex flex-col-reverse gap-2.5 border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:flex-row md:justify-end md:border-0 md:bg-transparent md:p-0">
            <a href="{{ $editing ? route('students.show', $form->student) : route('students.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-5 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('students.actions.cancel') }}
            </a>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('students.actions.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
