<?php

namespace App\Livewire\Roles;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Catálogo de roles (Plataforma → Roles y permisos). */
#[Layout('layouts.app')]
class RoleIndex extends Component
{
    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Role::class);
    }

    public function toggleActive(int $roleId): void
    {
        $role = Role::findOrFail($roleId);
        $this->authorize('update', $role);

        $role->update(['is_active' => ! $role->is_active]);
        AuditLog::record($role, $role->is_active ? 'activated' : 'deactivated');

        $this->dispatch('toast', type: 'success', message: __($role->is_active ? 'roles.messages.activated' : 'roles.messages.deactivated'));
    }

    public function confirmDeletion(int $roleId): void
    {
        $this->authorize('delete', Role::findOrFail($roleId));
        $this->confirmingDeletionId = $roleId;
    }

    public function delete(): void
    {
        $role = Role::findOrFail($this->confirmingDeletionId);
        $this->authorize('delete', $role);

        AuditLog::record($role, 'deleted', $role->only(['slug', 'name', 'description']));
        $role->delete();
        $this->confirmingDeletionId = null;

        $this->dispatch('toast', type: 'success', message: __('roles.messages.deleted'));
    }

    /** @return Collection<int, Role> */
    #[Computed]
    public function roles(): Collection
    {
        return Role::query()
            ->withCount([
                'users',
                'menuItems as modules_count' => fn ($query) => $query->whereDoesntHave('children'),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function roleToDelete(): ?Role
    {
        return $this->confirmingDeletionId ? Role::find($this->confirmingDeletionId) : null;
    }

    public function render(): View
    {
        return view('livewire.roles.role-index', [
            'totalModules' => MenuItem::whereDoesntHave('children')->count(),
        ])->title(__('roles.title'));
    }
}
