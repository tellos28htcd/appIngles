<?php

namespace App\Support;

use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Menú dinámico: módulos (nivel 1) y submódulos (nivel 2) que el rol del
 * usuario tiene asignados en menu_item_role. El Super Administrador ve y
 * entra a todo, incluidos los módulos que se agreguen después.
 */
final class Navigation
{
    /** @return Collection<int, MenuItem> */
    public static function for(?User $user): Collection
    {
        if ($user?->role_id === null) {
            return new Collection;
        }

        $seesEverything = $user->isPlatformAdmin();
        $restrict = fn (Builder|HasMany $query) => $seesEverything ? $query : $query->visibleTo($user->role_id);

        return $restrict(MenuItem::query()->whereNull('parent_id'))
            ->with(['children' => fn (HasMany $children) => $restrict($children)])
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (MenuItem $item) => $item->route_name === null && $item->children->isEmpty() && ! $item->isComingSoon())
            ->values();
    }

    /**
     * ¿El usuario puede entrar a esta ruta? Una opción del menú con ruta
     * "users.index" cubre todo el módulo "users.*" (alta, edición…). Las rutas
     * que no pertenecen a ningún módulo del menú (cerrar sesión…) no se
     * restringen aquí; las acciones siempre se autorizan además con Policies.
     */
    public static function allows(User $user, string $routeName): bool
    {
        $module = Str::contains($routeName, '.') ? Str::beforeLast($routeName, '.') : $routeName;

        $registered = MenuItem::query()->where(fn (Builder $query) => $query
            ->where('route_name', $routeName)
            ->orWhere('route_name', 'like', $module.'.%'));

        if ($user->isPlatformAdmin() || ! $registered->exists()) {
            return true;
        }

        return $registered
            ->where('status', 'active')
            ->whereHas('roles', fn (Builder $roles) => $roles->whereKey($user->role_id))
            ->exists();
    }
}
