<?php

namespace Database\Seeders;

use App\Enums\MenuItemStatus;
use App\Models\MenuItem;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Árbol del menú (módulo → submódulos) y qué rol ve cada opción.
 * Al activar un módulo nuevo: poner su route_name y status = active.
 */
class MenuSeeder extends Seeder
{
    /**
     * slug => [label, icon, route_name, status, children]
     *
     * @var array<string, array{0: string, 1: ?string, 2: ?string, 3: MenuItemStatus, 4?: array<string, array{0: string, 1: ?string, 2: ?string, 3: MenuItemStatus}>}>
     */
    private const TREE = [
        'dashboard' => ['Inicio', 'home', 'dashboard', MenuItemStatus::Active],
        'agenda' => ['Agenda', 'calendar', null, MenuItemStatus::ComingSoon],
        'teachers' => ['Teachers', 'users', 'teachers.index', MenuItemStatus::Active],
        'students' => ['Alumnos', 'student', null, MenuItemStatus::Active, [
            'students-records' => ['Expedientes', null, null, MenuItemStatus::ComingSoon],
            'students-guardians' => ['Tutores', null, null, MenuItemStatus::ComingSoon],
        ]],
        'tracking' => ['Seguimiento', 'clipboard', null, MenuItemStatus::Active, [
            'tracking-attendance' => ['Pase de lista', null, null, MenuItemStatus::ComingSoon],
            'tracking-evaluations' => ['Evaluaciones', null, null, MenuItemStatus::ComingSoon],
            'tracking-risk' => ['Alumnos en riesgo', null, null, MenuItemStatus::ComingSoon],
        ]],
        'billing' => ['Cobranza', 'receipt', null, MenuItemStatus::Active, [
            'billing-statements' => ['Estados de cuenta', null, null, MenuItemStatus::ComingSoon],
            'billing-payments' => ['Pagos', null, null, MenuItemStatus::ComingSoon],
            'billing-overdue' => ['Morosos', null, null, MenuItemStatus::ComingSoon],
            'billing-actions' => ['Gestiones de cobranza', null, null, MenuItemStatus::ComingSoon],
        ]],
        'my-students' => ['Mis alumnos', 'heart', null, MenuItemStatus::Active, [
            'my-students-progress' => ['Avance', null, null, MenuItemStatus::ComingSoon],
            'my-students-account' => ['Estado de cuenta', null, null, MenuItemStatus::ComingSoon],
        ]],
        'student-portal' => ['Mi aprendizaje', 'book', null, MenuItemStatus::Active, [
            'student-portal-progress' => ['Mi avance', null, null, MenuItemStatus::ComingSoon],
            'student-portal-book-class' => ['Agendar clase', null, null, MenuItemStatus::ComingSoon],
            'student-portal-book-club' => ['Agendar club', null, null, MenuItemStatus::ComingSoon],
            'student-portal-account' => ['Mi estado de cuenta', null, null, MenuItemStatus::ComingSoon],
        ]],
        'reports' => ['Reportes', 'chart', null, MenuItemStatus::ComingSoon],
        'settings' => ['Configuración', 'settings', null, MenuItemStatus::Active, [
            'settings-school' => ['Mi escuela', null, 'my-school.edit', MenuItemStatus::Active],
            'settings-users' => ['Usuarios', null, 'users.index', MenuItemStatus::Active],
            'settings-catalogs' => ['Catálogos académicos', null, 'catalogs.index', MenuItemStatus::Active],
            'settings-classrooms' => ['Salones', null, 'classrooms.index', MenuItemStatus::Active],
            'settings-payment-methods' => ['Métodos de pago', null, 'payment-methods.index', MenuItemStatus::Active],
            'settings-charge-concepts' => ['Conceptos de cobro', null, 'charge-concepts.index', MenuItemStatus::Active],
            'settings-holidays' => ['Días festivos', null, 'holidays.index', MenuItemStatus::Active],
            'settings-audit' => ['Bitácora', null, 'school-audit-logs.index', MenuItemStatus::Active],
        ]],
        'platform' => ['Plataforma', 'building', null, MenuItemStatus::Active, [
            'platform-schools' => ['Escuelas', null, 'schools.index', MenuItemStatus::Active],
            'platform-catalogs' => ['Catálogos base', null, 'base-catalogs.index', MenuItemStatus::Active],
            'platform-roles' => ['Roles y permisos', null, 'roles.index', MenuItemStatus::Active],
            'platform-menu' => ['Menú', null, 'menu.index', MenuItemStatus::Active],
            'platform-audit' => ['Bitácora', null, 'audit-logs.index', MenuItemStatus::Active],
        ]],
    ];

    /**
     * Opciones de último nivel que ve cada rol; el módulo padre se asigna solo.
     * '*' = todo excepto lo exclusivo de plataforma y del tutor; '**' = todo.
     * El Super Administrador además ve todo por código (Navigation).
     *
     * @var array<string, list<string>>
     */
    private const ACCESS = [
        Role::PLATFORM_ADMIN => ['**'],
        Role::SCHOOL_ADMIN => ['*'],
        Role::STUDENT_SERVICES => [
            'dashboard', 'agenda', 'students-records', 'students-guardians',
            'billing-statements', 'billing-payments', 'billing-actions', 'reports',
        ],
        Role::ACADEMIC_COORDINATOR => [
            'dashboard', 'agenda', 'teachers', 'students-records', 'students-guardians',
            'tracking-attendance', 'tracking-evaluations', 'tracking-risk', 'reports',
        ],
        Role::TEACHER => ['dashboard', 'agenda', 'tracking-attendance', 'tracking-evaluations'],
        Role::MENTOR => [
            'dashboard', 'agenda', 'students-records', 'students-guardians',
            'tracking-attendance', 'tracking-evaluations', 'tracking-risk',
            'billing-statements', 'billing-payments', 'billing-overdue', 'billing-actions', 'reports',
        ],
        Role::RECEPTION => [
            'dashboard', 'agenda', 'students-records', 'students-guardians',
            'billing-statements', 'billing-payments',
        ],
        Role::GUARDIAN => ['dashboard', 'my-students-progress', 'my-students-account'],
        Role::STUDENT => ['dashboard', 'student-portal-progress', 'student-portal-book-class', 'student-portal-book-club', 'student-portal-account'],
    ];

    private const EXCLUSIVE = ['platform', 'my-students', 'student-portal'];

    public function run(): void
    {
        DB::transaction(function (): void {
            $leaves = $this->syncTree();
            $this->removeObsoleteItems();
            $this->syncAccess($leaves);
        });
    }

    /** Quita del menú las opciones que ya no están en el árbol. */
    private function removeObsoleteItems(): void
    {
        $slugs = collect(self::TREE)
            ->flatMap(fn (array $definition, string $slug) => [$slug, ...array_keys($definition[4] ?? [])]);

        // Solo las que vinieron del código: las creadas en pantalla (is_system = false) se respetan.
        MenuItem::where('is_system', true)->whereNotIn('slug', $slugs)->delete();
    }

    /** @return array<string, MenuItem> Opciones de último nivel con su padre (si tiene). */
    private function syncTree(): array
    {
        $leaves = [];
        $order = 0;

        foreach (self::TREE as $slug => $definition) {
            $parent = $this->upsert($slug, $definition, null, $order += 10);
            $children = $definition[4] ?? [];

            if ($children === []) {
                $leaves[$slug] = $parent;

                continue;
            }

            $childOrder = 0;
            foreach ($children as $childSlug => $childDefinition) {
                $leaves[$childSlug] = $this->upsert($childSlug, $childDefinition, $parent->id, $childOrder += 10);
            }
        }

        return $leaves;
    }

    /**
     * Lo que define el código (jerarquía, ícono, ruta, estado) se actualiza
     * siempre; el nombre y el orden solo al crear, porque se editan desde
     * Plataforma → Roles y permisos → Menú.
     *
     * @param  array{0: string, 1: ?string, 2: ?string, 3: MenuItemStatus}  $definition
     */
    private function upsert(string $slug, array $definition, ?int $parentId, int $order): MenuItem
    {
        [$label, $icon, $routeName, $status] = $definition;

        $item = MenuItem::firstOrNew(['slug' => $slug], ['label' => $label, 'sort_order' => $order]);
        $item->fill(['parent_id' => $parentId, 'icon' => $icon, 'route_name' => $routeName, 'status' => $status, 'is_system' => true])->save();

        return $item;
    }

    /**
     * Asigna los permisos iniciales sin quitar nunca los que se dieron desde
     * la pantalla: solo para opciones nuevas o roles que aún no tienen ninguna.
     *
     * @param  array<string, MenuItem>  $leaves
     */
    private function syncAccess(array $leaves): void
    {
        $shared = collect($leaves)
            ->reject(fn (MenuItem $item, string $slug) => $this->isExclusive($slug))
            ->keys();

        foreach (self::ACCESS as $roleSlug => $slugs) {
            $role = Role::where('slug', $roleSlug)->firstOrFail();
            $isNewRole = $role->menuItems()->doesntExist();

            $granted = collect($slugs)
                ->flatMap(fn (string $slug) => match ($slug) {
                    '**' => array_keys($leaves),
                    '*' => $shared,
                    default => [$slug],
                })
                ->map(fn (string $slug) => $leaves[$slug])
                ->filter(fn (MenuItem $item) => $isNewRole || $item->wasRecentlyCreated)
                ->flatMap(fn (MenuItem $item) => array_filter([$item->id, $item->parent_id]))
                ->unique()
                ->values();

            $role->menuItems()->syncWithoutDetaching($granted);
        }
    }

    private function isExclusive(string $slug): bool
    {
        foreach (self::EXCLUSIVE as $prefix) {
            if ($slug === $prefix || str_starts_with($slug, $prefix.'-')) {
                return true;
            }
        }

        return false;
    }
}
