<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('clubs.title')" :subtitle="__('clubs.subtitle')" />

    {{-- Mes --}}
    <section class="flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <x-ui.button variant="outline" wire:click="previousMonth" aria-label="{{ __('clubs.previous_month') }}">‹</x-ui.button>
            <h2 class="min-w-48 text-center font-display text-h3 font-bold">{{ \Illuminate\Support\Str::ucfirst($monthStart->translatedFormat('F Y')) }}</h2>
            <x-ui.button variant="outline" wire:click="nextMonth" aria-label="{{ __('clubs.next_month') }}">›</x-ui.button>
        </div>
        <p class="text-sm text-ink-500">
            {{ trans_choice('clubs.rooms_count', $rooms->count(), ['names' => $rooms->pluck('name')->implode(', ')]) }}
            · {{ trans_choice('clubs.sessions_count', $this->sessions->count()) }}
        </p>
    </section>

    @if ($rooms->isEmpty())
        <x-ui.card>
            <x-ui.empty-state icon="building" :title="__('clubs.no_rooms')" :description="__('clubs.no_rooms_hint')" />
        </x-ui.card>
    @endif

    {{-- Leyenda de abreviaturas --}}
    @if ($this->codeCatalog)
        <div class="flex flex-wrap gap-2">
            @foreach ($this->codeCatalog as $key => $code)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-2.5 py-1 text-xs font-semibold text-ink-700" wire:key="legend-{{ $key }}">
                    <span class="size-3 rounded-full {{ \App\Support\RoomColors::for($code['color']) }}" aria-hidden="true"></span>{{ $code['label'] }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- Una cuadrícula por semana --}}
    @foreach ($weeks as $days)
        <section class="overflow-x-auto rounded-xl border border-line bg-white shadow-xs" wire:key="week-{{ $days[0]->toDateString() }}">
            <table class="w-full min-w-[720px] border-separate border-spacing-0 text-center text-sm">
                <thead>
                    <tr>
                        <th scope="col" class="w-20 border-b border-line bg-primary-700 px-2 py-2 text-xs font-bold uppercase text-on-primary">{{ __('clubs.hour') }}</th>
                        @foreach ($days as $day)
                            @php($inMonth = $day->month === $monthStart->month)
                            @php($holiday = $holidays[$day->toDateString()] ?? null)
                            <th scope="col" @class([
                                'border-b border-l border-line px-2 py-2',
                                'bg-primary-700 text-on-primary' => $inMonth && ! $holiday,
                                'bg-warning-500 text-white' => $inMonth && $holiday,
                                'bg-surface-2 text-ink-400' => ! $inMonth,
                            ])>
                                <span class="block font-display text-base font-bold">{{ \Illuminate\Support\Str::ucfirst($day->translatedFormat('l')) }}</span>
                                <span class="block text-xs font-semibold">{{ $day->translatedFormat('j M') }}</span>
                                @if ($inMonth && $holiday)
                                    <span class="block truncate text-[11px]">{{ $holiday }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($scheduleSlots as $slot)
                        <tr wire:key="week-{{ $days[0]->toDateString() }}-slot-{{ $slot->id }}">
                            <th scope="row" class="border-b border-line bg-surface px-2 py-1 font-mono text-xs font-bold tabular-nums text-ink-700">{{ substr($slot->starts_at, 0, 5) }}</th>
                            @foreach ($days as $day)
                                @php($key = $day->toDateString().'|'.$slot->id)
                                @php($cellSessions = $grid->get($key, collect()))
                                @php($open = $day->month === $monthStart->month && ! isset($holidays[$day->toDateString()]) && $planner->slotAppliesOn($slot, $day->dayOfWeekIso))
                                @php($free = $open ? $capacity($day, $slot) - $cellSessions->count() : 0)
                                <td @class(['h-12 min-w-28 border-b border-l border-line p-1 align-top', 'bg-surface-2' => ! $open])>
                                    <div class="flex flex-col gap-1">
                                        @foreach ($cellSessions as $session)
                                            @php($orphan = in_array($session->id, $orphans, true))
                                            <button type="button" @if ($canManage) wire:click="edit({{ $session->id }})" @else disabled @endif
                                                    @class([
                                                        'flex w-full flex-col items-center rounded-md px-1 py-0.5 leading-tight focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                        \App\Support\RoomColors::for($session->color()),
                                                        'ring-2 ring-danger-600' => $orphan,
                                                        'hover:opacity-85' => $canManage,
                                                    ])
                                                    title="{{ $session->club?->name }} · {{ $session->teacher?->fullName() }} · {{ $session->modality->label() }} · {{ __('clubs.room', ['name' => $session->classroom?->name]) }}{{ $orphan ? ' · '.__('clubs.orphan') : '' }}">
                                                <span class="text-sm font-extrabold">{{ $session->code }}</span>
                                                <span class="text-[11px] font-bold uppercase">{{ $session->teacher?->panelName() }}</span>
                                                <span class="mt-0.5 rounded-full bg-white/90 px-2 text-[10px] font-bold uppercase tracking-wide text-ink-900">{{ $session->modality->label() }}</span>
                                            </button>
                                        @endforeach
                                        @if ($canManage && $free > 0)
                                            <button type="button" wire:click="add('{{ $day->toDateString() }}', {{ $slot->id }})"
                                                    class="flex h-7 w-full items-center justify-center rounded-md text-ink-400 hover:bg-primary-50 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                    aria-label="{{ __('clubs.add_at', ['day' => $day->translatedFormat('l j'), 'hour' => $slot->range()]) }}">
                                                <x-ui.icon name="plus" class="size-4" />
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach

    @if ($canManage)
        <x-ui.modal wire:model="editing" size="md" :title="__($sessionId ? 'clubs.edit' : 'clubs.add')">
            <p class="-mt-3 text-sm font-semibold text-ink-500">
                {{ $date ? \Illuminate\Support\Str::ucfirst(\Carbon\CarbonImmutable::parse($date)->translatedFormat('l j \d\e F')) : '' }} · {{ $slotLabel }}
            </p>
            @error('slot') <p role="alert" class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            <x-ui.select name="codeKey" :label="__('clubs.fields.code')" :options="$this->codeOptions" :placeholder="__('students.select')" wire:model="codeKey" required />
            @error('code') <p role="alert" class="-mt-3 text-sm font-medium text-danger-700">{{ $message }}</p> @enderror
            <x-ui.select name="teacherId" :label="__('clubs.fields.teacher')" :options="$this->teacherOptions"
                         :placeholder="__($this->teacherOptions ? 'students.select' : 'clubs.no_teachers')" :hint="__('clubs.teacher_hint')" wire:model="teacherId" required />
            <x-ui.choice name="modality" :label="__('clubs.fields.modality')" :options="$modalities" wire:model="modality" />
            @if (count($this->roomOptions) > 1 || $classroomId)
                <x-ui.select name="classroomId" :label="__('clubs.fields.room')" :options="$this->roomOptions" :placeholder="__('clubs.any_room')" wire:model="classroomId" />
            @endif
            @unless ($sessionId)
                <x-ui.toggle name="repeat" :label="__('clubs.repeat')" :description="__('clubs.repeat_hint')" wire:model="repeat" />
            @endunless
            <x-slot:footer>
                @if ($sessionId)
                    <x-ui.button variant="danger" wire:click="delete" wire:confirm="{{ __('clubs.confirm_delete') }}" class="md:mr-auto">{{ __('clubs.delete') }}</x-ui.button>
                @endif
                <x-ui.button variant="ghost" x-on:click="open = null">{{ __('students.actions.cancel') }}</x-ui.button>
                <x-ui.button wire:click="save">{{ __('students.actions.save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
