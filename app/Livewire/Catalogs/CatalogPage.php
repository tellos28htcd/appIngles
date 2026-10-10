<?php

namespace App\Livewire\Catalogs;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Models\School;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Página de catálogos académicos con pestañas. BaseCatalogs (Plataforma)
 * y SchoolCatalogs (Configuración) solo cambian qué catálogo administran.
 */
#[Layout('layouts.app')]
abstract class CatalogPage extends Component
{
    public const TABS = ['horarios', 'libros', 'actividades', 'clubes'];

    /** Catálogos que se administran en esta página (los de Configuración tienen sus propias pantallas). */
    public const ACADEMIC_CATALOGS = ['shifts', 'schedule_slots', 'books', 'lessons', 'activities', 'clubs'];

    /** @return list<string> */
    protected function tabs(): array
    {
        return self::TABS;
    }

    #[Locked]
    public ?int $schoolId = null;

    #[Url(as: 'pestana', except: 'horarios')]
    public string $tab = 'horarios';

    /** Cambia al incorporar novedades para volver a montar las pestañas. */
    public int $version = 0;

    public ?bool $reviewingUpdates = null;

    /** catálogo => IDs base elegidos para incorporar. @var array<string, list<string>> */
    public array $selectedUpdates = [];

    abstract protected function title(): string;

    abstract protected function subtitle(): string;

    public function updatedTab(): void
    {
        if (! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'horarios';
        }
    }

    public function reviewUpdates(): void
    {
        $this->authorizeSchool();

        $this->selectedUpdates = collect($this->pendingUpdates)
            ->map(fn ($items) => $items->pluck('id')->map(fn ($id) => (string) $id)->all())
            ->all();
        $this->reviewingUpdates = true;
    }

    public function incorporateUpdates(CopyBaseCatalogs $copy): void
    {
        $this->authorizeSchool();

        $selection = collect(self::ACADEMIC_CATALOGS)
            ->mapWithKeys(fn (string $catalog) => [$catalog => array_map('intval', $this->selectedUpdates[$catalog] ?? [])])
            ->all();

        $copied = $copy->handle(School::findOrFail($this->schoolId), $selection);

        $this->reviewingUpdates = null;
        $this->selectedUpdates = [];
        $this->version++;
        unset($this->pendingUpdates);

        $this->dispatch('toast', type: 'success', message: trans_choice('catalogs.updates.incorporated', $copied));
    }

    /** Novedades del catálogo base que la escuela aún no tiene. @return array<string, \Illuminate\Support\Collection> */
    #[Computed]
    public function pendingUpdates(): array
    {
        if ($this->schoolId === null) {
            return [];
        }

        $school = School::findOrFail($this->schoolId);

        return collect(CopyBaseCatalogs::CATALOGS)
            ->only(self::ACADEMIC_CATALOGS)
            ->map(fn (string $model) => CopyBaseCatalogs::pending($school, $model))
            ->filter(fn ($items) => $items->isNotEmpty())
            ->all();
    }

    private function authorizeSchool(): void
    {
        abort_if($this->schoolId === null, 404);
        Gate::authorize('manage-catalog', [$this->schoolId]);
    }

    public function render(): View
    {
        return view('livewire.catalogs.catalog-page', [
            'pageTitle' => $this->title(),
            'pageSubtitle' => $this->subtitle(),
            'tabs' => collect($this->tabs())->mapWithKeys(fn (string $tab) => [$tab => __("catalogs.tabs.$tab")])->all(),
            'updatesCount' => collect($this->pendingUpdates)->sum(fn ($items) => $items->count()),
        ])->title($this->title());
    }
}
