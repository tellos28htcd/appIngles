<?php

namespace App\Actions\Roles;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * Guarda qué opciones del menú (y por lo tanto qué rutas) puede usar un rol.
 * Recibe solo opciones de último nivel; los módulos padre se asignan solos.
 * "Inicio" se asigna siempre: es la página a la que llega todo usuario.
 */
final class SyncRoleAccess
{
    public const ALWAYS_GRANTED = ['dashboard'];

    /** @param  list<int|string>  $leafIds */
    public function handle(Role $role, array $leafIds): void
    {
        $leaves = MenuItem::query()
            ->whereDoesntHave('children')
            ->where(fn ($query) => $query
                ->whereIn('id', array_map('intval', $leafIds))
                ->orWhereIn('slug', self::ALWAYS_GRANTED))
            ->get(['id', 'parent_id', 'slug']);

        $granted = $leaves
            ->flatMap(fn (MenuItem $item) => array_filter([$item->id, $item->parent_id]))
            ->unique()
            ->sort()
            ->values()
            ->all();

        DB::transaction(function () use ($role, $granted): void {
            $before = $role->menuItems()->orderBy('menu_items.id')->pluck('slug')->all();

            $role->menuItems()->sync($granted);

            $after = MenuItem::whereIn('id', $granted)->orderBy('id')->pluck('slug')->all();

            if ($before !== $after) {
                AuditLog::record($role, 'access_updated', ['menu' => $before], ['menu' => $after]);
            }
        });
    }
}
