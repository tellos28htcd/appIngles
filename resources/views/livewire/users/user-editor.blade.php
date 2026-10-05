<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__($editing ? 'users.edit_title' : 'users.create_title')"
                      :subtitle="$editing ? $form->user->name.' · '.$form->user->email : __('users.sections.access_hint')" />

    <form wire:submit="save" class="flex max-w-4xl flex-col gap-4 md:gap-6" novalidate>
        <x-ui.card :title="__('users.sections.personal')">
            <div class="grid gap-4 md:grid-cols-3">
                <x-ui.input name="form.first_name" :label="__('users.fields.first_name')" wire:model="form.first_name" autocomplete="given-name" maxlength="80" required />
                <x-ui.input name="form.last_name" :label="__('users.fields.last_name')" wire:model="form.last_name" autocomplete="family-name" maxlength="80" required />
                <x-ui.input name="form.second_last_name" :label="__('users.fields.second_last_name')" wire:model="form.second_last_name" maxlength="80" />
            </div>
            <x-ui.input name="form.email" type="email" :label="__('users.fields.email')" wire:model="form.email"
                        autocomplete="email" inputmode="email" maxlength="255" required />
            <x-ui.textarea name="form.notes" :label="__('users.fields.notes')" wire:model="form.notes" rows="3" maxlength="2000" />
        </x-ui.card>

        <x-ui.card :title="__('users.sections.access')" :description="$editing ? null : __('users.sections.access_hint')">
            <div class="grid gap-4 md:grid-cols-2">
                <x-ui.select name="form.role_id" :label="__('users.fields.role_id')" :options="$this->roles"
                             :placeholder="__('users.fields.select_role')" wire:model.live="form.role_id"
                             :disabled="$isSelf" required />

                @if ($actorIsPlatform)
                    @if ($platformRole)
                        <div class="flex flex-col gap-2">
                            <span class="text-sm font-semibold text-ink-900">{{ __('users.fields.school_id') }}</span>
                            <p class="flex min-h-11 items-center rounded-md bg-surface px-3.5 text-sm text-ink-700">{{ __('users.fields.platform_no_school') }}</p>
                        </div>
                    @else
                        <x-ui.select name="form.school_id" :label="__('users.fields.school_id')" :options="$this->schools"
                                     :placeholder="__('users.fields.select_school')" wire:model="form.school_id" required />
                    @endif
                @endif
            </div>
        </x-ui.card>

        <div class="sticky bottom-20 z-20 -mx-4 flex flex-col-reverse gap-2.5 border-t border-line bg-white/95 px-4 py-3 backdrop-blur md:static md:mx-0 md:flex-row md:justify-end md:border-0 md:bg-transparent md:p-0">
            <a href="{{ route('users.index') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-5 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('users.actions.cancel') }}
            </a>
            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                <span wire:loading wire:target="save" class="size-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
                {{ __('users.actions.save') }}
            </x-ui.button>
        </div>
    </form>
</div>
