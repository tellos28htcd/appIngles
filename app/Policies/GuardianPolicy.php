<?php

namespace App\Policies;

use App\Models\Guardian;
use App\Models\User;
use App\Support\Navigation;

/**
 * Tutores: consultar requiere la opción "Tutores" del menú; registrar, editar
 * y dar acceso, ser administrador de la escuela o Servicios escolares.
 */
class GuardianPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->school_id !== null && Navigation::allows($actor, 'guardians.index');
    }

    public function update(User $actor, Guardian $guardian): bool
    {
        return $this->viewAny($actor)
            && $actor->school_id === $guardian->school_id
            && in_array($actor->loadMissing('role')->role?->slug, StudentPolicy::MANAGER_ROLES, true);
    }

    /** El acceso se da solo cuando el tutor lo solicita y tiene correo. */
    public function grantAccess(User $actor, Guardian $guardian): bool
    {
        return $this->update($actor, $guardian) && $guardian->email !== null;
    }

    public function delete(User $actor, Guardian $guardian): bool
    {
        return $this->update($actor, $guardian) && $guardian->user_id === null;
    }
}
