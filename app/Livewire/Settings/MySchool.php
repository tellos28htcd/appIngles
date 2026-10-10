<?php

namespace App\Livewire\Settings;

use App\Livewire\Schools\SchoolEditor;
use App\Models\School;

/**
 * Configuración → Mi escuela: el administrador de la escuela edita los datos
 * que dio de alta el Super Admin, excepto la clave y el estado.
 */
class MySchool extends SchoolEditor
{
    public function mount(?School $school = null): void
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            session()->flash('toast', ['type' => 'warning', 'message' => __('settings.platform_redirect')]);
            $this->redirectRoute('schools.index', navigate: true);

            return;
        }

        $own = School::findOrFail($user->school_id);
        $this->authorize('updateOwn', $own);
        $this->form->setSchool($own);
    }

    public function save(): void
    {
        $school = School::findOrFail(auth()->user()->school_id);
        $this->authorize('updateOwn', $school);

        // La clave no se cambia desde aquí, aunque alguien la altere en el navegador.
        abort_unless($this->form->school?->is($school), 403);
        $this->form->code = $school->code;

        $this->form->save();

        session()->flash('toast', ['type' => 'success', 'message' => __('schools.my_school_saved')]);

        $this->redirectRoute('my-school.edit', navigate: true);
    }

    protected function isOwnSchool(): bool
    {
        return true;
    }
}
