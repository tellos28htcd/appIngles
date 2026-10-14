<div>
    <x-catalog.section :title="__('catalogs.student.advisors.title')" :description="__('catalogs.student.advisors.description')"
                       add="edit" :add-label="__('catalogs.student.advisors.add')" class="max-w-4xl">
        @if ($this->advisors->isEmpty())
            <x-ui.empty-state icon="users" :title="__('catalogs.empty')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($this->advisors as $advisor)
                    <li wire:key="advisor-{{ $advisor->id }}" @class(['flex items-center gap-3 px-4 py-3 md:px-5', 'opacity-60' => ! $advisor->is_active])>
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="font-semibold [overflow-wrap:anywhere]">{{ $advisor->name }}</span>
                            <span class="text-xs text-ink-500">
                                {{ $advisor->position?->name ?? __('catalogs.student.advisors.no_position') }}
                                @if ($advisor->hired_on) · {{ __('catalogs.student.advisors.since', ['date' => $advisor->hired_on->translatedFormat('j M Y')]) }}@endif
                                @if ($advisor->left_on) · {{ __('catalogs.student.advisors.until', ['date' => $advisor->left_on->translatedFormat('j M Y')]) }}@endif
                            </span>
                        </div>
                        <x-catalog.row-actions :active="$advisor->is_active" :name="$advisor->name"
                                               edit="edit({{ $advisor->id }})" toggle="toggle({{ $advisor->id }})" delete="confirmDeletion({{ $advisor->id }})" />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editing" :title="__($advisorId ? 'catalogs.student.advisors.edit' : 'catalogs.student.advisors.add')">
        <x-ui.input name="name" :label="__('catalogs.student.advisors.fields.name')" wire:model="name" maxlength="120" class="uppercase" required />
        <x-ui.select name="positionId" :label="__('catalogs.student.advisors.fields.position')" :options="$positions"
                     :placeholder="__('catalogs.student.advisors.no_position')" wire:model="positionId" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="hiredOn" type="date" :label="__('catalogs.student.advisors.fields.hired_on')" wire:model="hiredOn" />
            <x-ui.input name="leftOn" type="date" :label="__('catalogs.student.advisors.fields.left_on')" wire:model="leftOn" />
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="save">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
