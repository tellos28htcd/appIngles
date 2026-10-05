<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($editing ? 'roles.edit_title' : 'roles.create_title')" :subtitle="__('roles.editor_subtitle')" />

    @include('livewire.roles.partials.tabs')

    <form wire:submit="save" class="flex flex-col gap-4 md:gap-6" novalidate>
        <div class="grid gap-4 md:gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
            <x-ui.card :title="__('roles.sections.data')" class="self-start">
                <x-ui.input name="name" :label="__('roles.fields.name')" wire:model="name" maxlength="100" required />
                <x-ui.textarea name="description" :label="__('roles.fields.description')" wire:model="description" rows="3" maxlength="255" />
                <x-ui.toggle name="is_active" :label="__('roles.fields.is_active')" :description="__('roles.fields.is_active_hint')" wire:model="is_active" />
            </x-ui.card>

            {{-- Asignación de módulos y submódulos --}}
            <x-ui.card :title="__('roles.sections.access')" :description="__('roles.sections.access_hint')">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-surface px-4 py-3">
                    <p class="text-sm font-semibold text-ink-700" aria-live="polite">
                        {{ __('roles.selected_count', ['count' => count($selected), 'total' => $totalLeaves]) }}
                    </p>
                    <div class="flex gap-1">
                        <button type="button" wire:click="selectAll" class="h-9 rounded-[10px] px-3 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">{{ __('roles.select_all') }}</button>
                        <button type="button" wire:click="selectNone" class="h-9 rounded-[10px] px-3 text-sm font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">{{ __('roles.select_none') }}</button>
                    </div>
                </div>

                @error('selected.*')
                    <p class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700"><x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}</p>
                @enderror

                <ul class="grid gap-3 lg:grid-cols-2">
                    @foreach ($this->menu as $module)
                        @php
                            $childIds = $module->children->pluck('id')->map(fn ($id) => (string) $id);
                            $checkedCount = $childIds->intersect($selected)->count();
                        @endphp
                        <li wire:key="module-{{ $module->id }}" class="flex flex-col rounded-lg border border-line">
                            @if ($module->children->isEmpty())
                                {{-- Módulo sin submódulos --}}
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2.5">
                                    <input type="checkbox" value="{{ $module->id }}" wire:model.live="selected"
                                           @disabled(in_array((string) $module->id, $alwaysGranted, true))
                                           class="size-5 flex-none rounded accent-primary-600">
                                    <x-ui.icon :name="$module->icon" class="text-ink-500" />
                                    <span class="flex-1 font-bold">{{ $module->label }}</span>
                                    @if (in_array((string) $module->id, $alwaysGranted, true))
                                        <span class="text-xs text-ink-500">{{ __('roles.always') }}</span>
                                    @elseif ($module->isComingSoon())
                                        <span class="rounded-full bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-500">{{ __('layout.coming_soon') }}</span>
                                    @endif
                                </label>
                            @else
                                {{-- Módulo con submódulos: la casilla del módulo marca o desmarca todos --}}
                                <label class="flex min-h-12 cursor-pointer items-center gap-3 border-b border-line bg-surface px-4 py-2.5">
                                    <input type="checkbox" wire:click="toggleModule({{ $module->id }})"
                                           @checked($checkedCount === $childIds->count())
                                           x-effect="$el.indeterminate = {{ $checkedCount > 0 && $checkedCount < $childIds->count() ? 'true' : 'false' }}"
                                           class="size-5 flex-none rounded accent-primary-600">
                                    <x-ui.icon :name="$module->icon" class="text-ink-500" />
                                    <span class="flex-1 font-bold">{{ $module->label }}</span>
                                    <span class="text-xs font-semibold tabular-nums text-ink-500">{{ $checkedCount }}/{{ $childIds->count() }}</span>
                                </label>
                                <div class="flex flex-col py-1">
                                    @foreach ($module->children as $child)
                                        <label wire:key="item-{{ $child->id }}" class="flex min-h-11 cursor-pointer items-center gap-3 py-1.5 pl-12 pr-4 hover:bg-surface">
                                            <input type="checkbox" value="{{ $child->id }}" wire:model.live="selected" class="size-[18px] flex-none rounded accent-primary-600">
                                            <span class="flex-1 text-[15px]">{{ $child->label }}</span>
                                            @if ($child->isComingSoon())
                                                <span class="rounded-full bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-500">{{ __('layout.coming_soon') }}</span>
                                            @endif
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        <div class="sticky bottom-20 z-20 -mx-4 flex flex-col-reverse gap-2.5 border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:flex-row md:justify-end md:border-0 md:bg-transparent md:p-0">
            <a href="{{ route('roles.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-5 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('roles.actions.cancel') }}
            </a>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('roles.actions.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
