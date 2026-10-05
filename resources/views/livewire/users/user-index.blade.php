<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('users.title')" :subtitle="__($isPlatform ? 'users.subtitle_platform' : 'users.subtitle')">
        <x-slot:actions>
            @can('create', \App\Models\User::class)
                <a href="{{ route('users.create', $isPlatform && ctype_digit($school) ? ['escuela' => $school] : []) }}" wire:navigate
                   class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary transition hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                    <span aria-hidden="true">+</span> {{ __('users.new') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-ui.page-header>

    <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
        {{-- Filtros --}}
        <div class="flex flex-col gap-3 border-b border-line p-4">
            <div class="relative">
                <x-ui.icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-[18px] -translate-y-1/2 text-ink-500" />
                <label for="users-search" class="sr-only">{{ __('users.search') }}</label>
                <input id="users-search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('users.search') }}"
                       class="h-11 w-full rounded-md border-[1.5px] border-line bg-white pl-10 pr-3.5 text-[15px] placeholder:text-ink-400 focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
            </div>
            <div @class(['grid gap-3 sm:grid-cols-2', 'lg:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,1fr)_auto]' => $isPlatform, 'lg:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_auto]' => ! $isPlatform])>
                @if ($isPlatform)
                    <x-ui.select name="school" :label="__('users.columns.school')" :options="$this->schoolOptions" :placeholder="__('users.all_schools')"
                                 wire:model.live="school" sr-only-label />
                @endif
                <x-ui.select name="role" :label="__('users.columns.role')" :options="$this->roleOptions" :placeholder="__('users.all_roles')"
                             wire:model.live="role" sr-only-label />
                <x-ui.select name="status" :label="__('users.columns.status')" :options="$statusOptions" :placeholder="__('users.all_statuses')"
                             wire:model.live="status" sr-only-label />
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters"
                            class="h-11 rounded-md px-4 text-sm font-bold text-primary-700 hover:bg-primary-50 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                        {{ __('users.clear_filters') }}
                    </button>
                @endif
            </div>
        </div>

        <div wire:loading.delay.class="opacity-60" class="transition-opacity">
            @if ($this->users->isEmpty())
                <x-ui.empty-state icon="users" :title="__('users.no_results')" :description="__('users.no_results_hint')">
                    @if ($hasFilters)
                        <x-ui.button variant="secondary" wire:click="clearFilters">{{ __('users.clear_filters') }}</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                {{-- Escritorio: tabla --}}
                <table class="hidden w-full text-left lg:table">
                    <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('users.columns.user') }}</th>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('users.columns.role') }}</th>
                            @if ($isPlatform)
                                <th scope="col" class="px-4 py-3 font-bold">{{ __('users.columns.school') }}</th>
                            @endif
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('users.columns.status') }}</th>
                            <th scope="col" class="px-4 py-3 font-bold">{{ __('users.columns.last_login') }}</th>
                            <th scope="col" class="px-4 py-3 text-right font-bold">{{ __('users.columns.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="hover:bg-surface">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-9 flex-none items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-800" aria-hidden="true">{{ $user->initials() }}</span>
                                        <div class="flex min-w-0 flex-col">
                                            <span class="truncate font-bold">
                                                {{ $user->name }}
                                                @if ($user->is($actor))<span class="ml-1 rounded-full bg-accent-100 px-2 py-0.5 text-[11px] font-bold text-ink-900">{{ __('users.you') }}</span>@endif
                                            </span>
                                            <span class="truncate text-sm text-ink-500">{{ $user->email }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">{{ $user->role?->name }}</td>
                                @if ($isPlatform)
                                    <td class="px-4 py-3 text-sm text-ink-700">
                                        @if ($user->school)
                                            <span class="font-mono text-xs text-ink-500">{{ $user->school->code }}</span> {{ $user->school->name }}
                                        @else
                                            <span class="text-ink-500">{{ __('users.no_school') }}</span>
                                        @endif
                                    </td>
                                @endif
                                <td class="px-4 py-3">@include('livewire.users.partials.status')</td>
                                <td class="px-4 py-3 text-sm tabular-nums text-ink-700">
                                    {{ $user->last_login_at?->timezone(config('appingles.timezone'))->translatedFormat('j M Y, H:i') ?? __('users.never') }}
                                </td>
                                <td class="px-4 py-3"><div class="flex justify-end gap-1">@include('livewire.users.partials.row-actions')</div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Móvil y tablet: tarjetas --}}
                <ul class="divide-y divide-line lg:hidden">
                    @foreach ($this->users as $user)
                        <li wire:key="user-card-{{ $user->id }}" class="flex flex-col gap-3 p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex size-10 flex-none items-center justify-center rounded-full bg-primary-100 text-[13px] font-bold text-primary-800" aria-hidden="true">{{ $user->initials() }}</span>
                                <div class="flex min-w-0 flex-1 flex-col">
                                    <span class="truncate font-bold">{{ $user->name }}</span>
                                    <span class="truncate text-sm text-ink-500">{{ $user->email }}</span>
                                    <span class="mt-1 text-xs text-ink-700">
                                        {{ $user->role?->name }}@if ($isPlatform) · {{ $user->school?->name ?? __('users.no_school') }}@endif
                                    </span>
                                </div>
                                @include('livewire.users.partials.status')
                            </div>
                            <div class="flex flex-wrap gap-1">@include('livewire.users.partials.row-actions')</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="flex flex-col gap-3 border-t border-line p-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <label for="users-per-page" class="text-sm text-ink-700">{{ __('users.per_page') }}</label>
                <select id="users-per-page" wire:model.live="perPage"
                        class="h-10 rounded-[10px] border-[1.5px] border-line-strong bg-white px-2.5 text-sm tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                    @foreach (\App\Livewire\Users\UserIndex::PER_PAGE_OPTIONS as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 sm:flex sm:justify-end">{{ $this->users->links('partials.pagination') }}</div>
        </div>
    </section>

    <x-ui.modal wire:model="confirmingDeletionId" :title="__('users.confirm_delete.title', ['name' => $this->pendingUser?->name])">
        <p>{{ __('users.confirm_delete.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('users.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="delete">{{ __('users.confirm_delete.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="confirmingDeactivationId" :title="__('users.confirm_deactivate.title', ['name' => $this->pendingUser?->name])">
        <p>{{ __('users.confirm_deactivate.body') }}</p>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('users.actions.cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="deactivate">{{ __('users.confirm_deactivate.confirm') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
</div>
