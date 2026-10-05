<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

/** El menú lo administra solo el Super Administrador. */
class MenuItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isPlatformAdmin();
    }

    public function update(User $actor, MenuItem $item): bool
    {
        return $actor->isPlatformAdmin();
    }

    /** Desactivar por completo: nunca Inicio, Plataforma ni Menú. */
    public function toggle(User $actor, MenuItem $item): bool
    {
        return $actor->isPlatformAdmin() && ! $item->isProtected();
    }

    /** Eliminar: solo opciones creadas en pantalla y sin submódulos. */
    public function delete(User $actor, MenuItem $item): bool
    {
        return $this->toggle($actor, $item)
            && ! $item->is_system
            && $item->children()->doesntExist();
    }
}
