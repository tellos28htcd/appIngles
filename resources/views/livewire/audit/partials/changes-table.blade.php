{{-- Escritorio: tabla --}}
<div class="hidden overflow-x-auto lg:block">
    <table class="w-full text-left">
        <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
            <tr>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">{{ __('audit.columns.date') }}</th>
                @if ($platform)
                    <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.school') }}</th>
                @endif
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.user') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.module') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.event') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.subject') }}</th>
                <th scope="col" class="px-4 py-3 text-right font-bold"><span class="sr-only">{{ __('audit.columns.actions') }}</span></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($logs as $log)
                <tr wire:key="audit-{{ $log->id }}" class="hover:bg-surface">
                    <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-ink-700">{{ $log->created_at->timezone($timezone)->translatedFormat('j M Y H:i') }}</td>
                    @if ($platform)
                        <td class="px-4 py-3 text-sm text-ink-700">
                            @if ($log->school)
                                <span class="block font-mono text-xs text-ink-500">{{ $log->school->code }}</span>
                                <span class="block max-w-[10rem] leading-snug">{{ $log->school->name }}</span>
                            @else
                                <span class="text-ink-500">{{ __('audit.platform') }}</span>
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 text-sm">
                        @if ($log->user)
                            <span class="block max-w-[13rem] font-semibold leading-snug [overflow-wrap:anywhere]">{{ $log->user->name }}</span>
                            <span class="block max-w-[13rem] truncate text-xs text-ink-500">{{ $log->user->email }}</span>
                        @else
                            <span class="text-ink-500">{{ __('audit.system_actor') }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $log->moduleLabel() }}</td>
                    <td class="px-4 py-3"><x-ui.badge :variant="$log->eventBadge()">{{ $log->eventLabel() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm"><span class="block max-w-[14rem] leading-snug [overflow-wrap:anywhere]">{{ $log->subject() }}</span></td>
                    <td class="w-px px-4 py-3 text-right">
                        <button type="button" wire:click="showDetail({{ $log->id }})" title="{{ __('audit.actions.view') }}"
                                aria-label="{{ __('audit.actions.view_of', ['subject' => $log->subject()]) }}"
                                class="inline-flex size-9 items-center justify-center rounded-md text-ink-700 hover:bg-primary-50 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                            <x-ui.icon name="eye" class="size-[18px]" />
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Móvil y tablet: tarjetas --}}
<ul class="divide-y divide-line lg:hidden">
    @foreach ($logs as $log)
        <li wire:key="audit-card-{{ $log->id }}">
            <button type="button" wire:click="showDetail({{ $log->id }})"
                    class="flex w-full flex-col gap-1.5 p-4 text-left hover:bg-surface focus-visible:bg-surface focus-visible:outline-none">
                <span class="flex items-center justify-between gap-3">
                    <x-ui.badge :variant="$log->eventBadge()">{{ $log->eventLabel() }}</x-ui.badge>
                    <span class="text-xs tabular-nums text-ink-500">{{ $log->created_at->timezone($timezone)->translatedFormat('j M Y H:i') }}</span>
                </span>
                <span class="font-bold leading-snug [overflow-wrap:anywhere]">{{ $log->moduleLabel() }} · {{ $log->subject() }}</span>
                <span class="text-sm text-ink-700">
                    {{ $log->user?->name ?? __('audit.system_actor') }}@if ($platform) · {{ $log->school?->name ?? __('audit.platform') }}@endif
                </span>
            </button>
        </li>
    @endforeach
</ul>
