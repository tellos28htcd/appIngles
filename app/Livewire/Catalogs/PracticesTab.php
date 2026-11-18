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
 * práctica (como se ve en la agenda) y sus puntos acumulados (para el Kardex).
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
