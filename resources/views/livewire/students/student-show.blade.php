@php($student = $this->student)
@php($date = fn ($value) => $value?->translatedFormat('j M Y') ?? '—')
<div class="flex flex-col gap-6">
    {{-- Encabezado --}}
    <header class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex min-w-0 items-center gap-4">
            @include('livewire.students.partials.avatar', ['student' => $student, 'size' => 'size-16 text-xl'])
            <div class="flex min-w-0 flex-col gap-1">
                <h1 class="text-[26px] font-extrabold leading-tight tracking-tight [overflow-wrap:anywhere] md:text-[28px]">{{ $student->fullName() }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-sm font-bold text-ink-700">{{ $student->enrollment_number }}</span>
                    <x-ui.badge :variant="$student->status->badge()">{{ $student->status->label() }}</x-ui.badge>
                    @if ($student->user === null)
                        <x-ui.badge variant="neutral">{{ __('students.no_user') }}</x-ui.badge>
                    @elseif (! $student->user->hasPassword())
                        <x-ui.badge variant="info">{{ __('students.access_pending') }}</x-ui.badge>
                    @endif
                </div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('students.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-4 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('students.actions.back') }}
            </a>
            @can('sendAccess', $student)
                <x-ui.button variant="outline" wire:click="sendAccess" wire:loading.attr="disabled" wire:target="sendAccess">
                    <x-ui.icon name="mail" class="size-4" />{{ __($student->user->hasPassword() ? 'students.actions.send_reset' : 'students.actions.send_access') }}
                </x-ui.button>
            @endcan
            @if ($canUpdate)
                <x-ui.button variant="secondary" wire:click="askStatusChange">{{ __('students.actions.change_status') }}</x-ui.button>
                <a href="{{ route('students.edit', $student) }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <x-ui.icon name="pencil" class="size-4" />{{ __('students.actions.edit_short') }}
                </a>
            @endif
        </div>
    </header>

    <div class="grid gap-4 md:gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="flex min-w-0 flex-col gap-4 md:gap-6">
            {{-- Datos generales --}}
            <x-ui.card :title="__('students.sections.general')">
                <dl class="grid gap-x-6 gap-y-3 text-[15px] sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'email' => $student->email ?? __('students.no_email'),
                        'mobile_phone' => $student->mobile_phone ?? '—',
                        'landline_phone' => $student->landline_phone ?? '—',
                        'birth_date' => $student->birth_date ? $date($student->birth_date).' · '.trans_choice('students.age', $student->age()) : '—',
                        'student_type_id' => $student->type?->name ?? '—',
                        'learning_style' => collect(['visual', 'auditory', 'kinesthetic'])->filter(fn ($style) => $student->{'learns_'.$style})->map(fn ($style) => __("students.fields.learns_$style"))->implode(', ') ?: '—',
                        'course_starts_on' => $date($student->course_starts_on),
                        'course_ends_on' => $date($student->courseEndsOn()).($student->openMonthly() ? ' · '.__('students.term.estimated') : ''),
                        'extension' => trans_choice('students.fields.extension_days', $student->extensionDays()),
                        'withdrawn_on' => $date($student->withdrawn_on),
                        'graduated_on' => $date($student->graduated_on),
                        'absence_days' => __('students.fields.absence_days_value', ['cycle' => $student->absenceDaysInCycle(), 'max' => \App\Models\Student::MAX_ABSENCE_DAYS, 'total' => $student->absenceDaysTotal()]),
                    ] as $field => $value)
                        <div class="flex min-w-0 flex-col">
                            <dt class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __("students.fields.$field") }}</dt>
                            <dd class="[overflow-wrap:anywhere]">{{ $value }}</dd>
                        </div>
                    @endforeach
                    <div class="flex min-w-0 flex-col sm:col-span-2">
                        <dt class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('students.fields.address') }}</dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{ $student->formattedAddress() ?? '—' }}
                            @if ($student->address_needs_review)
                                <x-ui.badge variant="warning" class="ml-1">{{ __('students.address_review_badge') }}</x-ui.badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <dt class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('students.fields.advertising_consent') }}</dt>
                        <dd>{{ __($student->advertising_consent ? 'audit.yes' : 'audit.no') }}</dd>
                    </div>
                    @if ($student->notes)
                        <div class="flex min-w-0 flex-col sm:col-span-2 lg:col-span-3">
                            <dt class="text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('students.fields.notes') }}</dt>
                            <dd class="whitespace-pre-line [overflow-wrap:anywhere]">{{ $student->notes }}</dd>
                        </div>
                    @endif
                </dl>
            </x-ui.card>

            {{-- Inscripciones --}}
            <x-ui.card :title="__('students.sections.enrollments')">
                @if ($canUpdate)
                    <div class="-mt-2 flex justify-end">
                        <x-ui.button variant="secondary" size="sm" wire:click="editEnrollment"><x-ui.icon name="plus" class="size-4" />{{ __('students.enrollment.add') }}</x-ui.button>
                    </div>
                @endif
                @forelse ($student->enrollments as $record)
                    <article wire:key="enrollment-{{ $record->id }}" class="flex flex-col gap-3 rounded-lg border border-line p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex flex-col">
                                <span class="font-bold">{{ $record->auditLabel() }}</span>
                                <span class="text-sm text-ink-500">{{ __('students.enrollment.registered', ['date' => $date($record->registered_on)]) }}</span>
                            </div>
                            @if ($canUpdate)
                                <button type="button" wire:click="editEnrollment({{ $record->id }})"
                                        class="flex size-9 items-center justify-center rounded-[10px] text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                                        aria-label="{{ __('students.enrollment.edit', ['label' => $record->auditLabel()]) }}" title="{{ __('students.actions.edit_short') }}">
                                    <x-ui.icon name="pencil" class="size-4" />
                                </button>
                            @endif
                        </div>
                        <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                'sales_advisor_id' => $record->salesAdvisor?->name ?? '—',
                                'commission_applies' => __($record->commission_applies ? 'audit.yes' : 'audit.no'),
                                'information_source_id' => $student->informationSource?->name ?? '—',
                                'student_plan_id' => $record->plan ? $record->plan->name.' · '.trans_choice('students.enrollment.installments_count', $record->installments ?? $record->plan->installments) : '—',
                                'start_point' => collect([$record->startBook?->name, $record->startLesson?->name, $record->startActivity?->code])->filter()->implode(' · ') ?: '—',
                                'reenrolled_on' => $date($record->reenrolled_on),
                                'services' => collect($record->services())->map(fn ($service) => __("students.enrollment.services.$service"))->implode(', ') ?: '—',
                                'material_assigned' => $record->material_assigned
                                    ? collect([$record->material_serial, $record->material_type ? __('students.enrollment.material_types.'.$record->material_type) : null])->filter()->implode(' · ') ?: __('audit.yes')
                                    : __('audit.no'),
                            ] as $field => $value)
                                <div class="flex min-w-0 flex-col">
                                    <dt class="text-xs font-semibold text-ink-500">{{ __("students.enrollment.fields.$field") }}</dt>
                                    <dd class="[overflow-wrap:anywhere]">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </article>
                @empty
                    <p class="text-sm text-ink-500">{{ __('students.enrollment.empty') }}</p>
                @endforelse
            </x-ui.card>

            {{-- Encuesta --}}
            <x-ui.card :title="__('students.sections.survey')">
                @if ($student->surveyAnswers->isEmpty())
                    <p class="text-sm text-ink-500">{{ __('students.survey_empty') }}</p>
                @else
                    <dl class="grid gap-x-6 gap-y-3 text-sm md:grid-cols-2">
                        @foreach ($student->surveyAnswers->sortBy(fn ($answer) => $answer->question?->sort_order) as $answer)
                            <div class="flex min-w-0 flex-col" wire:key="answer-{{ $answer->id }}">
                                <dt class="text-ink-500">{{ $answer->question?->question }}</dt>
                                <dd class="font-semibold [overflow-wrap:anywhere]">{{ $answer->answer }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </x-ui.card>
        </div>

        <div class="flex min-w-0 flex-col gap-4 md:gap-6">
            {{-- Tutores --}}
            <x-ui.card :title="__('students.sections.guardians')">
                @forelse ($student->guardians as $guardian)
                    <div wire:key="guardian-{{ $guardian->id }}" class="flex items-start gap-3">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="font-bold [overflow-wrap:anywhere]">{{ $guardian->name }}</span>
                            <span class="text-sm text-ink-500">{{ collect([$guardian->pivot->relationship, $guardian->mobile_phone, $guardian->email])->filter()->implode(' · ') }}</span>
                            @if ($guardian->user)
                                <span class="text-xs font-semibold text-success-700">{{ __('students.guardians.has_access') }}</span>
                            @endif
                        </div>
                        @if ($canUpdate)
                            <button type="button" wire:click="unlinkGuardian({{ $guardian->id }})" wire:confirm="{{ __('students.guardians.confirm_unlink', ['name' => $guardian->name]) }}"
                                    class="flex size-9 flex-none items-center justify-center rounded-[10px] text-danger-700 hover:bg-danger-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                                    aria-label="{{ __('students.guardians.unlink', ['name' => $guardian->name]) }}" title="{{ __('students.guardians.unlink_short') }}">
                                <x-ui.icon name="close" class="size-4" />
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-500">{{ __('students.guardians.empty') }}</p>
                @endforelse
                @if ($canUpdate)
                    <x-ui.button variant="secondary" size="sm" wire:click="linkGuardian"><x-ui.icon name="plus" class="size-4" />{{ __('students.guardians.add') }}</x-ui.button>
                @endif
            </x-ui.card>

            {{-- Histórico de estatus --}}
            <x-ui.card :title="__('students.sections.history')">
                <ol class="flex flex-col gap-4 border-l-2 border-line pl-4">
                    @forelse ($student->statusChanges as $change)
                        <li wire:key="change-{{ $change->id }}" class="relative flex flex-col gap-1">
                            <span class="absolute -left-[23px] top-1.5 size-3 rounded-full border-2 border-white bg-primary-500" aria-hidden="true"></span>
                            <span class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :variant="$change->to_status->badge()">{{ $change->to_status->label() }}</x-ui.badge>
                                <span class="text-xs tabular-nums text-ink-500">{{ $date($change->changed_on) }}</span>
                            </span>
                            @if ($change->reason)
                                <p class="text-sm [overflow-wrap:anywhere]">{{ $change->reason }}</p>
                            @endif
                            <span class="text-xs text-ink-500">{{ $change->user?->name ?? __('audit.system_actor') }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-ink-500">{{ __('students.history_empty') }}</li>
                    @endforelse
                </ol>
            </x-ui.card>

            {{-- Plazo del curso --}}
            <x-ui.card :title="__('students.sections.term')"
                       :description="__('students.term.summary', ['start' => $date($student->course_starts_on), 'end' => $date($student->courseEndsOn())])">
                @if ($student->course_starts_on === null)
                    <p class="text-sm text-warning-700">{{ __('students.term.no_start') }}</p>
                @endif
                @forelse ($student->termMovements as $movement)
                    <div wire:key="movement-{{ $movement->id }}" class="flex items-start gap-3">
                        <div class="flex min-w-0 flex-1 flex-col gap-1">
                            <span class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :variant="$movement->type->badge()">{{ $movement->type->label() }}</x-ui.badge>
                                <span class="text-sm font-semibold tabular-nums text-primary-700">+{{ trans_choice('students.absences.days', $movement->days) }}</span>
                                @if ($movement->isOpen())
                                    <span class="text-xs font-semibold text-warning-700">{{ __('students.term.open') }}</span>
                                @elseif ($movement->closed_on)
                                    <span class="text-xs text-ink-500">{{ __('students.term.closed_on', ['date' => $date($movement->closed_on)]) }}</span>
                                @endif
                            </span>
                            <span class="text-xs tabular-nums text-ink-500">{{ $date($movement->occurred_on) }} · {{ $movement->user?->name ?? __('audit.system_actor') }}</span>
                            @if ($movement->reason)
                                <span class="text-sm text-ink-500 [overflow-wrap:anywhere]">{{ $movement->reason }}</span>
                            @endif
                        </div>
                        @if ($canUpdate && $movement->isOpen())
                            <x-ui.button variant="secondary" size="sm" wire:click="askCloseMovement({{ $movement->id }})">{{ __('students.term.close') }}</x-ui.button>
                        @endif
                        @if ($canUpdate && $movement->type->isManual())
                            <button type="button" wire:click="deleteMovement({{ $movement->id }})" wire:confirm="{{ __('students.term.confirm_delete') }}"
                                    class="flex size-9 flex-none items-center justify-center rounded-[10px] text-danger-700 hover:bg-danger-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                                    aria-label="{{ __('students.term.delete', ['type' => $movement->type->label()]) }}" title="{{ __('students.term.delete_short') }}">
                                <x-ui.icon name="close" class="size-4" />
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-500">{{ __('students.term.empty') }}</p>
                @endforelse
                @if ($canUpdate)
                    <x-ui.button variant="secondary" size="sm" wire:click="addMovement"><x-ui.icon name="plus" class="size-4" />{{ __('students.term.add') }}</x-ui.button>
                @endif
            </x-ui.card>

            {{-- Ausencias --}}
            <x-ui.card :title="__('students.sections.absences')"
                       :description="__('students.fields.absence_days_value', ['cycle' => $student->absenceDaysInCycle(), 'max' => \App\Models\Student::MAX_ABSENCE_DAYS, 'total' => $student->absenceDaysTotal()])">
                @forelse ($student->absences as $absence)
                    <div wire:key="absence-{{ $absence->id }}" class="flex items-start gap-3">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold tabular-nums">{{ $date($absence->starts_on) }} – {{ $absence->ends_on ? $date($absence->ends_on) : __('students.absences.open') }}</span>
                            <span class="text-sm font-semibold text-primary-700">{{ trans_choice('students.absences.days', $absence->days()) }}</span>
                            @if ($absence->reason)
                                <span class="text-sm text-ink-500 [overflow-wrap:anywhere]">{{ $absence->reason }}</span>
                            @endif
                        </div>
                        <a href="{{ route('students.absence-print', [$student, $absence]) }}" target="_blank" rel="noopener"
                           class="flex size-9 flex-none items-center justify-center rounded-[10px] text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                           aria-label="{{ __('students.absences.print') }}" title="{{ __('students.absences.print') }}">
                            <x-ui.icon name="download" class="size-4" />
                        </a>
                        @if ($canUpdate)
                            <button type="button" wire:click="editAbsence({{ $absence->id }})"
                                    class="flex size-9 flex-none items-center justify-center rounded-[10px] text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200"
                                    aria-label="{{ __('students.absences.edit') }}" title="{{ __('students.actions.edit_short') }}">
                                <x-ui.icon name="pencil" class="size-4" />
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-ink-500">{{ __('students.absences.empty') }}</p>
                @endforelse
                @if ($canUpdate)
                    <x-ui.button variant="secondary" size="sm" wire:click="editAbsence"><x-ui.icon name="plus" class="size-4" />{{ __('students.absences.add') }}</x-ui.button>
                @endif
            </x-ui.card>
        </div>
    </div>

    @if ($canUpdate)
        {{-- Inscripción --}}
        <x-ui.modal wire:model="editingEnrollment" size="lg" :title="__($enrollment->enrollment ? 'students.enrollment.edit_title' : 'students.enrollment.add')">
            @include('livewire.students.partials.enrollment-fields')
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="saveEnrollment">{{ __('students.actions.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Cambio de estatus --}}
        <x-ui.modal wire:model="changingStatus" :title="__('students.status_change.title')">
            <p>{{ __('students.status_change.body') }}</p>
            <x-ui.select name="newStatus" :label="__('students.fields.status')" :options="$statuses" wire:model.live="newStatus" required />
            <x-ui.input name="statusDate" type="date" :label="__('students.status_change.date')" wire:model="statusDate" required />
            <x-ui.textarea name="statusReason" :label="__('students.status_change.reason')" wire:model="statusReason" rows="3" maxlength="1000"
                           :hint="__(\App\Enums\StudentStatus::tryFrom($newStatus)?->requiresReason() ? 'students.status_change.reason_required' : 'students.status_change.reason_optional')" />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="changeStatus">{{ __('students.status_change.confirm') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Ausencia --}}
        <x-ui.modal wire:model="addingAbsence" :title="__($absenceId ? 'students.absences.edit' : 'students.absences.add')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="absenceFrom" type="date" :label="__('students.absences.from')" wire:model="absenceFrom" required />
                <x-ui.input name="absenceTo" type="date" :label="__('students.absences.to')" :hint="__('students.absences.to_hint')" wire:model="absenceTo" />
            </div>
            <x-ui.input name="absenceReason" :label="__('students.absences.reason')" wire:model="absenceReason" maxlength="255" />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="saveAbsence">{{ __('students.actions.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Movimiento del plazo --}}
        <x-ui.modal wire:model="addingMovement" :title="__('students.term.add')">
            <x-ui.select name="movementType" :label="__('students.term.type')" :options="$movementTypes" wire:model.live="movementType" required />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input name="movementDate" type="date" :label="__('students.term.date')" wire:model="movementDate" required />
                <x-ui.input name="movementDays" type="number" :label="__('students.term.days')" wire:model="movementDays" min="1"
                            :max="\App\Enums\StudentTermMovementType::tryFrom($movementType)?->maxDays()" class="tabular-nums" required
                            :hint="$movementType ? __('students.term.hints.'.$movementType) : null" />
            </div>
            <x-ui.input name="movementReason" :label="__('students.term.reason')" wire:model="movementReason" maxlength="255" />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="saveMovement">{{ __('students.actions.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Cierre de mensualidad abierta --}}
        <x-ui.modal wire:model="closingMovementId" :title="__('students.term.close_title')">
            <p>{{ __('students.term.close_body') }}</p>
            <x-ui.input name="closeDate" type="date" :label="__('students.term.close_date')" wire:model="closeDate" required />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="closeMovement">{{ __('students.term.close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Tutor --}}
        <x-ui.modal wire:model="linkingGuardian" size="md" :title="__('students.guardians.add')">
            <x-ui.select name="guardianId" :label="__('students.guardians.existing')" :options="$this->guardianOptions"
                         :placeholder="__('students.guardians.new_guardian')" wire:model.live="guardianId" :hint="__('students.guardians.existing_hint')" />
            @if ($guardianId === '')
                <x-ui.input name="guardianName" :label="__('students.guardians.name')" wire:model="guardianName" maxlength="160" class="uppercase" required />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="guardianPhone" type="tel" :label="__('students.guardians.phone')" wire:model="guardianPhone" inputmode="tel" maxlength="20" />
                    <x-ui.input name="guardianEmail" type="email" :label="__('students.guardians.email')" wire:model="guardianEmail" inputmode="email" maxlength="255" />
                </div>
                <fieldset class="flex flex-col gap-1">
                    <legend class="mb-1 text-sm font-semibold text-ink-900">{{ __('students.guardians.receives') }}</legend>
                    @foreach ($reports as $value => $label)
                        <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-[10px] px-2 hover:bg-surface">
                            <input type="checkbox" value="{{ $value }}" wire:model="guardianReceives" class="size-[18px] rounded accent-primary-600">
                            <span class="text-[15px]">{{ $label }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @endif
            <x-ui.input name="guardianRelationship" :label="__('students.guardians.relationship')" :hint="__('students.guardians.relationship_hint')" wire:model="guardianRelationship" maxlength="40" />
            <x-slot:footer>
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="saveGuardian">{{ __('students.guardians.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
