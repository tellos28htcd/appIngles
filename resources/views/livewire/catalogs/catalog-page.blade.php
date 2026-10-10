<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$pageTitle" :subtitle="$pageSubtitle" />

    {{-- Novedades del catálogo base (solo escuelas) --}}
    @if ($updatesCount > 0)
        <div role="status" class="flex flex-col gap-3 rounded-xl border border-accent-300 bg-accent-50 p-4 sm:flex-row sm:items-center">
            <span class="flex size-10 flex-none items-center justify-center rounded-full bg-accent-500 text-on-accent">
                <x-ui.icon name="sparkles" />
            </span>
            <div class="flex min-w-0 flex-1 flex-col">
                <span class="font-bold text-ink-900">{{ trans_choice('catalogs.updates.title', $updatesCount) }}</span>
                <span class="text-sm text-ink-700">{{ __('catalogs.updates.body') }}</span>
            </div>
            <x-ui.button variant="primary" wire:click="reviewUpdates">{{ __('catalogs.updates.review') }}</x-ui.button>
        </div>
    @endif

    {{-- Pestañas --}}
    <div class="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-line" role="tablist" aria-label="{{ $pageTitle }}">
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

    <div id="panel-{{ $tab }}" role="tabpanel" aria-labelledby="tab-{{ $tab }}">
        @switch($tab)
            @case('libros')
                <livewire:catalogs.books-tab :school-id="$schoolId" wire:key="books-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('actividades')
                <livewire:catalogs.activities-tab :school-id="$schoolId" wire:key="activities-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('clubes')
                <livewire:catalogs.clubs-tab :school-id="$schoolId" wire:key="clubs-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('salones')
                <livewire:settings.classroom-index :school-id="$schoolId" :embedded="true" wire:key="classrooms-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('metodos')
                <livewire:settings.payment-method-index :school-id="$schoolId" :embedded="true" wire:key="methods-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('conceptos')
                <livewire:settings.charge-concept-index :school-id="$schoolId" :embedded="true" wire:key="concepts-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @case('dias')
                <livewire:settings.holiday-index :school-id="$schoolId" :embedded="true" wire:key="holidays-{{ $schoolId ?? 'base' }}-{{ $version }}" />
                @break
            @default
                <livewire:catalogs.shifts-tab :school-id="$schoolId" wire:key="shifts-{{ $schoolId ?? 'base' }}-{{ $version }}" />
        @endswitch
    </div>

    {{-- Revisar e incorporar novedades --}}
    <x-ui.modal wire:model="reviewingUpdates" size="md" :title="__('catalogs.updates.modal_title')">
        <p>{{ __('catalogs.updates.modal_body') }}</p>
        @foreach ($this->pendingUpdates as $catalog => $items)
            <fieldset class="flex flex-col gap-1">
                <legend class="mb-1 text-sm font-bold text-ink-900">{{ __("catalogs.updates.catalogs.$catalog") }}</legend>
                @foreach ($items as $item)
                    <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-[10px] px-2 hover:bg-surface">
                        <input type="checkbox" value="{{ $item->id }}" wire:model="selectedUpdates.{{ $catalog }}" class="size-[18px] rounded accent-primary-600">
                        <span class="text-[15px]">
                            @switch($catalog)
                                @case('schedule_slots') {{ $item->number }} · {{ $item->range() }} @break
                                @case('lessons') {{ $item->name }} <span class="text-xs text-ink-500">({{ __('catalogs.fields.activity_number') }} {{ $item->number }})</span> @break
                                @case('activities') <span class="font-mono font-bold">{{ $item->code }}</span> · {{ $item->description }} @break
                                @default {{ $item->name }}
                            @endswitch
                        </span>
                    </label>
                @endforeach
            </fieldset>
        @endforeach
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="incorporateUpdates">{{ __('catalogs.updates.incorporate') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
