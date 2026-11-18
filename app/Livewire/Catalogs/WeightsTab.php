<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Lesson;
use App\Models\Practice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pestaña "Pesos newton" (tabla general, como el Excel del instituto): minutos que tarda el teacher en revisar cada
 * actividad de cada lección (Lesson 1–7, Check Point C1, Verb in context 1, Lesson 8–14, …), con su total en minutos
 * y horas. Lo que se cambia aplica a esa lección en todos los libros. Las casillas oscuras son actividades que no
 * están en el catálogo de Prácticas. La agenda balancea las aulas con estos pesos.
 */
class WeightsTab extends Component
{
    use ManagesCatalog;

    /** Pesos capturados: [número de lección][número de actividad] => peso. @var array<int, array<int, string>> */
    public array $weights = [];

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
        $this->fillWeights();
    }

    private function fillWeights(): void
    {
        $this->weights = $this->lessons->mapWithKeys(fn (Lesson $lesson) => [$lesson->number => collect($this->doneActivities[$lesson->number] ?? [])
            ->mapWithKeys(fn (int $number) => [$number => (string) ($lesson->weights()[$number] ?? '')])->all()])->all();
    }

    public function save(): void
    {
        $this->authorizeCatalog();
        $this->validate(['weights.*.*' => ['nullable', 'integer', 'min:0', 'max:999']], [], ['weights.*.*' => __('catalogs.weights.weight')]);

        // Cada lección (por número) en todos los libros del catálogo; solo las actividades que se hacen en ella.
        foreach ($this->catalog(Lesson::class)->whereIn('book_id', $this->books->modelKeys())->get() as $lesson) {
            $row = collect($this->weights[$lesson->number] ?? [])
                ->only($this->doneActivities[$lesson->number] ?? [])
                ->filter(fn ($weight) => $weight !== '' && $weight !== null)
                ->mapWithKeys(fn ($weight, $number) => [(string) $number => (int) $weight])->all();
            $lesson->update(['activity_weights' => (object) $row]);
        }

        $this->dispatch('toast', type: 'success', message: __('catalogs.weights.saved'));
    }

    /** @return Collection<int, Book> */
    #[Computed]
    public function books(): Collection
    {
        return $this->catalog(Book::class)->orderBy('level')->get();
    }

    /** Lecciones de la tabla general: las del primer libro, en su orden. @return Collection<int, Lesson> */
    #[Computed]
    public function lessons(): Collection
    {
        $book = $this->books->first();

        return $book === null ? new Collection : $this->catalog(Lesson::class)->where('book_id', $book->id)->orderBy('sort_order')->orderBy('number')->get();
    }

    /** Actividades que se hacen en cada lección (catálogo de Prácticas; sin él, las que tienen peso). @return array<int, list<int>> */
    #[Computed]
    public function doneActivities(): array
    {
        $practices = $this->catalog(Practice::class)->whereIn('lesson_id', $this->lessons->modelKeys())->where('is_active', true)->get(['lesson_id', 'activity_number']);
        $byLesson = $practices->groupBy('lesson_id');

        return $this->lessons->mapWithKeys(fn (Lesson $lesson) => [$lesson->number => $practices->isNotEmpty()
            ? $byLesson->get($lesson->id, collect())->pluck('activity_number')->map(fn ($number) => (int) $number)->sort()->values()->all()
            : $this->activities->pluck('number')->map(fn ($number) => (int) $number)->all()])->all();
    }

    /** @return Collection<int, Activity> */
    #[Computed]
    public function activities(): Collection
    {
        return $this->catalog(Activity::class)->where('is_active', true)->orderBy('number')->get();
    }

    /** Total de minutos de cada lección (suma de sus pesos). @return BaseCollection<int, int> */
    public function totals(): BaseCollection
    {
        return collect($this->weights)->map(fn (array $row) => (int) collect($row)->sum(fn ($weight) => is_numeric($weight) ? (int) $weight : 0));
    }

    public function render(): View
    {
        return view('livewire.catalogs.weights-tab', ['totals' => $this->totals()]);
    }
}
