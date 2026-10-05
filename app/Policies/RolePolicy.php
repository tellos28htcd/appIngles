<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/** Catálogo de roles y permisos del menú: solo el Super Administrador. */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    /** El rol de plataforma tiene acceso total por código: no se edita. */
    public function update(User $actor, Role $role): bool
    {
        return $actor->isPlatformAdmin() && ! $role->isPlatformAdmin();
    }

    /** Solo roles creados desde la pantalla y sin usuarios. */
    public function delete(User $actor, Role $role): bool
    {
        return $this->update($actor, $role)
            && ! $role->is_system
            && ! $role->users()->exists();
    }

    public function manageMenu(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }
}
