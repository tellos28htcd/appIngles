<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('schools.title')" :subtitle="__('schools.subtitle')">
        <x-slot:actions>
            <a href="{{ route('schools.create') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                <span aria-hidden="true">+</span> {{ __('schools.new') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Filtros --}}
        <div class="flex flex-col gap-3 border-b border-line p-4 md:flex-row md:items-center">
            <div class="relative flex-1">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="schools-search" class="sr-only">{{ __('schools.search') }}</label>
                <input id="schools-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('schools.search') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <div class="flex gap-1.5" role="group" aria-label="{{ __('schools.columns.status') }}">
                @foreach (['' => __('schools.all'), 'active' => __('schools.status.active'), 'suspended' => __('schools.status.suspended')] as $value => $label)
                    <button type="button" wire:click="$set('status', '{{ $value }}')" aria-pressed="{{ $status === $value ? 'true' : 'false' }}"
                            @class([
                                'h-10 rounded-full border px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
                                'border-primary-600 bg-primary-600 text-on-primary' => $status === $value,
                                'border-line bg-white text-ink-700 hover:bg-surface-2' => $status !== $value,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($totalSchools === 0)
                <x-ui.empty-state icon="building" :title="__('schools.empty')" :description="__('schools.empty_hint')">
                    <a href="{{ route('schools.create') }}" wire:navigate
                       class="inline-flex h-11 items-center rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary hover:bg-primary-700">{{ __('schools.new') }}</a>
                </x-ui.empty-state>
            @elseif ($this->schools->isEmpty())
                <x-ui.empty-state icon="search" :title="__('schools.no_results')" />
            @else
                {{-- Escritorio: tabla --}}
                <table class="hidden w-full text-left md:table">
                    <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('schools.columns.school') }}</th>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('schools.columns.location') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">{{ __('schools.columns.users') }}</th>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('schools.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">{{ __('schools.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->schools as $school)
                            <tr wire:key="school-{{ $school->id }}" class="hover:bg-surface">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.brand-mark :brand="\App\Support\Brand::forSchool($school)" size="sm" scoped />
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate font-bold">{{ $school->name }}</span>
                                            <span class="font-mono text-xs text-ink-500">{{ $school->code }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-700">{{ collect([$school->municipality?->name, $school->state?->name])->filter()->implode(', ') ?: '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm tabular-nums">{{ $school->users_count }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :variant="$school->isActive() ? 'success' : 'danger'">{{ $school->status->label() }}</x-ui.badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        @include('livewire.schools.partials.row-actions')
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Móvil: tarjetas --}}
                <ul class="divide-y divide-line md:hidden">
                    @foreach ($this->schools as $school)
                        <li wire:key="school-card-{{ $school->id }}" class="flex flex-col gap-3 p-4">
                            <div class="flex items-start gap-3">
                                <x-ui.brand-mark :brand="\App\Support\Brand::forSchool($school)" size="sm" scoped />
                                <div class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate font-bold">{{ $school->name }}</span>
                                    <span class="text-xs text-ink-500"><span class="font-mono">{{ $school->code }}</span> · {{ trans_choice('schools.users_count', $school->users_count) }}</span>
                                </div>
                                <x-ui.badge :variant="$school->isActive() ? 'success' : 'danger'">{{ $school->status->label() }}</x-ui.badge>
                            </div>
                            <div class="flex gap-1">@include('livewire.schools.partials.row-actions')</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($this->schools->hasPages())
            <div class="border-t border-line p-4">{{ $this->schools->links('partials.pagination') }}</div>
        @endif
    </section>

    <x-ui.modal wire:model="confirmingSuspendId" :title="__('schools.confirm_suspend.title', ['name' => $this->schoolToSuspend?->name])">
        <p>{{ __('schools.confirm_suspend.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('schools.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="suspend">{{ __('schools.confirm_suspend.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
