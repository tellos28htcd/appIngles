<div>
    <x-catalog.section :title="__('catalogs.weights.title')" :description="__('catalogs.weights.description')">
        {{-- Libro --}}
        <div class="flex flex-wrap gap-2 border-b border-line px-4 py-3 md:px-5" role="group" aria-label="{{ __('catalogs.books.title') }}">
            @foreach ($this->books as $book)
                <button type="button" wire:click="selectBook({{ $book->id }})" aria-pressed="{{ $book->id === $bookId ? 'true' : 'false' }}" wire:key="weights-book-{{ $book->id }}"
                        @class(['inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-bold transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200',
                            'border-primary-600 bg-primary-600 text-on-primary' => $book->id === $bookId,
                            'border-line-strong bg-white text-ink-700 hover:bg-surface' => $book->id !== $bookId])>
                    L{{ $book->level }} · {{ $book->name }}
                </button>
            @endforeach
        </div>

        @if ($this->lessons->isEmpty())
            <x-ui.empty-state icon="book" :title="__('catalogs.weights.select_book')" />
        @else
            {{-- Lecciones × actividades; vacío = no se hace (se ve oscuro, como en el Excel). --}}
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-primary-700 text-on-primary">
                        <tr>
                            <th scope="col" class="px-4 py-2 text-left font-bold">{{ __('catalogs.weights.lesson') }}</th>
                            @foreach ($this->activities as $activity)
                                <th scope="col" class="px-1 py-2 text-center font-bold" title="{{ $activity->description }}">
                                    <span class="block text-[11px] font-semibold opacity-80">{{ $activity->number }}</span>
                                    {{ $activity->code }}@if ($activity->is_one_to_one)<span class="block text-[10px] font-semibold opacity-80">{{ __('catalogs.weights.one_to_one') }}</span>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($this->lessons as $lesson)
                            <tr wire:key="weights-lesson-{{ $lesson->id }}" @class(['bg-row-alt' => $lesson->type !== \App\Enums\LessonType::Lesson, 'opacity-60' => ! $lesson->is_active])>
                                <th scope="row" class="whitespace-nowrap px-4 py-1.5 text-left font-semibold">
                                    {{ $lesson->name }}
                                    @if ($lesson->type !== \App\Enums\LessonType::Lesson)<span class="block text-xs font-normal text-ink-500">{{ $lesson->type->label() }}</span>@endif
                                </th>
                                @foreach ($this->activities as $activity)
                                    @php($empty = ($weights[$lesson->id][$activity->number] ?? '') === '')
                                    <td class="px-1 py-1">
                                        <input type="number" min="0" max="999" inputmode="numeric" wire:model.blur="weights.{{ $lesson->id }}.{{ $activity->number }}"
                                               aria-label="{{ __('catalogs.weights.weight_of', ['activity' => $activity->code, 'lesson' => $lesson->name]) }}"
                                               placeholder="—" title="{{ $empty ? __('catalogs.weights.not_done') : '' }}"
                                               @class(['h-9 w-full min-w-14 rounded-md border px-2 text-right tabular-nums focus:border-primary-500 focus:outline-none focus:ring-4 focus:ring-primary-100',
                                                   'border-line-strong bg-white font-bold' => ! $empty,
                                                   'border-ink-700 bg-ink-700 text-white placeholder:text-ink-400' => $empty,
                                                   'border-danger-500' => $errors->has("weights.{$lesson->id}.{$activity->number}")])>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($errors->any())
                <p role="alert" class="px-4 pt-3 text-sm font-semibold text-danger-700 md:px-5">{{ $errors->first() }}</p>
            @endif
            <div class="flex justify-end border-t border-line px-4 py-3 md:px-5">
                <x-ui.button wire:click="save" wire:loading.attr="disabled">{{ __('catalogs.weights.save') }}</x-ui.button>
            </div>
        @endif
    </x-catalog.section>
</div>
