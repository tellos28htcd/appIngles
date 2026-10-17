<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('availability.schedule_heading')" :subtitle="$teacher->fullName().($teacher->contract_type ? ' · '.$teacher->contract_type->label() : '')">
        <x-slot:actions>
            <a href="{{ route('teachers.edit', $teacher) }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-md px-4 text-[15px] font-bold text-ink-700 hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                {{ __('students.actions.back') }}
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.card :description="__('availability.schedule_hint')" class="max-w-5xl">
        @error('selected')
            <p role="alert" class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm font-medium text-danger-700">{{ $message }}</p>
        @enderror

        <div class="overflow-x-auto">
            <table class="w-full border-separate border-spacing-1 text-center text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="w-20 text-left text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('availability.hour') }}</th>
                        @foreach ($days as $day)
                            <th scope="col" class="min-w-20">
                                <button type="button" wire:click="toggleMany('{{ $scheduleSlots->map(fn ($slot) => $day.'-'.$slot->id)->implode(',') }}')"
                                        class="w-full rounded-md px-1 py-1 text-xs font-bold uppercase tracking-wide text-ink-700 hover:bg-surface-2"
                                        title="{{ __('availability.toggle_day') }}">{{ $dayNames[$day] }}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scheduleSlots as $slot)
                        <tr wire:key="slot-{{ $slot->id }}">
                            <th scope="row" class="text-left">
                                <button type="button" wire:click="toggleMany('{{ collect($days)->map(fn ($day) => $day.'-'.$slot->id)->implode(',') }}')"
                                        class="rounded-md px-1 py-1 font-mono text-xs tabular-nums text-ink-700 hover:bg-surface-2" title="{{ __('availability.toggle_hour') }}">{{ $slot->range() }}</button>
                            </th>
                            @foreach ($days as $day)
                                @php($key = $day.'-'.$slot->id)
                                <td>
                                    <label class="flex h-8 cursor-pointer items-center justify-center rounded-md border transition has-[:checked]:border-primary-600 has-[:checked]:bg-primary-600 border-line hover:bg-primary-50">
                                        <input type="checkbox" wire:model.live="selected.{{ $key }}" class="sr-only"
                                               aria-label="{{ $dayNames[$day] }} {{ $slot->range() }}">
                                        @if (! empty($selected[$key]))
                                            <x-ui.icon name="check" class="size-4 text-on-primary" />
                                        @endif
                                    </label>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th scope="row" class="text-left text-xs font-bold uppercase tracking-wide text-ink-500">{{ __('availability.hours') }}</th>
                        @foreach ($days as $day)
                            <td @class(['rounded-md py-1 font-bold tabular-nums', 'bg-warning-50 text-warning-700' => $dailyReference && $dailyHours[$day] > $dailyReference, 'text-ink-900' => ! $dailyReference || $dailyHours[$day] <= $dailyReference])>
                                {{ $dailyHours[$day] }}
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-col gap-1 text-sm">
            <p>
                <span class="font-bold tabular-nums">{{ trans_choice('availability.weekly_total', $weeklyHours) }}</span>
                @if ($weeklyLimit) <span class="text-ink-500">· {{ __('availability.weekly_limit', ['limit' => $weeklyLimit]) }}</span>@endif
                @if ($teacher->weekly_hours) <span class="text-ink-500">· {{ __('availability.to_fulfill', ['hours' => $teacher->weekly_hours]) }}</span>@endif
            </p>
            @if ($dailyReference)
                <p class="text-ink-500">{{ __('availability.daily_reference', ['hours' => $dailyReference]) }}</p>
            @endif
        </div>

        <div class="flex justify-end">
            <x-ui.button wire:click="save" wire:loading.attr="disabled" wire:target="save">{{ __('availability.schedule_save') }}</x-ui.button>
        </div>
    </x-ui.card>
</div>
