<?php

namespace Tests\Feature\Menu;

use App\Livewire\Menu\MenuManager;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->admin = User::factory()->create(['role_id' => $this->role(Role::PLATFORM_ADMIN)->id]);
    }

    private function role(string $slug): Role
    {
        return Role::where('slug', $slug)->firstOrFail();
    }

    private function item(string $slug): MenuItem
    {
        return MenuItem::where('slug', $slug)->firstOrFail();
    }

    private function schoolUser(string $roleSlug): User
    {
        return User::factory()->create([
            'role_id' => $this->role($roleSlug)->id,
            'school_id' => School::factory()->create()->id,
        ]);
    }

    public function test_menu_option_is_under_platform_and_only_for_super_admin(): void
    {
        $this->actingAs($this->admin)
            ->get(route('menu.index'))
            ->assertOk()
            ->assertSee('Nuevo módulo')
            ->assertSee('Agregar submódulo');

        $this->assertSame('platform', $this->item('platform-menu')->parent->slug);

        $this->actingAs($this->schoolUser(Role::SCHOOL_ADMIN))
            ->get(route('menu.index'))
            ->assertForbidden();
    }

    public function test_super_admin_adds_a_new_module_visible_to_chosen_roles(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(MenuManager::class)
            ->call('openCreate')
            ->set('newLabel', 'Biblioteca')
            ->set('newIcon', 'book')
            ->set('newRoles', [(string) $this->role(Role::TEACHER)->id])
            ->call('create')
            ->assertHasNoErrors()
            ->assertSet('creating', null);

        $module = MenuItem::where('label', 'Biblioteca')->sole();
        $this->assertNull($module->parent_id);
        $this->assertFalse($module->is_system);
        $this->assertTrue($module->isComingSoon());
        $this->assertTrue($this->role(Role::TEACHER)->menuItems()->whereKey($module->id)->exists());

        $this->actingAs($this->schoolUser(Role::TEACHER))->get(route('dashboard'))->assertSee('Biblioteca');
        $this->actingAs($this->schoolUser(Role::RECEPTION))->get(route('dashboard'))->assertDontSee('Biblioteca');
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $module->id, 'event' => 'created']);
    }

    public function test_super_admin_adds_a_submodule_and_its_parent_is_granted_too(): void
    {
        $this->actingAs($this->admin);
        $billing = $this->item('billing');
        $reception = $this->role(Role::RECEPTION);
        $reception->menuItems()->detach($billing->id);

        Livewire::test(MenuManager::class)
            ->call('openCreate', $billing->id)
            ->set('newLabel', 'Convenios de extensión')
            ->set('newRoles', [(string) $reception->id])
            ->call('create')
            ->assertHasNoErrors();

        $child = MenuItem::where('label', 'Convenios de extensión')->sole();
        $this->assertSame($billing->id, $child->parent_id);
        $this->assertNull($child->icon);
        $this->assertEqualsCanonicalizing([$billing->id, $child->id], $reception->menuItems()->whereKey([$billing->id, $child->id])->pluck('menu_items.id')->all());
    }

    public function test_modules_with_their_own_screen_cannot_get_submodules(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(MenuManager::class)
            ->call('openCreate', $this->item('dashboard')->id)
            ->assertSet('newParentId', null)
            ->set('newParentId', $this->item('dashboard')->id)
            ->set('newLabel', 'Indebido')
            ->call('create')
            ->assertHasErrors(['newParentId']);
    }

    public function test_icon_must_come_from_the_design_system(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(MenuManager::class)
            ->call('openCreate')
            ->set('newLabel', 'Raro')
            ->set('newIcon', '<script>')
            ->call('create')
            ->assertHasErrors(['newIcon']);
    }

    public function test_disabling_a_module_hides_it_and_blocks_its_routes_for_everyone(): void
    {
        $this->actingAs($this->admin);
        $users = $this->item('settings-users');

        Livewire::test(MenuManager::class)->call('toggleEnabled', $users->id);
        $this->assertFalse($users->fresh()->is_enabled);

        // Ni el Super Admin ni el admin de escuela lo ven ni entran.
        $this->get(route('users.index'))->assertForbidden();
        $schoolAdmin = $this->schoolUser(Role::SCHOOL_ADMIN);
        $this->actingAs($schoolAdmin)->get(route('users.index'))->assertForbidden();
        $this->actingAs($schoolAdmin)->get(route('dashboard'))->assertDontSee(route('users.index'));

        // Al reactivarlo vuelve todo.
        $this->actingAs($this->admin);
        Livewire::test(MenuManager::class)->call('toggleEnabled', $users->id);
        $this->get(route('users.index'))->assertOk();
        $this->assertSame(2, AuditLog::where('auditable_id', $users->id)->whereIn('event', ['enabled', 'disabled'])->count());
    }

    public function test_disabling_a_parent_module_blocks_its_submodules(): void
    {
        $this->actingAs($this->admin);
        $settings = $this->item('settings');

        Livewire::test(MenuManager::class)->call('toggleEnabled', $settings->id);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('Configuración');
    }

    public function test_home_platform_and_menu_cannot_be_disabled_or_deleted(): void
    {
        $this->actingAs($this->admin);

        foreach (['dashboard', 'platform', 'platform-menu'] as $slug) {
            Livewire::test(MenuManager::class)->call('toggleEnabled', $this->item($slug)->id)->assertForbidden();
            $this->assertTrue($this->item($slug)->is_enabled);
        }
    }

    public function test_only_custom_items_without_submodules_can_be_deleted(): void
    {
        $this->actingAs($this->admin);
        $custom = MenuItem::factory()->comingSoon()->create(['slug' => 'custom-temporal', 'label' => 'Temporal']);

        $this->assertFalse($this->admin->can('delete', $this->item('reports')));
        $this->assertFalse($this->admin->can('delete', $this->item('billing')));
        $this->assertTrue($this->admin->can('delete', $custom));

        Livewire::test(MenuManager::class)->call('confirmDeletion', $custom->id)->call('delete');
        $this->assertModelMissing($custom);

        Livewire::test(MenuManager::class)->call('confirmDeletion', $this->item('reports')->id)->assertForbidden();
    }

    public function test_labels_and_order_are_editable_and_survive_reseeding(): void
    {
        $this->actingAs($this->admin);
        $billingId = (string) $this->item('billing')->id;
        $reportsId = (string) $this->item('reports')->id;
        $custom = MenuItem::factory()->comingSoon()->create(['slug' => 'custom-biblioteca', 'label' => 'Biblioteca', 'is_system' => false]);

        Livewire::test(MenuManager::class)
            ->set("labels.$billingId", 'Pagos y cobranza')
            ->call('move', $reportsId, -1)
            ->call('save')
            ->assertHasNoErrors();

        $this->item('agenda')->update(['is_enabled' => false]);
        $orderBefore = MenuItem::whereNull('parent_id')->orderBy('sort_order')->pluck('slug')->all();

        // Volver a correr los seeders no pisa nada de lo hecho en pantalla.
        $this->seed([RoleSeeder::class, MenuSeeder::class]);

        $this->assertSame('Pagos y cobranza', $this->item('billing')->label);
        $this->assertSame($orderBefore, MenuItem::whereNull('parent_id')->orderBy('sort_order')->pluck('slug')->all());
        $this->assertFalse($this->item('agenda')->is_enabled);
        $this->assertModelExists($custom);
    }
}
