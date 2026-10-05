<?php

namespace App\Livewire\Menu;

use App\Enums\MenuItemStatus;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use App\Support\MenuIcons;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Plataforma → Menú (solo Super Admin): agregar módulos y submódulos,
 * renombrar, reordenar, desactivar por completo y eliminar los creados aquí.
 */
#[Layout('layouts.app')]
class MenuManager extends Component
{
    /** @var array<string, string> id => nombre */
    public array $labels = [];

    /** Orden de los hermanos por padre ('root' = módulos). @var array<string, list<string>> */
    public array $order = [];

    // Alta de una opción nueva
    public ?bool $creating = null;

    public ?int $newParentId = null;

    public string $newLabel = '';

    public string $newIcon = 'sparkles';

    public string $newRoute = '';

    /** @var list<string> */
    public array $newRoles = [];

    public ?int $confirmingDeletionId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', MenuItem::class);
        $this->loadMenu();
    }

    /** Recarga nombres y orden sin perder los nombres que se estaban editando. */
    private function loadMenu(): void
    {
        $items = MenuItem::query()->orderBy('sort_order')->get(['id', 'parent_id', 'label']);
        $pending = $this->labels;

        $this->labels = $items->mapWithKeys(fn (MenuItem $item) => [
            (string) $item->id => $pending[(string) $item->id] ?? $item->label,
        ])->all();

        $this->order = $items
            ->groupBy(fn (MenuItem $item) => $item->parent_id ? (string) $item->parent_id : 'root')
            ->map(fn ($group) => $group->pluck('id')->map(fn ($id) => (string) $id)->values()->all())
            ->all();
    }

    public function move(string $itemId, int $direction): void
    {
        foreach ($this->order as $parent => $ids) {
            $index = array_search($itemId, $ids, true);
            $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

            if ($target !== null && isset($ids[$target])) {
                [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
                $this->order[$parent] = $ids;
            }
        }
    }

    /** Guarda nombres y orden. */
    public function save(): void
    {
        $this->authorize('viewAny', MenuItem::class);

        $this->validate([
            'labels' => ['required', 'array'],
            'labels.*' => ['required', 'string', 'max:100'],
        ], attributes: ['labels.*' => Str::lower(__('menu.fields.label'))]);

        $items = MenuItem::all()->keyBy(fn (MenuItem $item) => (string) $item->id);

        DB::transaction(function () use ($items): void {
            foreach ($this->order as $ids) {
                foreach (array_values($ids) as $position => $id) {
                    $item = $items[$id] ?? null;

                    if ($item === null) {
                        continue;
                    }

                    $old = $item->only(['label', 'sort_order']);
                    $item->fill(['label' => trim($this->labels[$id] ?? $item->label), 'sort_order' => ($position + 1) * 10])->save();

                    if ($item->wasChanged()) {
                        AuditLog::record($item, 'updated', $old, $item->only(['label', 'sort_order']));
                    }
                }
            }
        });

        $this->dispatch('toast', type: 'success', message: __('menu.messages.saved'));
    }

    /** Desactivar por completo (oculta para todos, rutas bloqueadas) o reactivar. */
    public function toggleEnabled(int $itemId): void
    {
        $item = MenuItem::findOrFail($itemId);
        $this->authorize('toggle', $item);

        $item->update(['is_enabled' => ! $item->is_enabled]);
        AuditLog::record($item, $item->is_enabled ? 'enabled' : 'disabled');
        unset($this->items);

        $this->dispatch('toast', type: 'success', message: __($item->is_enabled ? 'menu.messages.enabled' : 'menu.messages.disabled', ['name' => $item->label]));
    }

    public function openCreate(?int $parentId = null): void
    {
        $this->authorize('create', MenuItem::class);

        $this->resetValidation();
        $this->reset('newLabel', 'newRoute', 'newRoles');
        $this->newIcon = 'sparkles';
        $this->newParentId = $parentId && MenuItem::whereKey($parentId)->whereNull('parent_id')->whereNull('route_name')->exists() ? $parentId : null;
        $this->creating = true;
    }

    public function create(): void
    {
        $this->authorize('create', MenuItem::class);

        $data = $this->validate([
            // Solo módulos sin pantalla propia pueden agrupar submódulos.
            'newParentId' => ['nullable', 'integer', Rule::exists('menu_items', 'id')->whereNull('parent_id')->whereNull('route_name')],
            'newLabel' => ['required', 'string', 'max:100'],
            'newIcon' => [$this->newParentId ? 'nullable' : 'required', Rule::in(MenuIcons::KEYS)],
            'newRoute' => ['nullable', Rule::in(array_keys($this->availableRoutes))],
            'newRoles' => ['array'],
            'newRoles.*' => [Rule::exists('roles', 'id')],
        ], attributes: [
            'newLabel' => Str::lower(__('menu.fields.label')),
            'newIcon' => Str::lower(__('menu.fields.icon')),
            'newRoute' => Str::lower(__('menu.fields.route')),
        ]);

        DB::transaction(function () use ($data): void {
            $parentId = $data['newParentId'] ?? null;

            $item = MenuItem::create([
                'parent_id' => $parentId,
                'slug' => $this->uniqueSlug($data['newLabel']),
                'label' => trim($data['newLabel']),
                'icon' => $parentId ? null : $data['newIcon'],
                'route_name' => $data['newRoute'] ?: null,
                'status' => $data['newRoute'] ? MenuItemStatus::Active : MenuItemStatus::ComingSoon,
                'is_system' => false,
                'is_enabled' => true,
                'sort_order' => (int) MenuItem::where('parent_id', $parentId)->max('sort_order') + 10,
            ]);

            // Los roles elegidos ven la opción (y su módulo, si es submódulo).
            foreach (Role::whereKey($data['newRoles'])->get() as $role) {
                $role->menuItems()->syncWithoutDetaching(array_filter([$item->id, $parentId]));
            }

            AuditLog::record($item, 'created', null, [
                ...$item->only(['label', 'parent_id', 'icon', 'route_name']),
                'roles' => Role::whereKey($data['newRoles'])->pluck('slug')->all(),
            ]);
        });

        $this->creating = null;
        $this->loadMenu();
        unset($this->items, $this->availableRoutes);

        $this->dispatch('toast', type: 'success', message: __('menu.messages.created'));
    }

    public function confirmDeletion(int $itemId): void
    {
        $this->authorize('delete', MenuItem::findOrFail($itemId));
        $this->confirmingDeletionId = $itemId;
    }

    public function delete(): void
    {
        $item = MenuItem::findOrFail($this->confirmingDeletionId);
        $this->authorize('delete', $item);

        AuditLog::record($item, 'deleted', $item->only(['slug', 'label', 'parent_id', 'route_name']));
        $item->delete();

        $this->confirmingDeletionId = null;
        unset($this->labels[(string) $item->id]);
        $this->loadMenu();
        unset($this->items, $this->availableRoutes);

        $this->dispatch('toast', type: 'success', message: __('menu.messages.deleted'));
    }

    /** @return Collection<string, MenuItem> */
    #[Computed]
    public function items(): Collection
    {
        return MenuItem::query()->withCount('children')->get()->keyBy(fn (MenuItem $item) => (string) $item->id);
    }

    /**
     * Pantallas ya programadas que aún no tienen opción en el menú.
     *
     * @return array<string, string> nombre de ruta => URL
     */
    #[Computed]
    public function availableRoutes(): array
    {
        $used = MenuItem::whereNotNull('route_name')->pluck('route_name')->all();

        return collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn (RouteDefinition $route, string $name) => in_array('GET', $route->methods(), true)
                && in_array('menu.access', $route->gatherMiddleware(), true)
                && $route->parameterNames() === []
                && (Str::endsWith($name, '.index') || ! Str::contains($name, '.'))
                && ! in_array($name, $used, true))
            ->map(fn (RouteDefinition $route) => '/'.ltrim($route->uri(), '/'))
            ->sort()
            ->all();
    }

    #[Computed]
    public function pendingDeletion(): ?MenuItem
    {
        return $this->confirmingDeletionId ? MenuItem::find($this->confirmingDeletionId) : null;
    }

    private function uniqueSlug(string $label): string
    {
        $base = 'custom-'.Str::limit(Str::slug($label), 60, '');
        $slug = $base;

        for ($i = 2; MenuItem::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    public function render(): View
    {
        return view('livewire.menu.menu-manager', [
            'icons' => MenuIcons::options(),
            'roleOptions' => Role::query()->where('slug', '!=', Role::PLATFORM_ADMIN)->orderBy('sort_order')->pluck('name', 'id')->all(),
            'parentLabel' => $this->newParentId ? ($this->labels[(string) $this->newParentId] ?? null) : null,
        ])->title(__('menu.title'));
    }
}
