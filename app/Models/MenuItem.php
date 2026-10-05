<?php

namespace App\Models;

use App\Enums\MenuItemStatus;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['parent_id', 'slug', 'label', 'icon', 'route_name', 'status', 'sort_order'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => MenuItemStatus::class,
        ];
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** @param Builder<MenuItem> $query */
    public function scopeVisibleTo(Builder $query, ?int $roleId): void
    {
        $query->whereHas('roles', fn (Builder $roles) => $roles->whereKey($roleId));
    }

    public function isComingSoon(): bool
    {
        return $this->status === MenuItemStatus::ComingSoon;
    }

    public function isActiveRoute(): bool
    {
        // "users.index" marca como activo todo el módulo "users.*".
        if ($this->route_name !== null && request()->routeIs($this->route_name, Str::beforeLast($this->route_name, '.').'.*')) {
            return true;
        }

        return $this->relationLoaded('children')
            && $this->children->contains(fn (MenuItem $child) => $child->isActiveRoute());
    }
}
