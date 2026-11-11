<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Lesson;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pestaña "Pesos newton": el peso de cada actividad en cada lección (como la tabla del Excel: lecciones × BA, 1P, M,
 * WS, 2P, WP, SP, PR). La agenda balancea las aulas con estos pesos y la ruta de avance salta las actividades vacías.
 */
class WeightsTab extends Component
{
    use ManagesCatalog;

    public ?int $bookId = null;

    /** Pesos capturados: [id de la lección][número de actividad] => peso ('' = no se hace). @var array<int, array<int, string>> */
    public array $weights = [];

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
        $this->selectBook($this->books->first()?->id);
    }

    public function selectBook(?int $id): void
    {
        $this->authorizeCatalog();
        $this->bookId = $id === null ? null : $this->findInCatalog(Book::class, $id)->id;
        unset($this->lessons);
        $this->resetValidation();
        $this->weights = $this->lessons->mapWithKeys(fn (Lesson $lesson) => [$lesson->id => $this->activities->mapWithKeys(fn (Activity $activity) => [
            $activity->number => (string) ($lesson->weights()[$activity->number] ?? ''),
        ])->all()])->all();
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->validate(['weights.*.*' => ['nullable', 'integer', 'min:0', 'max:999']], [], ['weights.*.*' => __('catalogs.weights.weight')]);

        foreach ($this->lessons as $lesson) {
            $row = collect($this->weights[$lesson->id] ?? [])->filter(fn ($weight) => $weight !== '' && $weight !== null)
                ->mapWithKeys(fn ($weight, $number) => [(string) $number => (int) $weight])->all();
            $lesson->update(['activity_weights' => $row]);
        }

        $this->dispatch('toast', type: 'success', message: __('catalogs.weights.saved'));
    }

    /** @return Collection<int, Book> */
    #[Computed]
    public function books(): Collection
    {
        return $this->catalog(Book::class)->orderBy('level')->get();
    }

    /** @return Collection<int, Lesson> */
    #[Computed]
    public function lessons(): Collection
    {
        return $this->bookId === null ? new Collection : $this->catalog(Lesson::class)->where('book_id', $this->bookId)->orderBy('sort_order')->orderBy('number')->get();
    }

    /** @return Collection<int, Activity> */
    #[Computed]
    public function activities(): Collection
    {
        return $this->catalog(Activity::class)->where('is_active', true)->orderBy('number')->get();
    }

    public function render(): View
    {
        return view('livewire.catalogs.weights-tab');
    }
}
