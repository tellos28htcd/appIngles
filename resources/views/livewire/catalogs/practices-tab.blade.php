<div>
    <x-catalog.section :title="__('catalogs.practices.title')" :description="__('catalogs.practices.description')" class="max-w-3xl">
        @if ($this->practices->isEmpty())
            <x-ui.empty-state icon="book" :title="__('catalogs.practices.empty')" />
        @else
            <div class="max-h-[70vh] overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-surface text-xs uppercase tracking-wide text-ink-500">
                        <tr>
                            <th scope="col" class="w-20 px-4 py-2.5 text-right font-bold md:px-5">{{ __('catalogs.practices.order') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-bold">{{ __('catalogs.practices.practice') }}</th>
                            <th scope="col" class="px-3 py-2.5 font-bold">{{ __('catalogs.practices.book') }}</th>
                            <th scope="col" class="w-32 px-4 py-2.5 text-right font-bold md:px-5">{{ __('catalogs.practices.points') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->practices as $practice)
                            <tr wire:key="practice-{{ $practice->id }}" @class(['bg-row-alt' => $practice->lesson?->type !== \App\Enums\LessonType::Lesson, 'opacity-60' => ! $practice->is_active])>
                                <td class="px-4 py-1.5 text-right font-mono tabular-nums md:px-5">{{ $practice->sort_order }}</td>
                                <td class="px-3 py-1.5 font-bold tabular-nums">
                                    {{ $practice->lesson ? \App\Models\Practice::labelFor((int) $practice->book?->level, $practice->lesson, $practice->activity_number, $practice->name, $this->codes) : '—' }}
                                </td>
                                <td class="px-3 py-1.5 text-ink-700">{{ $practice->book?->name }}</td>
                                <td class="px-4 py-1.5 text-right tabular-nums md:px-5">{{ $practice->points }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-catalog.section>
</div>
