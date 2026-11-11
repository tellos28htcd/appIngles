{{-- Leyenda del calendario de la agenda. En Agenda, "Lista de espera" despliega quiénes están en ella el día elegido. --}}
@php($waitlist = $waitlist ?? null)
<ul class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-semibold text-ink-700" aria-label="{{ __('agenda.grid.legend') }}">
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-success-50 ring-1 ring-success-200"></span>{{ __('agenda.grid.legend_session') }}</li>
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-info-50 ring-1 ring-info-200"></span>{{ __('agenda.grid.legend_online') }}</li>
    @if ($waitlist === null)
        <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-warning-50 ring-1 ring-warning-200"></span>{{ __('agenda.grid.waitlist') }}</li>
    @else
        <li class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
            <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="agenda-waitlist"
                    class="flex items-center gap-1.5 rounded-md px-1.5 py-0.5 hover:bg-warning-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <span class="size-3 rounded-sm bg-warning-50 ring-1 ring-warning-200"></span>{{ __('agenda.grid.waitlist') }}
                <span class="rounded-full bg-warning-100 px-1.5 text-xs font-bold tabular-nums text-warning-700">{{ $waitlist->count() }}</span>
                <x-ui.icon name="chevron-down" class="size-4" />
            </button>
            <div id="agenda-waitlist" x-show="open" x-cloak x-transition.opacity
                 class="absolute right-0 z-50 mt-1 max-h-80 w-[32rem] max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-line bg-white p-2 shadow-pop">
                @if ($waitlist->isEmpty())
                    <p class="px-2 py-1.5 text-sm font-normal text-ink-500">{{ __('agenda.waitlist_empty') }}</p>
                @else
                    <ul class="flex flex-col divide-y divide-line">
                        @foreach ($waitlist as $waiting)
                            <li wire:key="legend-waiting-{{ $waiting->id }}" class="flex items-baseline justify-between gap-3 px-2 py-1.5">
                                <span class="font-bold text-ink-900 sm:whitespace-nowrap">{{ $waiting->student?->fullName() }}</span>
                                <span class="whitespace-nowrap text-xs tabular-nums text-ink-500">{{ $waiting->date->translatedFormat('j M') }} · {{ $waiting->slot?->range() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </li>
    @endif
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-danger-50 ring-1 ring-danger-200"></span>{{ __('agenda.grid.full') }}</li>
    <li class="text-ink-500">{{ __('agenda.grid.legend_hint') }}</li>
</ul>
