<div class="grid gap-4 md:gap-6 xl:grid-cols-[minmax(0,360px)_minmax(0,1fr)]">
    {{-- Libros (niveles) --}}
    <x-catalog.section :title="__('catalogs.books.title')" :description="__('catalogs.books.description')"
                       add="editBook" :add-label="__('catalogs.books.add')" class="self-start">
        <ul class="flex flex-col gap-1 p-2">
            @forelse ($this->books as $book)
                <li wire:key="book-{{ $book->id }}" @class([
                    'flex items-center gap-2 rounded-lg pl-1 pr-2',
                    'bg-primary-50' => $book->id === $selectedBookId,
                    'opacity-60' => ! $book->is_active,
                ])>
                    <button type="button" wire:click="selectBook({{ $book->id }})" aria-pressed="{{ $book->id === $selectedBookId ? 'true' : 'false' }}"
                            class="flex min-h-12 min-w-0 flex-1 items-center gap-3 rounded-md px-2 text-left focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200">
                        <span class="flex size-9 flex-none items-center justify-center rounded-full bg-primary-600 font-display text-sm font-extrabold text-on-primary">L{{ $book->level }}</span>
                        <span class="flex min-w-0 flex-col">
                            <span @class(['truncate font-bold', 'text-primary-700' => $book->id === $selectedBookId])>{{ $book->name }}</span>
                            <span class="text-xs text-ink-500">{{ trans_choice('catalogs.books.lessons_count', $book->lessons_count) }}</span>
                        </span>
                    </button>
                    <x-catalog.row-actions :active="$book->is_active" :name="$book->name"
                                           edit="editBook({{ $book->id }})" toggle="toggleBook({{ $book->id }})" delete="confirmBookDeletion({{ $book->id }})" />
                </li>
            @empty
                <li><x-ui.empty-state icon="book" :title="__('catalogs.empty')" /></li>
            @endforelse
        </ul>
    </x-catalog.section>

    {{-- Lecciones del libro seleccionado --}}
    <x-catalog.section :title="$selectedBook ? __('catalogs.lessons.title_for', ['book' => $selectedBook->name]) : __('catalogs.lessons.title')"
                       :description="__('catalogs.lessons.description')"
                       :add="$selectedBook ? 'editLesson' : null" :add-label="__('catalogs.lessons.add')">
        @if (! $selectedBook)
            <x-ui.empty-state icon="book" :title="__('catalogs.lessons.select_book')" />
        @elseif ($this->lessons->isEmpty())
            <x-ui.empty-state icon="book" :title="__('catalogs.empty')" />
        @else
            @php($arrow = 'flex size-8 items-center justify-center rounded-[10px] text-ink-700 hover:bg-surface-2 disabled:opacity-30 disabled:hover:bg-transparent focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary-200')
            <table class="w-full text-left">
                <thead class="bg-surface text-xs uppercase tracking-wide text-ink-500">
                    <tr>
                        <th scope="col" class="w-20 px-4 py-2.5 font-bold md:px-5"><span class="sr-only">{{ __('catalogs.fields.order') }}</span></th>
                        <th scope="col" class="w-20 px-2 py-2.5 text-right font-bold" title="{{ __('catalogs.fields.activity_number') }}">{{ __('catalogs.fields.number_short') }}</th>
                        <th scope="col" class="px-3 py-2.5 font-bold">{{ __('catalogs.fields.lesson') }}</th>
                        <th scope="col" class="hidden px-3 py-2.5 font-bold md:table-cell">{{ __('catalogs.fields.type') }}</th>
                        <th scope="col" class="px-4 py-2.5 md:px-5"><span class="sr-only">{{ __('catalogs.fields.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($this->lessons as $lesson)
                        <tr wire:key="lesson-{{ $lesson->id }}" @class(['hover:bg-surface', 'opacity-60' => ! $lesson->is_active])>
                            <td class="px-4 py-1.5 md:px-5">
                                <div class="flex gap-0.5">
                                    <button type="button" wire:click="moveLesson({{ $lesson->id }}, -1)" @disabled($loop->first) class="{{ $arrow }}" aria-label="{{ __('catalogs.actions.up', ['name' => $lesson->name]) }}">
                                        <x-ui.icon name="chevron-down" class="size-4 rotate-180" />
                                    </button>
                                    <button type="button" wire:click="moveLesson({{ $lesson->id }}, 1)" @disabled($loop->last) class="{{ $arrow }}" aria-label="{{ __('catalogs.actions.down', ['name' => $lesson->name]) }}">
                                        <x-ui.icon name="chevron-down" class="size-4" />
                                    </button>
                                </div>
                            </td>
                            <td class="px-2 py-1.5 text-right font-mono text-sm tabular-nums">{{ $lesson->number }}</td>
                            <td class="px-3 py-1.5 font-semibold">
                                {{ $lesson->name }}
                                <span class="block text-xs font-normal text-ink-500 md:hidden">{{ $lesson->type->label() }}</span>
                            </td>
                            <td class="hidden px-3 py-1.5 md:table-cell">
                                <x-ui.badge :variant="$lesson->type === \App\Enums\LessonType::Lesson ? 'neutral' : 'info'">{{ $lesson->type->label() }}</x-ui.badge>
                            </td>
                            <td class="px-4 py-1.5 md:px-5">
                                <x-catalog.row-actions :active="$lesson->is_active" :name="$lesson->name"
                                                       edit="editLesson({{ $lesson->id }})" toggle="toggleLesson({{ $lesson->id }})" delete="confirmLessonDeletion({{ $lesson->id }})" />
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-catalog.section>

    <x-ui.modal wire:model="editingBook" :title="__($bookId ? 'catalogs.books.edit' : 'catalogs.books.add')">
        <div class="grid gap-4 sm:grid-cols-[120px_minmax(0,1fr)]">
            <x-ui.input name="bookLevel" type="number" :label="__('catalogs.fields.level')" wire:model="bookLevel" min="1" class="tabular-nums" required />
            <x-ui.input name="bookName" :label="__('catalogs.fields.name')" wire:model="bookName" maxlength="80" required />
        </div>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="saveBook">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    <x-ui.modal wire:model="editingLesson" :title="__($lessonId ? 'catalogs.lessons.edit' : 'catalogs.lessons.add')">
        <div class="grid gap-4 sm:grid-cols-[140px_minmax(0,1fr)]">
            <x-ui.input name="lessonNumber" type="number" :label="__('catalogs.fields.activity_number')" wire:model="lessonNumber" min="1" class="tabular-nums" required />
            <x-ui.input name="lessonName" :label="__('catalogs.fields.name')" wire:model="lessonName" maxlength="120" required />
        </div>
        <x-ui.select name="lessonType" :label="__('catalogs.fields.type')" :options="$lessonTypes" wire:model="lessonType" required />
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="open = null">{{ __('catalogs.actions.cancel') }}</x-ui.button>
            <x-ui.button wire:click="saveLesson">{{ __('catalogs.actions.save') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    @include('livewire.catalogs.partials.confirm-delete')
</div>
