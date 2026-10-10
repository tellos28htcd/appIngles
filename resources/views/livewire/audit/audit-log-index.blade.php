<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($platform ? 'audit.title_platform' : 'audit.title')" :subtitle="__($platform ? 'audit.subtitle_platform' : 'audit.subtitle')">
        <x-slot:actions>
            <x-ui.button variant="outline" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                <x-ui.icon name="download" class="size-[18px]" />
                {{ __('audit.actions.export') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Pestañas --}}
    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line" role="tablist" aria-label="{{ __('audit.title') }}">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                    aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        '-mb-px inline-flex h-11 flex-none items-center border-b-2 px-4 text-[15px] font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
                        'border-primary-600 text-primary-700' => $tab === $key,
                        'border-transparent text-ink-500 hover:text-ink-900' => $tab !== $key,
                    ])>{{ $label }}</button>
        @endforeach
    </div>

    <section id="panel-{{ $tab }}" role="tabpanel" aria-labelledby="tab-{{ $tab }}" class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Filtros --}}
        <div class="flex flex-col gap-3 border-b border-line p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="audit-search" class="sr-only">{{ __($isChanges ? 'audit.filters.search_changes' : 'audit.filters.search_logins') }}</label>
                <input id="audit-search" type="search" wire:model.live.debounce.400ms="search"
                       placeholder="{{ __($isChanges ? 'audit.filters.search_changes' : 'audit.filters.search_logins') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-[repeat(5,minmax(0,1fr))_auto]">
                @if ($platform)
                    <x-ui.select name="school" :label="__('audit.filters.school')" :options="$this->schoolOptions" :placeholder="__('audit.filters.all_schools')"
                                 wire:model.live="school" sr-only-label />
                @endif
                @if ($isChanges)
                    <x-ui.select name="module" :label="__('audit.filters.module')" :options="$moduleOptions" :placeholder="__('audit.filters.all_modules')"
                                 wire:model.live="module" sr-only-label />
                    <x-ui.select name="event" :label="__('audit.filters.event')" :options="$eventOptions" :placeholder="__('audit.filters.all_events')"
                                 wire:model.live="event" sr-only-label />
                @else
                    <x-ui.select name="event" :label="__('audit.filters.login_event')" :options="$eventOptions" :placeholder="__('audit.filters.all_login_events')"
                                 wire:model.live="event" sr-only-label />
                @endif
                <div class="flex items-center gap-2">
                    <label for="audit-from" class="w-14 flex-none text-sm font-semibold text-ink-700">{{ __('audit.filters.from') }}</label>
                    <input id="audit-from" type="date" wire:model.live="from" max="{{ $to ?: now($timezone)->toDateString() }}"
                           class="h-11 w-full min-w-0 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                </div>
                <div class="flex items-center gap-2">
                    <label for="audit-to" class="w-14 flex-none text-sm font-semibold text-ink-700">{{ __('audit.filters.to') }}</label>
                    <input id="audit-to" type="date" wire:model.live="to" min="{{ $from }}"
                           class="h-11 w-full min-w-0 rounded-md border-[1.5px] border-line-strong bg-white px-3 text-[15px] tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                </div>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                            class="h-11 rounded-md px-4 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                        {{ __('audit.filters.clear') }}
                    </button>
                @endif
            </div>
            <p class="text-xs text-ink-500">{{ __('audit.filters.default_range') }}</p>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($logs->isEmpty())
                <x-ui.empty-state icon="clipboard" :title="__('audit.empty')" :description="__('audit.empty_hint')">
                    @if ($hasFilters)
                        <x-ui.button variant="secondary" wire:click="clearFilters">{{ __('audit.filters.clear') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @elseif ($isChanges)
                @include('livewire.audit.partials.changes-table')
            @else
                @include('livewire.audit.partials.logins-table')
            @endif
        </div>

        <div class="flex flex-col gap-3 border-t border-line p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <label for="audit-per-page" class="text-sm text-ink-700">{{ __('audit.per_page') }}</label>
                <select id="audit-per-page" wire:model.live="perPage"
                        class="h-10 rounded-[10px] border-[1.5px] border-line-strong bg-white px-2.5 text-sm tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                    @foreach (\App\Livewire\Audit\AuditLogIndex::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 sm:flex sm:justify-end">{{ $logs->links('partials.pagination') }}</div>
        </div>
    </section>

    {{-- Detalle de un cambio --}}
    @php($detail = $this->viewingLog)
    <x-ui.modal wire:model="viewingId" size="md"
                :title="$detail ? __('audit.detail_title', ['event' => $detail->eventLabel(), 'module' => $detail->moduleLabel()]) : ''">
        @if ($detail)
            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                <dt class="font-semibold text-ink-900">{{ __('audit.columns.subject') }}</dt>
                <dd class="[overflow-wrap:anywhere]">{{ $detail->subject() }}</dd>
                <dt class="font-semibold text-ink-900">{{ __('audit.columns.date') }}</dt>
                <dd class="tabular-nums">{{ $detail->created_at->timezone($timezone)->translatedFormat('j M Y, H:i:s') }}</dd>
                <dt class="font-semibold text-ink-900">{{ __('audit.columns.user') }}</dt>
                <dd class="[overflow-wrap:anywhere]">{{ $detail->user ? "{$detail->user->name} · {$detail->user->email}" : __('audit.system_actor') }}</dd>
                @if ($platform)
                    <dt class="font-semibold text-ink-900">{{ __('audit.columns.school') }}</dt>
                    <dd>{{ $detail->school ? "{$detail->school->code} · {$detail->school->name}" : __('audit.platform') }}</dd>
                @endif
                <dt class="font-semibold text-ink-900">{{ __('audit.columns.ip') }}</dt>
                <dd class="font-mono text-xs leading-5">{{ $detail->ip_address ?? '—' }}</dd>
            </dl>

            @php($changes = $detail->changes())
            @if ($changes === [])
                <p class="text-sm text-ink-500">{{ __('audit.no_changes') }}</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-line">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-3 py-2 font-bold">{{ __('audit.columns.field') }}</th>
                                @if ($detail->event !== 'created')
                                    <th scope="col" class="px-3 py-2 font-bold">{{ __('audit.columns.old') }}</th>
                                @endif
                                @if ($detail->event !== 'deleted')
                                    <th scope="col" class="px-3 py-2 font-bold">{{ __('audit.columns.new') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($changes as $change)
                                <tr wire:key="change-{{ $detail->id }}-{{ $loop->index }}">
                                    <th scope="row" class="whitespace-nowrap px-3 py-2 align-top font-semibold text-ink-900">{{ $change['field'] }}</th>
                                    @if ($detail->event !== 'created')
                                        <td class="px-3 py-2 align-top text-ink-500 [overflow-wrap:anywhere]">{{ $change['old'] }}</td>
                                    @endif
                                    @if ($detail->event !== 'deleted')
                                        <td class="px-3 py-2 align-top text-ink-900 [overflow-wrap:anywhere]">{{ $change['new'] }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('audit.actions.close') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
