<?php

namespace App\Livewire\Catalogs;

use App\Enums\LessonType;
use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Book;
use App\Models\Lesson;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Libros y lecciones": cada libro (nivel) con sus lecciones. */
class BooksTab extends Component
{
    use ManagesCatalog;

    public ?int $selectedBookId = null;

    // Libro
    public ?bool $editingBook = null;

    public ?int $bookId = null;

    public ?int $bookLevel = null;

    public string $bookName = '';

    // Lección
    public ?bool $editingLesson = null;

    public ?int $lessonId = null;

    public ?int $lessonNumber = null;

    public string $lessonName = '';

    public string $lessonType = 'lesson';

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
        $this->selectedBookId = $this->books->first()?->id;
    }

    public function selectBook(int $id): void
    {
        $this->selectedBookId = $this->findInCatalog(Book::class, $id)->id;
    }

    public function editBook(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $book = $id ? $this->findInCatalog(Book::class, $id) : null;
        $this->bookId = $book?->id;
        $this->bookLevel = $book?->level ?? (int) $this->catalog(Book::class)->max('level') + 1;
        $this->bookName = (string) $book?->name;
        $this->editingBook = true;
    }

    public function saveBook(): void
    {
        $this->authorizeCatalog();

        $data = $this->validate([
            'bookLevel' => ['required', 'integer', 'min:1', 'max:99', $this->uniqueInCatalog('books', 'level', $this->bookId)],
            'bookName' => ['required', 'string', 'max:80'],
        ], attributes: [
            'bookLevel' => Str::lower(__('catalogs.fields.level')),
            'bookName' => Str::lower(__('catalogs.fields.name')),
        ]);

        $book = $this->bookId ? $this->findInCatalog(Book::class, $this->bookId) : new Book([
            'school_id' => $this->schoolId,
            'is_active' => true,
        ]);
        $book->fill(['level' => $data['bookLevel'], 'name' => trim($data['bookName'])])->save();

        $this->selectedBookId = $book->id;
        $this->editingBook = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function editLesson(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $book = $this->findInCatalog(Book::class, $this->selectedBookId);
        $lesson = $id ? $this->catalog(Lesson::class)->where('book_id', $book->id)->findOrFail($id) : null;

        $this->lessonId = $lesson?->id;
        $this->lessonNumber = $lesson?->number ?? (int) $book->lessons()->max('number') + 1;
        $this->lessonName = (string) $lesson?->name;
        $this->lessonType = $lesson?->type->value ?? LessonType::Lesson->value;
        $this->editingLesson = true;
    }

    public function saveLesson(): void
    {
        $this->authorizeCatalog();

        $book = $this->findInCatalog(Book::class, $this->selectedBookId);

        $data = $this->validate([
            'lessonNumber' => [
                'required', 'integer', 'min:1', 'max:999',
                Rule::unique('lessons', 'number')->where('book_id', $book->id)->ignore($this->lessonId),
            ],
            'lessonName' => ['required', 'string', 'max:120'],
            'lessonType' => ['required', Rule::enum(LessonType::class)],
        ], attributes: [
            'lessonNumber' => Str::lower(__('catalogs.fields.activity_number')),
            'lessonName' => Str::lower(__('catalogs.fields.name')),
            'lessonType' => Str::lower(__('catalogs.fields.type')),
        ]);

        $lesson = $this->lessonId
            ? $this->catalog(Lesson::class)->where('book_id', $book->id)->findOrFail($this->lessonId)
            : new Lesson([
                'school_id' => $this->schoolId,
                'book_id' => $book->id,
                'sort_order' => (int) $book->lessons()->max('sort_order') + 10,
                'is_active' => true,
            ]);
        $lesson->fill(['number' => $data['lessonNumber'], 'name' => trim($data['lessonName']), 'type' => $data['lessonType']])->save();

        $this->editingLesson = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    /** Sube o baja una lección dentro de su libro. */
    public function moveLesson(int $id, int $direction): void
    {
        $this->authorizeCatalog();

        $lessons = $this->lessons->values();
        $index = $lessons->search(fn (Lesson $lesson) => $lesson->id === $id);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

        if ($target === null || ! isset($lessons[$target])) {
            return;
        }

        [$current, $other] = [$lessons[$index], $lessons[$target]];
        [$currentOrder, $otherOrder] = [$current->sort_order, $other->sort_order];
        $current->update(['sort_order' => $otherOrder]);
        $other->update(['sort_order' => $currentOrder]);

        unset($this->lessons);
    }

    public function toggleBook(int $id): void
    {
        $this->toggleActive(Book::class, $id);
    }

    public function toggleLesson(int $id): void
    {
        $this->toggleActive(Lesson::class, $id);
    }

    public function confirmBookDeletion(int $id): void
    {
        $this->askDeletion(Book::class, $id);
    }

    public function confirmLessonDeletion(int $id): void
    {
        $this->askDeletion(Lesson::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Book' => Book::class, 'Lesson' => Lesson::class]);

        if (! $this->catalog(Book::class)->whereKey($this->selectedBookId)->exists()) {
            $this->selectedBookId = $this->books->first()?->id;
        }
    }

    /** @return Collection<int, Book> */
    #[Computed]
    public function books(): Collection
    {
        return $this->catalog(Book::class)->withCount('lessons')->orderBy('level')->get();
    }

    /** @return Collection<int, Lesson> */
    #[Computed]
    public function lessons(): Collection
    {
        return $this->selectedBookId
            ? $this->catalog(Lesson::class)->where('book_id', $this->selectedBookId)->orderBy('sort_order')->get()
            : new Collection;
    }

    public function render(): View
    {
        return view('livewire.catalogs.books-tab', [
            'selectedBook' => $this->books->firstWhere('id', $this->selectedBookId),
            'lessonTypes' => collect(LessonType::cases())->mapWithKeys(fn (LessonType $type) => [$type->value => $type->label()])->all(),
        ]);
    }
}
