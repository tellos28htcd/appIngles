<?php

namespace App\Livewire\Catalogs\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Locked;

/**
 * Comportamiento común de las pestañas de catálogos: el mismo componente
 * administra el catálogo base (schoolId nulo) o la copia de una escuela.
 * Toda consulta y acción se limita a ese catálogo y se autoriza de nuevo.
 */
trait ManagesCatalog
{
    #[Locked]
    public ?int $schoolId = null;

    /** "modelo:id" en espera de confirmación para eliminar. */
    public ?string $confirmingDeletion = null;

    protected function authorizeCatalog(): void
    {
        Gate::authorize('manage-catalog', [$this->schoolId]);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    protected function catalog(string $model): Builder
    {
        return $model::query()->ofCatalog($this->schoolId);
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    protected function findInCatalog(string $model, int|string|null $id): Model
    {
        return $this->catalog($model)->findOrFail($id);
    }

    /** Regla "único dentro de este catálogo" (base o escuela). */
    protected function uniqueInCatalog(string $table, string $column, ?int $ignoreId = null): Unique
    {
        return (new Unique($table, $column))
            ->where(fn ($query) => $this->schoolId === null
                ? $query->whereNull('school_id')
                : $query->where('school_id', $this->schoolId))
            ->ignore($ignoreId);
    }

    /** @param class-string<Model> $model */
    protected function toggleActive(string $model, int $id): void
    {
        $this->authorizeCatalog();

        $record = $this->findInCatalog($model, $id);
        $record->update(['is_active' => ! $record->is_active]);

        $this->dispatch('toast', type: 'success', message: __($record->is_active ? 'catalogs.messages.activated' : 'catalogs.messages.deactivated'));
        $this->dispatch('catalog-changed');
    }

    /** @param class-string<Model> $model */
    protected function askDeletion(string $model, int $id): void
    {
        $this->authorizeCatalog();

        $record = $this->findInCatalog($model, $id);

        if (! $record->canBeDeleted()) {
            $this->dispatch('toast', type: 'error', message: __('catalogs.messages.in_use'));

            return;
        }

        $this->confirmingDeletion = class_basename($model).':'.$id;
    }

    /** @param array<string, class-string<Model>> $models nombre corto => clase */
    protected function deleteConfirmed(array $models): void
    {
        $this->authorizeCatalog();

        [$type, $id] = explode(':', (string) $this->confirmingDeletion) + [null, null];
        $model = $models[$type] ?? abort(404);
        $record = $this->findInCatalog($model, (int) $id);

        if ($record->canBeDeleted()) {
            $record->delete();
            $this->dispatch('toast', type: 'success', message: __('catalogs.messages.deleted'));
            $this->dispatch('catalog-changed');
        }

        $this->confirmingDeletion = null;
    }
}
