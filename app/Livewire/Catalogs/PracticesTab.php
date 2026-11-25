<?php

namespace App\Livewire\Catalogs;

use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\Activity;
use App\Models\Practice;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pestaña "Prácticas" (consulta): la ruta de avance del Excel Cat_Practicas.xlsx en su orden, con el nombre de cada
 * práctica (como se ve en la agenda) y sus puntos acumulados (para el Kardex). Se marca cuál es presentación (A7 de
 * las lecciones 15 y 17): se agenda como Presentación y puede quedar pendiente en el Kardex.
 */
class PracticesTab extends Component
{
    use ManagesCatalog;

    public function mount(?int $schoolId): void
    {
        $this->schoolId = $schoolId;
        $this->authorizeCatalog();
    }

    /** @return Collection<int, Practice> */
    #[Computed]
    public function practices(): Collection
    {
        return $this->catalog(Practice::class)->with(['book', 'lesson'])->orderBy('sort_order')->orderBy('id')->get();
    }

    /** Marca o desmarca la práctica como presentación. */
    public function togglePresentation(int $practiceId): void
    {
        $this->authorizeCatalog();
        $practice = $this->findInCatalog(Practice::class, $practiceId);
        $practice->update(['is_presentation' => ! $practice->is_presentation]);

        unset($this->practices);
        $this->dispatch('toast', type: 'success', message: __($practice->is_presentation ? 'catalogs.practices.marked' : 'catalogs.practices.unmarked'));
    }

    /** @return array<int, string> */
    #[Computed]
    public function codes(): array
    {
        return $this->catalog(Activity::class)->pluck('code', 'number')->all();
    }

    public function render(): View
    {
        return view('livewire.catalogs.practices-tab');
    }
}
