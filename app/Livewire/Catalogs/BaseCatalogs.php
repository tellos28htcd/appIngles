<?php

namespace App\Livewire\Catalogs;

use Illuminate\Support\Facades\Gate;

/** Plataforma → Catálogos base (Super Admin): la base que reciben las escuelas. */
class BaseCatalogs extends CatalogPage
{
    public function mount(): void
    {
        Gate::authorize('manage-catalog', [null]);
        $this->schoolId = null;
    }

    protected function title(): string
    {
        return __('catalogs.base_title');
    }

    protected function subtitle(): string
    {
        return __('catalogs.base_subtitle');
    }
}
