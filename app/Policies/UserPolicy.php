<?php

namespace App\Policies;

use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isPlatformAdmin() || $actor->isSchoolAdmin();
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $user): bool
    {
        if ($actor->isPlatformAdmin()) {
            return true;
        }

        return $actor->isSchoolAdmin()
            && $user->school_id !== null
            && $user->school_id === $actor->school_id
            && ! $user->isPlatformAdmin();
    }

    /** Activar o desactivar: nunca a uno mismo ni al último Super Admin activo. */
    public function toggleStatus(User $actor, User $user): bool
    {
        return $this->update($actor, $user)
            && ! $actor->is($user)
            && ! $this->isLastActivePlatformAdmin($user);
    }

    /** Eliminar solo si no tiene registros en ningún módulo (RN-24). */
    public function delete(User $actor, User $user): bool
    {
        return $this->toggleStatus($actor, $user) && ! $user->hasModuleRecords();
    }

    public function resendInvitation(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $user->hasPassword() && $user->isActive();
    }

    /** El administrador de escuela solo asigna roles de escuela. */
    public function assignRole(User $actor, Role $role): bool
    {
        return $actor->isPlatformAdmin()
            || ($this->create($actor) && $role->scope === RoleScope::School);
    }

    private function isLastActivePlatformAdmin(User $user): bool
    {
        if (! $user->isPlatformAdmin() || ! $user->isActive()) {
            return false;
        }

        return User::query()
            ->whereKeyNot($user->id)
            ->where('status', UserStatus::Active)
            ->whereRelation('role', 'slug', Role::PLATFORM_ADMIN)
            ->doesntExist();
    }
}
