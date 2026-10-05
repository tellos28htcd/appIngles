<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($editing ? 'schools.edit_title' : 'schools.create_title')"
                      :subtitle="$editing ? $form->code.' · '.$form->name : __('schools.create_subtitle')" />

    <form wire:submit="save" class="flex flex-col gap-4 md:gap-6" novalidate>
        <div class="grid gap-4 md:gap-6 xl:grid-cols-2">
            {{-- Datos del plantel --}}
            <x-ui.card :title="__('schools.sections.plantel')">
                <div class="grid gap-4 sm:grid-cols-[180px_minmax(0,1fr)]">
                    <x-ui.input name="form.code" :label="__('schools.fields.code')" wire:model="form.code" maxlength="20" class="font-mono uppercase" required />
                    <x-ui.input name="form.name" :label="__('schools.fields.name')" :hint="__('schools.fields.name_hint')" wire:model="form.name" maxlength="150" required />
                </div>
            </x-ui.card>

            {{-- Contacto y datos fiscales --}}
            <x-ui.card :title="__('schools.sections.fiscal')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="form.phone" type="tel" :label="__('schools.fields.phone')" wire:model="form.phone" inputmode="tel" autocomplete="tel" />
                    <x-ui.input name="form.rfc" :label="__('schools.fields.rfc')" wire:model="form.rfc" maxlength="13" class="font-mono uppercase" />
                </div>
                <x-ui.input name="form.legal_name" :label="__('schools.fields.legal_name')" wire:model="form.legal_name" maxlength="200" />
            </x-ui.card>
        </div>

        {{-- Domicilio --}}
        <x-ui.card :title="__('schools.sections.address')">
            <div class="grid gap-4 md:grid-cols-6">
                <div class="md:col-span-3"><x-ui.input name="form.street" :label="__('schools.fields.street')" wire:model="form.street" autocomplete="address-line1" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.exterior_number" :label="__('schools.fields.exterior_number')" wire:model="form.exterior_number" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.interior_number" :label="__('schools.fields.interior_number')" wire:model="form.interior_number" /></div>
                <div class="md:col-span-1"><x-ui.input name="form.postal_code" :label="__('schools.fields.postal_code')" wire:model="form.postal_code" inputmode="numeric" maxlength="5" autocomplete="postal-code" /></div>
                <div class="md:col-span-2"><x-ui.input name="form.neighborhood" :label="__('schools.fields.neighborhood')" wire:model="form.neighborhood" /></div>
                <div class="md:col-span-2">
                    <x-ui.select name="form.state_id" :label="__('schools.fields.state_id')" :options="$this->states"
                                 :placeholder="__('schools.fields.select_state')" wire:model.live="form.state_id" required />
                </div>
                <div class="md:col-span-2">
                    <x-ui.select name="form.municipality_id" :label="__('schools.fields.municipality_id')" :options="$this->municipalities"
                                 :placeholder="__($form->state_id ? 'schools.fields.select_municipality' : 'schools.fields.select_state_first')"
                                 wire:model="form.municipality_id" wire:key="municipalities-{{ $form->state_id }}"
                                 :disabled="! $form->state_id" required />
                </div>
            </div>
        </x-ui.card>

        <div class="grid gap-4 md:gap-6 xl:grid-cols-2">
            {{-- Identidad visual --}}
            <x-ui.card :title="__('schools.sections.brand')" :description="__('schools.sections.brand_hint')">
                <div class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-ink-900">{{ __('schools.fields.logo') }}</span>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex h-16 min-w-16 items-center justify-center rounded-lg border border-line bg-surface p-2">
                            @if ($logoPreview)
                                <img src="{{ $logoPreview }}" alt="" class="h-12 w-auto object-contain">
                            @elseif ($form->school?->logo_path && ! $form->remove_logo)
                                <img src="{{ $form->school->logoUrl() }}" alt="" class="h-12 w-auto object-contain">
                            @else
                                <x-ui.icon name="building" class="size-7 text-ink-400" />
                            @endif
                        </div>
                        <div class="flex flex-col gap-2">
                            <input type="file" wire:model="form.logo" accept="image/png,image/jpeg,image/webp" id="form-logo"
                                   aria-describedby="form-logo-hint"
                                   class="text-sm text-ink-700 file:mr-3 file:h-10 file:cursor-pointer file:rounded-[10px] file:border-0 file:bg-primary-50 file:px-4 file:font-bold file:text-primary-700 hover:file:bg-primary-100">
                            <span wire:loading wire:target="form.logo" class="text-[13px] text-ink-500">{{ __('access.reset.submitting') }}</span>
                            @if ($form->school?->logo_path && ! $form->logo)
                                <label class="flex items-center gap-2 text-sm text-ink-700">
                                    <input type="checkbox" wire:model.live="form.remove_logo" class="size-4 accent-primary-600">{{ __('schools.fields.logo_remove') }}
                                </label>
                            @endif
                        </div>
                    </div>
                    <p id="form-logo-hint" class="text-[13px] text-ink-500">{{ __('schools.fields.logo_hint') }}</p>
                    @error('form.logo')
                        <p class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700"><x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['brand_primary', 'brand_accent'] as $color)
                        <div class="flex flex-col gap-2">
                            <label for="form-{{ $color }}" class="text-sm font-semibold text-ink-900">{{ __("schools.fields.$color") }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" wire:model.live.debounce.250ms="form.{{ $color }}" aria-label="{{ __("schools.fields.$color") }}"
                                       class="h-11 w-14 flex-none cursor-pointer rounded-md border-[1.5px] border-line-strong bg-white p-1">
                                <input id="form-{{ $color }}" type="text" wire:model.live.debounce.400ms="form.{{ $color }}" maxlength="7"
                                       class="h-11 w-full rounded-md border-[1.5px] border-line-strong bg-white px-3.5 font-mono uppercase focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100">
                            </div>
                            @if ($color === 'brand_accent')
                                <p class="text-[13px] text-ink-500">{{ __('schools.fields.brand_accent_hint') }}</p>
                            @endif
                            @error("form.$color")
                                <p class="flex items-start gap-1.5 text-[13px] font-medium text-danger-700"><x-ui.icon name="alert" class="mt-px size-4" />{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                @if ($this->primaryContrast !== null && $this->primaryContrast < 4.5)
                    <div role="status" class="flex items-start gap-2.5 rounded-lg border border-warning-200 bg-warning-50 p-3.5 text-sm font-medium text-warning-700">
                        <x-ui.icon name="alert" class="mt-px size-[18px]" />{{ __('schools.fields.contrast_warning', ['ratio' => $this->primaryContrast]) }}
                    </div>
                @endif

                {{-- Vista previa: los tokens se recalculan solo dentro de este bloque --}}
                @if (preg_match('/^#[0-9A-Fa-f]{6}$/', $form->brand_primary) && preg_match('/^#[0-9A-Fa-f]{6}$/', $form->brand_accent))
                    <div class="flex flex-col gap-2">
                        <span class="text-sm font-semibold text-ink-900">{{ __('schools.fields.preview') }}</span>
                        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-line bg-surface p-4"
                             style="--brand-primary: {{ $form->brand_primary }}; --brand-accent: {{ $form->brand_accent }}; --brand-on-primary: {{ \App\Support\BrandColor::onPrimary($form->brand_primary) }}; --brand-on-accent: {{ \App\Support\BrandColor::onColor($form->brand_accent) }};">
                            <span class="inline-flex h-11 items-center rounded-md bg-primary-600 px-5 text-[15px] font-bold text-on-primary">{{ __('schools.actions.save') }}</span>
                            <span class="inline-flex h-11 items-center rounded-md bg-primary-50 px-5 text-[15px] font-bold text-primary-700">{{ __('users.title') }}</span>
                            <span class="inline-flex h-7 items-center rounded-full bg-accent-500 px-3 text-[13px] font-bold text-on-accent">{{ __('layout.coming_soon') }}</span>
                            <span class="text-sm font-bold text-primary-700 underline">{{ $form->name ?: __('schools.fields.name') }}</span>
                        </div>
                    </div>
                @endif
            </x-ui.card>

            <div class="flex flex-col gap-4 md:gap-6">
                {{-- Operación --}}
                <x-ui.card :title="__('schools.sections.operation')">
                    <x-ui.input name="form.session_capacity" type="number" :label="__('schools.fields.session_capacity')"
                                :hint="__('schools.fields.session_capacity_hint', ['max' => \App\Models\School::MAX_SESSION_CAPACITY])"
                                wire:model="form.session_capacity" min="1" max="{{ \App\Models\School::MAX_SESSION_CAPACITY }}" inputmode="numeric" class="tabular-nums" required />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select name="form.timezone" :label="__('schools.fields.timezone')" :options="$timezones" wire:model="form.timezone" />
                        <x-ui.select name="form.currency" :label="__('schools.fields.currency')" :options="['MXN' => 'MXN · Peso mexicano', 'USD' => 'USD · Dólar']" wire:model="form.currency" />
                    </div>
                </x-ui.card>

                {{-- Folios --}}
                <x-ui.card :title="__('schools.sections.folios')" :description="__('schools.sections.folios_hint')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="form.last_folio_series_a" type="number" :label="__('schools.fields.last_folio_series_a')" wire:model="form.last_folio_series_a" min="0" inputmode="numeric" class="tabular-nums" />
                        <x-ui.input name="form.last_folio_series_b" type="number" :label="__('schools.fields.last_folio_series_b')" wire:model="form.last_folio_series_b" min="0" inputmode="numeric" class="tabular-nums" />
                    </div>
                </x-ui.card>
            </div>
        </div>

        {{-- Configuración del plantel --}}
        <x-ui.card :title="__('schools.sections.settings')">
            <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
                <div class="flex flex-col">
                    @foreach (['works_sundays', 'schedules_classrooms', 'books_without_classroom', 'hybrid_clubs', 'requires_progress'] as $flag)
                        <x-ui.toggle name="form.{{ $flag }}" :label="__('schools.fields.'.$flag)" wire:model="form.{{ $flag }}" />
                    @endforeach
                </div>
                <div class="flex flex-col gap-6">
                    <x-ui.choice name="form.self_booking" :label="__('schools.fields.self_booking')"
                                 :options="collect(\App\Enums\SelfBooking::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                                 wire:model="form.self_booking" />
                    <x-ui.choice name="form.max_sessions_scope" :label="__('schools.fields.max_sessions_scope')"
                                 :options="collect(\App\Enums\MaxSessionsScope::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                                 wire:model="form.max_sessions_scope" />
                    <x-ui.input name="form.max_sessions" type="number" :label="__('schools.fields.max_sessions')" wire:model="form.max_sessions" min="1" max="99" inputmode="numeric" class="tabular-nums" />
                    <x-ui.choice name="form.failed_activity_policy" :label="__('schools.fields.failed_activity_policy')"
                                 :options="collect(\App\Enums\FailedActivityPolicy::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()"
                                 wire:model="form.failed_activity_policy" />
                </div>
            </div>
        </x-ui.card>

        {{-- Acciones: fijas abajo en móvil --}}
        <div class="sticky bottom-20 z-20 -mx-4 flex flex-col-reverse gap-2.5 border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:flex-row md:justify-end md:border-0 md:bg-transparent md:p-0">
            <a href="{{ route('schools.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-5 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('schools.actions.cancel') }}
            </a>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('schools.actions.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
