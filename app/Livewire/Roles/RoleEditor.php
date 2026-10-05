<?php

namespace App\Livewire\Roles;

use App\Actions\Roles\SyncRoleAccess;
use App\Enums\RoleScope;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Alta y edición de un rol con la asignación de sus módulos y submódulos.
 * Lo que se marca aquí es lo que el rol ve en el menú y las rutas que puede abrir.
 */
#[Layout('layouts.app')]
class RoleEditor extends Component
{
    #[Locked]
    public ?int $roleId = null;

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    /** IDs de las opciones de último nivel asignadas al rol. @var list<string> */
    public array $selected = [];

    public function mount(?Role $role = null): void
    {
        if ($role?->exists) {
            $this->authorize('update', $role);

            $this->roleId = $role->id;
            $this->name = $role->name;
            $this->description = (string) $role->description;
            $this->is_active = $role->is_active;
            $this->selected = $role->menuItems()->whereDoesntHave('children')->pluck('menu_items.id')->map(fn ($id) => (string) $id)->all();
        } else {
            $this->authorize('create', Role::class);
            $this->selected = MenuItem::whereIn('slug', SyncRoleAccess::ALWAYS_GRANTED)->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
    }

    /** Marca o desmarca todos los submódulos de un módulo. */
    public function toggleModule(int $moduleId): void
    {
        $children = $this->menu->firstWhere('id', $moduleId)?->children->pluck('id')->map(fn ($id) => (string) $id) ?? collect();

        $this->selected = $children->every(fn (string $id) => in_array($id, $this->selected, true))
            ? array_values(array_diff($this->selected, $children->all()))
            : array_values(array_unique([...$this->selected, ...$children->all()]));
    }

    public function selectAll(): void
    {
        $this->selected = $this->leafIds();
    }

    public function selectNone(): void
    {
        $this->selected = MenuItem::whereIn('slug', SyncRoleAccess::ALWAYS_GRANTED)->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function save(SyncRoleAccess $syncAccess): void
    {
        $role = $this->roleId ? Role::findOrFail($this->roleId) : null;
        $role ? $this->authorize('update', $role) : $this->authorize('create', Role::class);

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($this->roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'selected' => ['array'],
            'selected.*' => ['string', Rule::in($this->leafIds())],
        ], attributes: [
            'name' => Str::lower(__('roles.fields.name')),
            'description' => Str::lower(__('roles.fields.description')),
        ]);

        DB::transaction(function () use (&$role, $data, $syncAccess): void {
            $attributes = [
                'name' => trim($data['name']),
                'description' => trim($data['description']) ?: null,
                'is_active' => $data['is_active'],
            ];

            if ($role === null) {
                $role = Role::create([
                    ...$attributes,
                    'slug' => $this->uniqueSlug($attributes['name']),
                    'scope' => RoleScope::School,
                    'is_system' => false,
                    'sort_order' => (int) Role::max('sort_order') + 10,
                ]);
                AuditLog::record($role, 'created', null, $attributes);
            } else {
                $old = $role->only(array_keys($attributes));
                $role->update($attributes);

                if ($role->wasChanged()) {
                    AuditLog::record($role, 'updated', $old, $role->only(array_keys($attributes)));
                }
            }

            $syncAccess->handle($role, $data['selected']);
        });

        session()->flash('toast', ['type' => 'success', 'message' => __('roles.messages.saved')]);

        $this->redirectRoute('roles.index', navigate: true);
    }

    /** Módulos (nivel 1) con sus submódulos, en el orden del menú. @return Collection<int, MenuItem> */
    #[Computed]
    public function menu(): Collection
    {
        return MenuItem::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();
    }

    /** @return list<string> */
    private function leafIds(): array
    {
        return $this->menu
            ->flatMap(fn (MenuItem $item) => $item->children->isEmpty() ? [$item] : $item->children)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();
    }

    private function uniqueSlug(string $name): string
    {
        $base = 'custom_'.Str::limit(Str::slug($name, '_'), 40, '');
        $slug = $base;

        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}_{$i}";
        }

        return $slug;
    }

    public function render(): View
    {
        return view('livewire.roles.role-editor', [
            'editing' => $this->roleId !== null,
            'alwaysGranted' => MenuItem::whereIn('slug', SyncRoleAccess::ALWAYS_GRANTED)->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'totalLeaves' => count($this->leafIds()),
        ])->title(__($this->roleId ? 'roles.edit_title' : 'roles.create_title'));
    }
}
