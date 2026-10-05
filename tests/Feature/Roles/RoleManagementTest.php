<?php

namespace Tests\Feature\Roles;

use App\Livewire\Auth\Login;
use App\Livewire\Roles\RoleEditor;
use App\Livewire\Roles\RoleIndex;
use App\Models\MenuItem;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoleManagementTest extends TestCase
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

    private function itemId(string $slug): string
    {
        return (string) MenuItem::where('slug', $slug)->value('id');
    }

    public function test_platform_admin_sees_the_roles_catalog(): void
    {
        $this->actingAs($this->admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Roles y permisos')
            ->assertSee('Administrador escuela')
            ->assertSee('Mentor / Administrador')
            ->assertSee('Acceso total, no editable');
    }

    public function test_school_admin_cannot_manage_roles(): void
    {
        $schoolAdmin = User::factory()->create([
            'role_id' => $this->role(Role::SCHOOL_ADMIN)->id,
            'school_id' => School::factory()->create()->id,
        ]);

        $this->actingAs($schoolAdmin);
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('menu.index'))->assertForbidden();
        $this->get(route('roles.edit', $this->role(Role::TEACHER)))->assertForbidden();
    }

    public function test_admin_assigns_modules_to_a_role_and_menu_reflects_it(): void
    {
        $teacher = $this->role(Role::TEACHER);
        $this->actingAs($this->admin);

        Livewire::test(RoleEditor::class, ['role' => $teacher])
            ->call('selectNone')
            ->set('selected', [$this->itemId('dashboard'), $this->itemId('billing-payments'), $this->itemId('reports')])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('roles.index'));

        $granted = $teacher->menuItems()->pluck('slug');
        $this->assertEqualsCanonicalizing(['dashboard', 'billing', 'billing-payments', 'reports'], $granted->all());

        $teacherUser = User::factory()->for($teacher)->create(['school_id' => School::factory()->create()->id]);
        $this->actingAs($teacherUser)
            ->get(route('dashboard'))
            ->assertSee('Cobranza')
            ->assertSee('Pagos')
            ->assertDontSee('Pase de lista');

        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $teacher->id, 'event' => 'access_updated', 'user_id' => $this->admin->id]);
    }

    public function test_toggling_a_module_selects_or_clears_all_its_submodules(): void
    {
        $this->actingAs($this->admin);
        $billing = MenuItem::where('slug', 'billing')->first();
        $children = $billing->children()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $component = Livewire::test(RoleEditor::class, ['role' => $this->role(Role::TEACHER)])
            ->call('toggleModule', $billing->id);
        $this->assertEmpty(array_diff($children, $component->get('selected')));

        $component->call('toggleModule', $billing->id);
        $this->assertEmpty(array_intersect($children, $component->get('selected')));
    }

    public function test_home_is_always_granted(): void
    {
        $this->actingAs($this->admin);
        $reception = $this->role(Role::RECEPTION);

        Livewire::test(RoleEditor::class, ['role' => $reception])
            ->set('selected', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['dashboard'], $reception->menuItems()->pluck('slug')->all());
    }

    public function test_cannot_assign_unknown_or_parent_items(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(RoleEditor::class, ['role' => $this->role(Role::TEACHER)])
            ->set('selected', ['999999'])
            ->call('save')
            ->assertHasErrors(['selected.0']);
    }

    public function test_admin_creates_a_custom_school_role_that_can_be_assigned(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(RoleEditor::class)
            ->set('name', 'Coordinador de clubes')
            ->set('description', 'Organiza los clubes de conversación.')
            ->set('selected', [$this->itemId('dashboard'), $this->itemId('agenda')])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::where('name', 'Coordinador de clubes')->sole();
        $this->assertSame('custom_coordinador_de_clubes', $role->slug);
        $this->assertFalse($role->is_system);
        $this->assertSame('school', $role->scope->value);
        $this->assertEqualsCanonicalizing(['dashboard', 'agenda'], $role->menuItems()->pluck('slug')->all());
    }

    public function test_only_custom_roles_without_users_can_be_deleted(): void
    {
        $this->actingAs($this->admin);
        $custom = Role::factory()->create(['name' => 'Temporal']);
        $withUsers = Role::factory()->create(['name' => 'Con usuarios']);
        User::factory()->for($withUsers)->create(['school_id' => School::factory()->create()->id]);

        $this->assertTrue($this->admin->can('delete', $custom));
        $this->assertFalse($this->admin->can('delete', $withUsers));
        $this->assertFalse($this->admin->can('delete', $this->role(Role::TEACHER)));
        $this->assertFalse($this->admin->can('update', $this->role(Role::PLATFORM_ADMIN)));

        Livewire::test(RoleIndex::class)->call('confirmDeletion', $custom->id)->call('delete');
        $this->assertModelMissing($custom);

        Livewire::test(RoleIndex::class)->call('confirmDeletion', $withUsers->id)->assertForbidden();
    }

    public function test_deactivated_role_cannot_log_in(): void
    {
        $this->actingAs($this->admin);
        $teacherRole = $this->role(Role::TEACHER);
        $teacher = User::factory()->for($teacherRole)->create(['school_id' => School::factory()->create()->id]);

        Livewire::test(RoleIndex::class)->call('toggleActive', $teacherRole->id);
        $this->assertFalse($teacherRole->fresh()->is_active);

        auth()->logout();
        Livewire::test(Login::class)
            ->set('email', $teacher->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');
    }

    public function test_platform_admin_role_cannot_be_toggled(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(RoleIndex::class)->call('toggleActive', $this->role(Role::PLATFORM_ADMIN)->id)->assertForbidden();
    }
}
