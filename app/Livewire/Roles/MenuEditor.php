<?php

namespace App\Livewire\Roles;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Nombre y orden de las opciones del menú. El ícono, la ruta y si el módulo
 * está activo o "Próximamente" los define el código.
 */
#[Layout('layouts.app')]
class MenuEditor extends Component
{
    /** @var array<string, string> id => nombre */
    public array $labels = [];

    /** Orden de los hermanos por padre ('root' = módulos). @var array<string, list<string>> */
    public array $order = [];

    public function mount(): void
    {
        $this->authorize('manageMenu', Role::class);
        $this->loadMenu();
    }

    private function loadMenu(): void
    {
        $items = MenuItem::query()->orderBy('sort_order')->get(['id', 'parent_id', 'label']);

        $this->labels = $items->mapWithKeys(fn (MenuItem $item) => [(string) $item->id => $item->label])->all();
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

    public function save(): void
    {
        $this->authorize('manageMenu', Role::class);

        $validIds = MenuItem::pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->validate([
            'labels' => ['required', 'array'],
            'labels.*' => ['required', 'string', 'max:100'],
        ], attributes: ['labels.*' => mb_strtolower(__('roles.menu.label'))]);

        DB::transaction(function () use ($validIds): void {
            foreach ($this->order as $ids) {
                foreach (array_values($ids) as $position => $id) {
                    if (! in_array($id, $validIds, true)) {
                        continue;
                    }

                    $item = MenuItem::find($id);
                    $old = $item->only(['label', 'sort_order']);
                    $item->fill(['label' => trim($this->labels[$id] ?? $item->label), 'sort_order' => ($position + 1) * 10])->save();

                    if ($item->wasChanged()) {
                        AuditLog::record($item, 'updated', $old, $item->only(['label', 'sort_order']));
                    }
                }
            }
        });

        $this->loadMenu();
        $this->dispatch('toast', type: 'success', message: __('roles.messages.menu_saved'));
    }

    public function render(): View
    {
        /** @var Collection<int, MenuItem> $items */
        $items = MenuItem::query()->get(['id', 'parent_id', 'icon', 'status', 'slug'])->keyBy(fn (MenuItem $item) => (string) $item->id);

        return view('livewire.roles.menu-editor', ['items' => $items])->title(__('roles.menu.title'));
    }
}
