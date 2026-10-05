<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('menu.title')" :subtitle="__('menu.subtitle')">
        <x-slot:actions>
            <x-ui.button wire:click="openCreate">
                <x-ui.icon name="plus" class="size-4" /> {{ __('menu.new_module') }}
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex items-start gap-2.5 rounded-lg border border-info-200 bg-info-50 p-3.5 text-sm text-info-700">
        <x-ui.icon name="alert" class="mt-px size-[18px]" />
        <p>{{ __('menu.help') }}</p>
    </div>

    <form wire:submit="save" class="flex max-w-4xl flex-col gap-4" novalidate>
        <ol class="flex flex-col gap-3">
            @foreach ($order['root'] ?? [] as $moduleId)
                @continue(! isset($this->items[$moduleId]))
                @php($module = $this->items[$moduleId])
                <li wire:key="menu-module-{{ $moduleId }}" class="rounded-xl border border-line bg-white shadow-xs">
                    <div class="p-3">
                        @include('livewire.menu.partials.row', ['item' => $module, 'id' => $moduleId, 'nested' => false])
                    </div>

                    @if (! empty($order[$moduleId]) || $module->route_name === null)
                    <div class="flex flex-col gap-1 border-t border-line py-2 pl-6 pr-3 sm:pl-10">
                        @if (! empty($order[$moduleId]))
                            <ol class="flex flex-col gap-1.5">
                                @foreach ($order[$moduleId] as $childId)
                                    @continue(! isset($this->items[$childId]))
                                    <li wire:key="menu-item-{{ $childId }}">
                                        @include('livewire.menu.partials.row', ['item' => $this->items[$childId], 'id' => $childId, 'nested' => true])
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                        @if ($module->route_name === null)
                        <button type="button" wire:click="openCreate({{ $moduleId }})"
                                class="mt-1 inline-flex h-9 items-center gap-1.5 self-start rounded-[10px] px-2.5 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                            <x-ui.icon name="plus" class="size-4" />{{ __('menu.new_submodule') }}
                        </button>
                        @endif
                    </div>
                    @endif
                </li>
            @endforeach
        </ol>

        <div class="sticky bottom-20 z-20 -mx-4 flex justify-end border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:border-0 md:bg-transparent md:p-0">
            <x-ui.button type="submit" class="w-full md:w-auto" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('menu.save') }}
            </x-ui.button>
        </div>
    </form>

    {{-- Alta de módulo o submódulo --}}
    <x-ui.modal wire:model="creating" size="md"
                :title="$newParentId ? __('menu.create_submodule_title', ['parent' => $parentLabel]) : __('menu.create_module_title')">
        <div class="flex flex-col gap-4">
            <x-ui.input name="newLabel" :label="__('menu.fields.label')" wire:model="newLabel" maxlength="100" required />

            @unless ($newParentId)
                <fieldset class="flex flex-col gap-2">
                    <legend class="mb-2 text-sm font-semibold text-ink-900">{{ __('menu.fields.icon') }}</legend>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-7">
                        @foreach ($icons as $key => $name)
                            <label class="flex cursor-pointer flex-col items-center gap-1 rounded-md border-[1.5px] border-line p-2 text-[11px] text-ink-700 transition hover:bg-surface has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50 has-[:checked]:text-primary-700 has-[:focus-visible]:ring-4 has-[:focus-visible]:ring-primary-200">
                                <input type="radio" name="newIcon" value="{{ $key }}" wire:model="newIcon" class="sr-only">
                                <x-ui.icon :name="$key" />
                                <span class="w-full truncate text-center">{{ $name }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('newIcon')
                        <p class="text-[13px] font-medium text-danger-700">{{ $message }}</p>
                    @enderror
                </fieldset>
            @endunless

            <x-ui.select name="newRoute" :label="__('menu.fields.route')" :options="$this->availableRoutes"
                         :placeholder="__('menu.fields.route_none')" :hint="__('menu.fields.route_hint')" wire:model="newRoute" />

            <fieldset class="flex flex-col gap-2">
                <legend class="mb-1 text-sm font-semibold text-ink-900">{{ __('menu.fields.roles') }}</legend>
                <p class="text-[13px] text-ink-500">{{ __('menu.fields.roles_hint') }}</p>
                <div class="grid gap-1 sm:grid-cols-2">
                    @foreach ($roleOptions as $roleId => $roleName)
                        <label class="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-[10px] px-2 text-[15px] hover:bg-surface">
                            <input type="checkbox" value="{{ $roleId }}" wire:model="newRoles" class="size-[18px] rounded accent-primary-600">
                            {{ $roleName }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('menu.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="create" wire:loading.attr="disabled" wire:target="create">{{ __('menu.actions.create') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('menu.confirm_delete.title', ['name' => $this->pendingDeletion?->label])">
        <p>{{ __('menu.confirm_delete.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('menu.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('menu.confirm_delete.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
