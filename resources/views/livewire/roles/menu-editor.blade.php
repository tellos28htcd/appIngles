<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('roles.title')" :subtitle="__('roles.menu.subtitle')" />

    @include('livewire.roles.partials.tabs')

    <form wire:submit="save" class="flex max-w-3xl flex-col gap-4" novalidate>
        @php($arrow = 'flex size-9 items-center justify-center rounded-[10px] text-ink-700 hover:bg-surface-2 disabled:opacity-30 disabled:hover:bg-transparent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
        <ol class="flex flex-col gap-3">
            @foreach ($order['root'] ?? [] as $moduleId)
                @php($module = $items[$moduleId])
                <li wire:key="menu-module-{{ $moduleId }}" class="rounded-xl border border-line bg-white shadow-xs">
                    <div class="flex items-center gap-3 p-3">
                        <x-ui.icon :name="$module->icon" class="text-ink-500" />
                        <label for="label-{{ $moduleId }}" class="sr-only">{{ __('roles.menu.label') }}</label>
                        <input id="label-{{ $moduleId }}" type="text" wire:model="labels.{{ $moduleId }}" maxlength="100"
                               class="h-11 min-w-0 flex-1 rounded-md border-[1.5px] border-line-strong bg-white px-3.5 font-bold focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                        @if ($module->isComingSoon())
                            <span class="hidden rounded-full bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-500 sm:inline">{{ __('layout.coming_soon') }}</span>
                        @endif
                        <button type="button" wire:click="move('{{ $moduleId }}', -1)" @disabled($loop->first) class="{{ $arrow }}" aria-label="{{ __('roles.menu.up', ['name' => $labels[$moduleId]]) }}">
                            <x-ui.icon name="chevron-down" class="size-4 rotate-180" />
                        </button>
                        <button type="button" wire:click="move('{{ $moduleId }}', 1)" @disabled($loop->last) class="{{ $arrow }}" aria-label="{{ __('roles.menu.down', ['name' => $labels[$moduleId]]) }}">
                            <x-ui.icon name="chevron-down" class="size-4" />
                        </button>
                    </div>
                    @error("labels.$moduleId")
                        <p class="px-4 pb-2 text-[13px] font-medium text-danger-700">{{ $message }}</p>
                    @enderror

                    @if (! empty($order[$moduleId]))
                        <ol class="flex flex-col gap-1 border-t border-line py-2 pl-10 pr-3">
                            @foreach ($order[$moduleId] as $childId)
                                <li wire:key="menu-item-{{ $childId }}" class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <label for="label-{{ $childId }}" class="sr-only">{{ __('roles.menu.label') }}</label>
                                        <input id="label-{{ $childId }}" type="text" wire:model="labels.{{ $childId }}" maxlength="100"
                                               class="h-10 min-w-0 flex-1 rounded-[10px] border-[1.5px] border-line bg-white px-3 text-[15px] focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                                        @if ($items[$childId]->isComingSoon())
                                            <span class="hidden rounded-full bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-500 sm:inline">{{ __('layout.coming_soon') }}</span>
                                        @endif
                                        <button type="button" wire:click="move('{{ $childId }}', -1)" @disabled($loop->first) class="{{ $arrow }}" aria-label="{{ __('roles.menu.up', ['name' => $labels[$childId]]) }}">
                                            <x-ui.icon name="chevron-down" class="size-4 rotate-180" />
                                        </button>
                                        <button type="button" wire:click="move('{{ $childId }}', 1)" @disabled($loop->last) class="{{ $arrow }}" aria-label="{{ __('roles.menu.down', ['name' => $labels[$childId]]) }}">
                                            <x-ui.icon name="chevron-down" class="size-4" />
                                        </button>
                                    </div>
                                    @error("labels.$childId")
                                        <p class="pt-1 text-[13px] font-medium text-danger-700">{{ $message }}</p>
                                    @enderror
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="sticky bottom-20 z-20 -mx-4 flex justify-end border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0">
            <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('roles.menu.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
