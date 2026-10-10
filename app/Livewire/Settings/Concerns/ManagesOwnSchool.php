<?php

namespace App\Livewire\Settings\Concerns;

use App\Actions\Catalogs\CopyBaseCatalogs;
use App\Livewire\Catalogs\Concerns\ManagesCatalog;
use App\Models\School;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Pantallas de Configuración. Funcionan de dos formas:
 * - Página propia: siempre sobre la escuela del usuario (su administrador).
 *   El Super Admin no tiene escuela: se le envía a Escuelas.
 * - Pestaña incrustada en Plataforma → Catálogos base (schoolId nulo).
 */
trait ManagesOwnSchool
{
    use ManagesCatalog;

    #[Locked]
    public bool $embedded = false;

    protected function mountCatalogContext(?int $schoolId, bool $embedded): bool
    {
        if ($embedded) {
            $this->embedded = true;
            $this->schoolId = $schoolId;
            $this->authorizeCatalog();

            return true;
        }

        return $this->mountOwnSchool();
    }

    /** Modelo del catálogo que administra la pantalla (para las novedades del catálogo base). */
    abstract protected function catalogModel(): string;

    /** Registros nuevos del catálogo base que la escuela aún no tiene. */
    #[Computed]
    public function baseUpdates(): Collection
    {
        if ($this->schoolId === null || $this->embedded) {
            return new Collection;
        }

        return CopyBaseCatalogs::pending(School::findOrFail($this->schoolId), $this->catalogModel());
    }

    public function incorporateBaseUpdates(CopyBaseCatalogs $copy): void
    {
        $this->authorizeCatalog();
        abort_if($this->schoolId === null, 404);

        $catalog = array_search($this->catalogModel(), CopyBaseCatalogs::CATALOGS, true);
        $copied = $copy->handle(School::findOrFail($this->schoolId), [$catalog => $this->baseUpdates->modelKeys()]);

        unset($this->baseUpdates);
        $this->dispatch('toast', type: 'success', message: trans_choice('catalogs.updates.incorporated', $copied));
    }

    protected function mountOwnSchool(): bool
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('settings.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return false;
        }

        $this->schoolId = $user->school_id;
        $this->authorizeCatalog();

        return true;
    }
}
