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
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
