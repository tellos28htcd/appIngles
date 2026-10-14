<?php

namespace App\Livewire\Catalogs;

use Illuminate\Support\Facades\Gate;

/**
 * Configuración → Catálogos de alumnos: asesores de ventas (propios de la
 * escuela) y la copia de la escuela de planes, origen de la información,
 * tipos de alumno, puestos y encuesta.
 */
class StudentCatalogs extends CatalogPage
{
    public const STUDENT_TABS = ['asesores', 'planes', 'origenes', 'tipos', 'puestos', 'encuesta'];

    public const STUDENT_CATALOGS = ['student_plans', 'information_sources', 'student_types', 'advisor_positions', 'survey_questions'];

    public function mount(): void
    {
        $user = auth()->user();

        // El Super Admin no pertenece a una escuela: administra la base.
        if ($user->isPlatformAdmin()) {
            $this->redirectRoute('base-catalogs.index', ['pestana' => 'planes'], navigate: true);

            return;
        }

        Gate::authorize('manage-catalog', [$user->school_id]);
        $this->schoolId = $user->school_id;
        $this->updatedTab();
    }

    protected function tabs(): array
    {
        return self::STUDENT_TABS;
    }

    protected function updateCatalogs(): array
    {
        return self::STUDENT_CATALOGS;
    }

    protected function title(): string
    {
        return __('catalogs.student_title');
    }

    protected function subtitle(): string
    {
        return __('catalogs.student_subtitle');
    }
}
