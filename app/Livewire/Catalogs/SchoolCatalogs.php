<?php

namespace App\Livewire\Catalogs;

use Illuminate\Support\Facades\Gate;

/** Configuración → Catálogos académicos: la copia de la escuela del usuario. */
class SchoolCatalogs extends CatalogPage
{
    public function mount(): void
    {
        $user = auth()->user();

        // El Super Admin no pertenece a una escuela: administra la base.
        if ($user->isPlatformAdmin()) {
            $this->redirectRoute('base-catalogs.index', navigate: true);

            return;
        }

        Gate::authorize('manage-catalog', [$user->school_id]);
        $this->schoolId = $user->school_id;
    }

    protected function title(): string
    {
        return __('catalogs.school_title');
    }

    protected function subtitle(): string
    {
        return __('catalogs.school_subtitle');
    }
}
