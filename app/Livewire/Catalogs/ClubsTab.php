<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Book;
use App\Models\Club;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** Pestaña "Clubes": cada club con sus horas por nivel (libro). */
class ClubsTab extends Component
{
    use ManagesCatalog;

    public ?bool $editing = null;

    public ?int $clubId = null;

    public string $name = '';

    public string $description = '';

    /** book_id => horas ('' = no se ofrece en ese nivel). @var array<string, string|float|null> */
    public array $hours = [];

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    public function edit(?int $id = null): void
    {
        $this->authorizeCatalog();
        $this->resetValidation();

        $club = $id ? $this->findInCatalog(Club::class, $id)->load('books') : null;
        $this->clubId = $club?->id;
        $this->name = (string) $club?->name;
        $this->description = (string) $club?->description;
        $this->hours = $this->books->mapWithKeys(fn (Book $book) => [
            (string) $book->id => $club?->books->firstWhere('id', $book->id)?->pivot->hours ?? '',
        ])->all();
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorizeCatalog();

        $bookIds = $this->books->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->hours = array_intersect_key($this->hours, array_flip($bookIds));

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', $this->uniqueInCatalog('clubs', 'name', $this->clubId)],
            'description' => ['nullable', 'string', 'max:255'],
            'hours' => ['array'],
            'hours.*' => ['nullable', 'numeric', 'min:0.5', 'max:999'],
        ], attributes: [
            'name' => Str::lower(__('catalogs.fields.name')),
            'description' => Str::lower(__('catalogs.fields.description')),
            'hours.*' => Str::lower(__('catalogs.fields.hours')),
        ]);

        DB::transaction(function () use ($data): void {
            $club = $this->clubId ? $this->findInCatalog(Club::class, $this->clubId) : new Club([
                'school_id' => $this->schoolId,
                'sort_order' => (int) $this->catalog(Club::class)->max('sort_order') + 10,
                'is_active' => true,
            ]);
            $club->fill(['name' => trim($data['name']), 'description' => trim((string) $data['description']) ?: null])->save();

            $club->books()->sync(collect($data['hours'] ?? [])
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->mapWithKeys(fn ($value, $bookId) => [(int) $bookId => ['hours' => (float) $value]])
                ->all());
        });

        $this->editing = null;
        $this->dispatch('toast', type: 'success', message: __('catalogs.messages.saved'));
    }

    public function toggle(int $id): void
    {
        $this->toggleActive(Club::class, $id);
    }

    public function confirmDeletion(int $id): void
    {
        $this->askDeletion(Club::class, $id);
    }

    public function delete(): void
    {
        $this->deleteConfirmed(['Club' => Club::class]);
    }

    /** @return Collection<int, Book> */
    #[Computed]
    public function books(): Collection
    {
        return $this->catalog(Book::class)->orderBy('level')->get(['id', 'level', 'name']);
    }

    /** @return Collection<int, Club> */
    #[Computed]
    public function clubs(): Collection
    {
        return $this->catalog(Club::class)->with('books:id')->orderBy('sort_order')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.clubs-tab');
    }
}
