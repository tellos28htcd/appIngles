{{-- Escritorio: tabla --}}
<div class="hidden overflow-x-auto lg:block">
    <table class="w-full text-left">
        <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
            <tr>
                <th scope="col" class="whitespace-nowrap px-4 py-3 font-bold">{{ __('audit.columns.date') }}</th>
                @if ($platform)
                    <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.school') }}</th>
                @endif
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.email') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.login_event') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.ip') }}</th>
                <th scope="col" class="px-4 py-3 font-bold">{{ __('audit.columns.device') }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($logs as $log)
                <tr wire:key="login-{{ $log->id }}" class="hover:bg-surface">
                    <td class="whitespace-nowrap px-4 py-3 text-sm tabular-nums text-ink-700">{{ $log->created_at->timezone($timezone)->translatedFormat('j M Y H:i:s') }}</td>
                    @if ($platform)
                        <td class="px-4 py-3 text-sm text-ink-700">
                            @if ($log->school)
                                <span class="block font-mono text-xs text-ink-500">{{ $log->school->code }}</span>
                                <span class="block max-w-[10rem] leading-snug">{{ $log->school->name }}</span>
                            @else
                                <span class="text-ink-500">{{ $log->user ? __('audit.platform') : __('audit.unknown_user') }}</span>
                            @endif
                        </td>
                    @endif
                    <td class="px-4 py-3 text-sm">
                        <span class="block max-w-[15rem] truncate font-semibold">{{ $log->email }}</span>
                        @if ($log->user)
                            <span class="block max-w-[15rem] truncate text-xs text-ink-500">{{ $log->user->name }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :variant="$log->event->badge()">{{ $log->event->label() }}</x-ui.badge></td>
                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-ink-700">{{ $log->ip_address ?? '—' }}</td>
                    <td class="px-4 py-3 text-xs text-ink-500"><span class="block max-w-[16rem] truncate" title="{{ $log->user_agent }}">{{ $log->user_agent ?: '—' }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Móvil y tablet: tarjetas --}}
<ul class="divide-y divide-line lg:hidden">
    @foreach ($logs as $log)
        <li wire:key="login-card-{{ $log->id }}" class="flex flex-col gap-1.5 p-4">
            <span class="flex items-center justify-between gap-3">
                <x-ui.badge :variant="$log->event->badge()">{{ $log->event->label() }}</x-ui.badge>
                <span class="text-xs tabular-nums text-ink-500">{{ $log->created_at->timezone($timezone)->translatedFormat('j M Y H:i') }}</span>
            </span>
            <span class="truncate font-bold">{{ $log->email }}</span>
            <span class="text-sm text-ink-700">
                {{ $log->user?->name ?? __('audit.unknown_user') }}@if ($platform && $log->school) · {{ $log->school->name }}@endif
                · <span class="font-mono text-xs">{{ $log->ip_address }}</span>
            </span>
        </li>
    @endforeach
</ul>
