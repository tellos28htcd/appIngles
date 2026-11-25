<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('kardex.my_title')" :subtitle="__('kardex.my_subtitle')" />

    @if (! $student)
        <x-ui.empty-state icon="book" :title="__('agenda.no_student')" />
    @else
        {{-- Avance: práctica que sigue, puntos acumulados y prácticas calificadas. --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white p-4 shadow-xs">
                <span class="text-sm font-semibold text-ink-500">{{ __('kardex.next') }}</span>
                <span class="font-display text-h3 font-bold tabular-nums text-primary-700">{{ $summary['next']['label'] ?? '—' }}</span>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white p-4 shadow-xs">
                <span class="text-sm font-semibold text-ink-500">{{ __('kardex.points') }}</span>
                <span class="font-display text-h3 font-bold tabular-nums">{{ number_format($summary['points']) }}</span>
            </div>
            <div class="flex flex-col gap-1 rounded-xl border border-line bg-white p-4 shadow-xs">
                <span class="text-sm font-semibold text-ink-500">{{ __('kardex.progress') }}</span>
                <span class="font-display text-h3 font-bold tabular-nums">{{ __('kardex.progress_value', ['graded' => $summary['graded'], 'total' => $summary['total']]) }}</span>
            </div>
        </div>

        @if ($summary['pending'])
            <p class="flex items-start gap-2 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm font-semibold text-warning-700">
                <x-ui.icon name="alert" class="mt-px size-4 flex-none" />
                <span>{{ __('agenda.presentation.student_pending', ['practice' => $summary['pending']->practiceLabel()]) }}</span>
            </p>
        @endif

        <section class="flex flex-col rounded-xl border border-line bg-white shadow-xs">
            <h2 class="border-b border-line p-4 font-display text-lg font-bold">{{ __('kardex.my_grades') }}</h2>
            @if ($summary['entries']->isEmpty())
                <x-ui.empty-state icon="book" :title="__('kardex.my_empty')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left">
                        <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-bold">{{ __('agenda.date') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-bold">{{ __('agenda.activity') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-bold">{{ __('kardex.grade') }}</th>
                                <th scope="col" class="px-4 py-2.5 font-bold">{{ __('agenda.teacher') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($summary['entries'] as $entry)
                                <tr wire:key="entry-{{ $entry->id }}" class="hover:bg-surface">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm tabular-nums">{{ $entry->session_date->translatedFormat('j M Y') }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 font-bold tabular-nums">
                                        @if ($entry->kind === \App\Enums\BookingKind::Club)
                                            <span class="text-sm uppercase text-ink-700">{{ $entry->kind->label() }}</span>
                                        @endif
                                        {{ $entry->practiceLabel() }}
                                    </td>
                                    <td class="px-4 py-2">
                                        @if ($entry->is_pending)
                                            <span class="rounded-full bg-warning-50 px-2.5 py-0.5 text-sm font-bold text-warning-700">{{ __('kardex.pending_presentation') }}</span>
                                        @else
                                            <span class="rounded-full bg-success-50 px-2.5 py-0.5 text-sm font-bold text-success-700">{{ $entry->grade?->code }} · {{ $entry->grade?->name }}</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm font-semibold">{{ $entry->teacher?->panelName() ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</div>
