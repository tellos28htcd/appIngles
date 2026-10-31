{{-- Leyenda del calendario de la agenda. --}}
<ul class="ml-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-xs font-semibold text-ink-700" aria-label="{{ __('agenda.grid.legend') }}">
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-success-50 ring-1 ring-success-200"></span>{{ __('agenda.grid.legend_session') }}</li>
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-info-50 ring-1 ring-info-200"></span>{{ __('agenda.grid.legend_online') }}</li>
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-warning-50 ring-1 ring-warning-200"></span>{{ __('agenda.grid.waitlist') }}</li>
    <li class="flex items-center gap-1.5"><span class="size-3 rounded-sm bg-danger-50 ring-1 ring-danger-200"></span>{{ __('agenda.grid.full') }}</li>
    <li class="text-ink-500">{{ __('agenda.grid.legend_hint') }}</li>
</ul>
