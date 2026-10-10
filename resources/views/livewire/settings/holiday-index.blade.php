<div class="flex flex-col gap-6">
    @unless ($embedded)
    <x-ui.page-header :title="__('settings.holidays.title')" :subtitle="__('settings.holidays.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.holidays.add') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>
    @else
        <div class="flex justify-end">
            <x-ui.button variant="secondary" wire:click="edit"><x-ui.icon name="plus" class="size-4" />{{ __('settings.holidays.add') }}</x-ui.button>
        </div>
    @endunless

    @include('livewire.settings.partials.base-updates')

    <section class="max-w-4xl rounded-xl border border-line bg-white shadow-xs">
        {{-- Selector de año --}}
        <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 md:px-5">
            @php($arrow = 'flex size-10 items-center justify-center rounded-[10px] text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
            <button type="button" wire:click="changeYear(-1)" class="{{ $arrow }}" aria-label="{{ __('settings.holidays.previous_year') }}">
                <x-ui.icon name="chevron-down" class="size-5 rotate-90" />
            </button>
            <div class="flex flex-col items-center">
                <span class="font-display text-h3 font-bold tabular-nums" aria-live="polite">{{ $year }}</span>
                @if ($schoolId !== null)
                    <span class="text-xs text-ink-500">{{ trans_choice('settings.holidays.official_count', $officialCount) }}</span>
                @endif
            </div>
            <button type="button" wire:click="changeYear(1)" class="{{ $arrow }}" aria-label="{{ __('settings.holidays.next_year') }}">
                <x-ui.icon name="chevron-down" class="size-5 -rotate-90" />
            </button>
        </div>

        @if ($this->holidays->isEmpty())
            <x-ui.empty-state icon="calendar" :title="__('settings.holidays.empty')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->holidays as $holiday)
                    <li wire:key="holiday-{{ $holiday->id }}" @class(['flex items-center gap-3 px-4 py-3 md:px-5', 'opacity-60' => ! $holiday->is_active])>
                        <div class="flex w-16 flex-none flex-col items-center rounded-lg bg-surface py-1.5">
                            <span class="text-[11px] font-bold uppercase text-ink-500">{{ $holiday->starts_on->translatedFormat('M') }}</span>
                            <span class="font-display text-lg font-extrabold leading-none tabular-nums">{{ $holiday->starts_on->format('j') }}</span>
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span class="font-semibold">{{ $holiday->name }}</span>
                            <span class="text-xs text-ink-500">
                                @if ($holiday->days() > 1)
                                    {{ __('settings.holidays.range', [
                                        'from' => $holiday->starts_on->translatedFormat('j M'),
                                        'to' => $holiday->ends_on->translatedFormat('j M Y'),
                                        'days' => $holiday->days(),
                                    ]) }}
                                @else
                                    {{ \Illuminate\Support\Str::ucfirst($holiday->starts_on->translatedFormat('l j \d\e F')) }}
                                @endif
                            </span>
                        </div>
                        <x-ui.badge :variant="$holiday->isOfficial() ? 'info' : 'neutral'" class="hidden sm:inline-flex">{{ $holiday->type->label() }}</x-ui.badge>
                        @if ($holiday->isOfficial())
                            <button type="button" role="switch" aria-checked="{{ $holiday->is_active ? 'true' : 'false' }}" wire:click="toggle({{ $holiday->id }})"
                                    aria-label="{{ __($holiday->is_active ? 'catalogs.actions.deactivate' : 'catalogs.actions.activate', ['name' => $holiday->name]) }}"
                                    class="relative mx-1 inline-flex h-6 w-11 flex-none items-center rounded-full transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200 {{ $holiday->is_active ? 'bg-primary-600' : 'bg-line-strong' }}">
                                <span class="absolute left-0.5 size-5 rounded-full bg-white shadow-xs transition {{ $holiday->is_active ? 'translate-x-5' : '' }}"></span>
                            </button>
                        @else
                            <x-catalog.row-actions :active="$holiday->is_active" :name="$holiday->name"
                                                   edit="edit({{ $holiday->id }})" toggle="toggle({{ $holiday->id }})" delete="confirmDeletion({{ $holiday->id }})" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <p class="border-t border-line px-4 py-2.5 text-xs text-ink-500 md:px-5">{{ __('settings.holidays.legend') }}</p>
    </section>

    <x-ui.modal wire:model="editing" :title="__($holidayId ? 'settings.holidays.edit' : 'settings.holidays.add')">
        <x-ui.input name="name" :label="__('settings.fields.name')" :hint="__('settings.holidays.name_hint')" wire:model="name" maxlength="120" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="startsOn" type="date" :label="__('settings.fields.starts_on')" wire:model="startsOn" required />
            <x-ui.input name="endsOn" type="date" :label="__('settings.fields.ends_on')" :hint="__('settings.holidays.ends_hint')" wire:model="endsOn" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('settings.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('settings.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
