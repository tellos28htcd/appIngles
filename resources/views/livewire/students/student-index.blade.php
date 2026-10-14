<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('students.title')" :subtitle="__('students.subtitle')">
        <x-slot:actions>
            @can('create', \App\Models\Student::class)
                <a href="{{ route('students.import') }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-md border-[1.5px] border-line-strong bg-white px-5 text-[15px] font-bold text-ink-900 transition hover:bg-surface focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <x-ui.icon name="download" class="size-4 rotate-180" /> {{ __('students.import.title') }}
                </a>
                <a href="{{ route('students.create') }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <x-ui.icon name="plus" class="size-4" /> {{ __('students.new') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Filtros --}}
        <div class="flex flex-col gap-3 border-b border-line p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="students-search" class="sr-only">{{ __('students.search') }}</label>
                <input id="students-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('students.search') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
                <x-ui.select name="status" :label="__('students.fields.status')" :options="$statusOptions" :placeholder="__('students.filters.all_statuses')" wire:model.live="status" sr-only-label />
                <x-ui.select name="advisor" :label="__('students.enrollment.fields.sales_advisor_id')" :options="$advisorOptions" :placeholder="__('students.filters.all_advisors')" wire:model.live="advisor" sr-only-label />
                <x-ui.select name="type" :label="__('students.fields.student_type_id')" :options="$typeOptions" :placeholder="__('students.filters.all_types')" wire:model.live="type" sr-only-label />
                <div class="flex items-center gap-2">
                    <label for="students-from" class="w-14 flex-none text-sm font-semibold text-ink-700">{{ __('students.filters.from') }}</label>
                    <input id="students-from" type="date" wire:model.live="from" title="{{ __('students.filters.registered_hint') }}"
                           class="h-11 w-full min-w-0 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                </div>
                <div class="flex items-center gap-2">
                    <label for="students-to" class="w-14 flex-none text-sm font-semibold text-ink-700">{{ __('students.filters.to') }}</label>
                    <input id="students-to" type="date" wire:model.live="to" title="{{ __('students.filters.registered_hint') }}"
                           class="h-11 w-full min-w-0 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                </div>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                            class="h-11 rounded-md px-4 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                        {{ __('students.filters.clear') }}
                    </button>
                @endif
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->students->isEmpty())
                <x-ui.empty-state icon="student" :title="__($hasFilters ? 'students.no_results' : 'students.empty')" :description="__($hasFilters ? 'students.no_results_hint' : 'students.empty_hint')">
                    @if ($hasFilters)
                        <x-ui.button variant="secondary" wire:click="clearFilters">{{ __('students.filters.clear') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($this->students as $student)
                        <li wire:key="student-{{ $student->id }}" class="flex flex-col gap-3 p-4 hover:bg-surface md:flex-row md:items-center md:gap-4 md:px-5">
                            <a href="{{ route('students.show', $student) }}" wire:navigate class="flex min-w-0 flex-1 items-center gap-3 rounded-md focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                                @include('livewire.students.partials.avatar', ['student' => $student, 'size' => 'size-11'])
                                <div class="flex min-w-0 flex-col">
                                    <span class="font-bold leading-snug [overflow-wrap:anywhere]">{{ $student->fullName() }}</span>
                                    <span class="font-mono text-xs text-ink-500">{{ $student->enrollment_number }}</span>
                                    <span class="truncate text-sm text-ink-500">{{ $student->email ?? __('students.no_email') }}</span>
                                </div>
                            </a>
                            <div class="flex flex-col text-sm md:w-56 md:flex-none">
                                <span class="font-semibold">{{ $student->type?->name ?? '—' }}</span>
                                <span class="truncate text-xs text-ink-500">{{ $student->latestEnrollment?->salesAdvisor?->name }}</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 md:w-52 md:flex-none">
                                <x-ui.badge :variant="$student->status->badge()">{{ $student->status->label() }}</x-ui.badge>
                                @if ($student->user === null)
                                    <x-ui.badge variant="neutral">{{ __('students.no_user') }}</x-ui.badge>
                                @elseif (! $student->user->hasPassword())
                                    <x-ui.badge variant="info">{{ __('students.access_pending') }}</x-ui.badge>
                                @endif
                            </div>
                            <div class="flex items-center justify-end gap-0.5 md:flex-none">
                                @php($button = 'flex size-9 flex-none items-center justify-center rounded-[10px] transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
                                <a href="{{ route('students.show', $student) }}" wire:navigate class="{{ $button }} text-ink-700 hover:bg-surface-2"
                                   aria-label="{{ __('students.actions.view', ['name' => $student->fullName()]) }}" title="{{ __('students.actions.view_short') }}">
                                    <x-ui.icon name="eye" class="size-4" />
                                </a>
                                @can('update', $student)
                                    <a href="{{ route('students.edit', $student) }}" wire:navigate class="{{ $button }} text-primary-700 hover:bg-primary-50"
                                       aria-label="{{ __('students.actions.edit', ['name' => $student->fullName()]) }}" title="{{ __('students.actions.edit_short') }}">
                                        <x-ui.icon name="pencil" class="size-4" />
                                    </a>
                                @endcan
                                @can('sendAccess', $student)
                                    <button type="button" wire:click="sendAccess({{ $student->id }})" wire:loading.attr="disabled" wire:target="sendAccess({{ $student->id }})"
                                            class="{{ $button }} text-ink-700 hover:bg-surface-2"
                                            aria-label="{{ __('students.actions.send_access_named', ['name' => $student->fullName()]) }}"
                                            title="{{ __($student->user->hasPassword() ? 'students.actions.send_reset' : 'students.actions.send_access') }}">
                                        <x-ui.icon name="mail" class="size-4" />
                                    </button>
                                @endcan
                                @can('delete', $student)
                                    <button type="button" wire:click="confirmDeletion({{ $student->id }})" class="{{ $button }} text-danger-700 hover:bg-danger-50"
                                            aria-label="{{ __('students.actions.delete', ['name' => $student->fullName()]) }}" title="{{ __('students.actions.delete_short') }}">
                                        <x-ui.icon name="trash" class="size-4" />
                                    </button>
                                @endcan
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-col gap-3 border-t border-line p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <label for="students-per-page" class="text-sm text-ink-700">{{ __('students.per_page') }}</label>
                <select id="students-per-page" wire:model.live="perPage"
                        class="h-10 rounded-[10px] border-[1.5px] border-line-strong bg-white px-2.5 text-sm tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                    @foreach (\App\Livewire\Students\StudentIndex::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                <span class="text-sm tabular-nums text-ink-500">{{ trans_choice('students.total', $this->students->total()) }}</span>
            </div>
            <div class="flex-1 sm:flex sm:justify-end">{{ $this->students->links('partials.pagination') }}</div>
        </div>
    </section>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('students.confirm_delete.title', ['name' => $this->pendingDeletion?->fullName()])">
        <p>{{ __('students.confirm_delete.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('students.confirm_delete.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
