<div>
    <x-catalog.section :title="__('catalogs.clubs.title')" :description="__('catalogs.clubs.description')"
                       add="edit" :add-label="__('catalogs.clubs.add')">
        @if ($this->clubs->isEmpty())
            <x-ui.empty-state icon="chat" :title="__('catalogs.empty')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-bold md:px-5">{{ __('catalogs.clubs.club') }}</th>
                            @foreach ($this->books as $book)
                                <th scope="col" class="w-20 px-2 py-2.5 text-right font-bold" title="{{ $book->name }}">{{ __('catalogs.clubs.level_short', ['level' => $book->level]) }}</th>
                            @endforeach
                            <th scope="col" class="px-4 py-2.5 md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->clubs as $club)
                            <tr wire:key="club-{{ $club->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $club->is_active])>
                                <td class="px-4 py-2 md:px-5">
                                    <span class="font-semibold">{{ $club->name }}</span>
                                    @if ($club->description)
                                        <span class="block text-xs text-ink-500">{{ $club->description }}</span>
                                    @endif
                                    @if ($club->codeList())
                                        <span class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($club->codeList() as $code)
                                                <span @class(['rounded px-1.5 py-0.5 text-[11px] font-extrabold', \App\Support\RoomColors::for((int) $code['color']), 'opacity-50' => ! ($code['is_active'] ?? true)])
                                                      title="{{ __('catalogs.clubs.code_title', ['levels' => collect($code['levels'])->map(fn ($l) => 'N'.$l)->implode(', ')]) }}">{{ $code['code'] }}</span>
                                            @endforeach
                                        </span>
                                    @endif
                                </td>
                                @foreach ($this->books as $book)
                                    @php($pivot = $club->books->firstWhere('id', $book->id)?->pivot)
                                    <td class="px-2 py-2 text-right tabular-nums">
                                        @if ($pivot)
                                            <span class="font-semibold">{{ rtrim(rtrim(number_format((float) $pivot->hours, 1), '0'), '.') }}</span><span class="text-xs text-ink-500"> h</span>
                                        @else
                                            <span class="text-ink-400" title="{{ __('catalogs.clubs.not_offered') }}">—</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-4 py-2 md:px-5">
                                    <x-catalog.row-actions :active="$club->is_active" :name="$club->name"
                                                           edit="edit({{ $club->id }})" toggle="toggle({{ $club->id }})" delete="confirmDeletion({{ $club->id }})" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-4 py-2.5 text-xs text-ink-500 md:px-5">{{ __('catalogs.clubs.legend') }}</p>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" size="md" :title="__($clubId ? 'catalogs.clubs.edit' : 'catalogs.clubs.add')">
        <x-ui.input name="name" :label="__('catalogs.fields.name')" wire:model="name" maxlength="100" required />
        <x-ui.input name="description" :label="__('catalogs.fields.description')" wire:model="description" maxlength="255" />
        <fieldset class="flex flex-col gap-2">
            <legend class="mb-1 text-sm font-semibold text-ink-900">{{ __('catalogs.clubs.hours_by_level') }}</legend>
            <p class="text-[13px] text-ink-500">{{ __('catalogs.clubs.hours_hint') }}</p>
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ($this->books as $book)
                    <x-ui.input name="hours.{{ $book->id }}" id="hours-{{ $book->id }}" type="number" step="0.5" min="0.5"
                                :label="__('catalogs.clubs.level_label', ['level' => $book->level, 'name' => $book->name])"
                                wire:model="hours.{{ $book->id }}" class="tabular-nums" />
                @endforeach
            </div>
        </fieldset>
        <fieldset class="flex flex-col gap-3">
            <legend class="mb-1 text-sm font-semibold text-ink-900">{{ __('catalogs.clubs.codes') }}</legend>
            <p class="text-[13px] text-ink-500">{{ __('catalogs.clubs.codes_hint') }}</p>
            @foreach ($codes as $index => $code)
                <div class="flex flex-col gap-2 rounded-lg border border-line p-3" wire:key="code-{{ $index }}">
                    <div class="flex items-end gap-2">
                        <x-ui.input name="codes.{{ $index }}.code" id="code-{{ $index }}" :label="__('catalogs.clubs.code')" wire:model="codes.{{ $index }}.code" maxlength="10" class="w-24 font-mono uppercase" required />
                        <x-ui.select name="codes.{{ $index }}.color" id="color-{{ $index }}" :label="__('catalogs.clubs.code_color')"
                                     :options="collect(range(1, 9))->mapWithKeys(fn ($n) => [$n => __('catalogs.clubs.color_option', ['n' => $n])])->all()" wire:model.live="codes.{{ $index }}.color" />
                        <span class="mb-2 size-8 flex-none rounded-md {{ \App\Support\RoomColors::for((int) ($code['color'] ?: 1)) }}" aria-hidden="true"></span>
                        <button type="button" wire:click="removeCode({{ $index }})" class="mb-1 ml-auto flex size-9 items-center justify-center rounded-[10px] text-danger-700 hover:bg-danger-50"
                                aria-label="{{ __('catalogs.clubs.remove_code') }}" title="{{ __('catalogs.clubs.remove_code') }}"><x-ui.icon name="trash" class="size-4" /></button>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span class="font-semibold text-ink-700">{{ __('catalogs.clubs.code_levels') }}:</span>
                        @foreach ($this->books as $book)
                            <label class="inline-flex items-center gap-1.5">
                                <input type="checkbox" value="{{ $book->level }}" wire:model="codes.{{ $index }}.levels" class="size-4 accent-primary-600"> N{{ $book->level }}
                            </label>
                        @endforeach
                        <label class="ml-auto inline-flex items-center gap-1.5">
                            <input type="checkbox" wire:model="codes.{{ $index }}.is_active" class="size-4 accent-primary-600"> {{ __('catalogs.clubs.code_active') }}
                        </label>
                    </div>
                    @error("codes.$index.code") <p role="alert" class="text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                    @error("codes.$index.levels") <p role="alert" class="text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
                </div>
            @endforeach
            <div><x-ui.button variant="secondary" size="sm" wire:click="addCode"><x-ui.icon name="plus" class="size-4" />{{ __('catalogs.clubs.add_code') }}</x-ui.button></div>
        </fieldset>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
