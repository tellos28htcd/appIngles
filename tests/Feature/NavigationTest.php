<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
    }

    private function userWithRole(string $slug): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
    }

    public function test_platform_admin_sees_platform_module_and_coming_soon_badges(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Plataforma')
            ->assertSee('Escuelas')
            ->assertSee('Configuración')
            ->assertSee('Próximamente')
            ->assertSee('Con tecnología de');
    }

    public function test_platform_admin_has_access_to_everything_even_without_assignments(): void
    {
        $admin = $this->userWithRole(Role::PLATFORM_ADMIN);
        $admin->role->menuItems()->detach();
        MenuItem::factory()->comingSoon()->create(['slug' => 'modulo-nuevo', 'label' => 'Módulo nuevo']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis alumnos')
            ->assertSee('Plataforma')
            ->assertSee('Módulo nuevo');
    }

    public function test_teacher_only_sees_assigned_modules(): void
    {
        $this->actingAs($this->userWithRole(Role::TEACHER))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pase de lista')
            ->assertDontSee('Cobranza')
            ->assertDontSee('Plataforma')
            ->assertDontSee('Configuración');
    }

    public function test_guardian_sees_their_own_portal_only(): void
    {
        $this->actingAs($this->userWithRole(Role::GUARDIAN))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mis alumnos')
            ->assertDontSee('Agenda');
    }

    public function test_student_sees_his_learning_portal_only(): void
    {
        $this->actingAs($this->userWithRole(Role::STUDENT))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mi aprendizaje')
            ->assertSee('Mi avance')
            ->assertSee('Agendar clase')
            ->assertSee('Agendar club')
            ->assertSee('Mi estado de cuenta')
            ->assertDontSee('Configuración')
            ->assertDontSee('Cobranza')
            ->assertDontSee('Mis alumnos');
    }

    public function test_school_admin_does_not_get_the_student_portal(): void
    {
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Mi aprendizaje');
    }

    public function test_role_without_the_menu_item_cannot_open_its_route(): void
    {
        $role = Role::factory()->create();

        $this->actingAs(User::factory()->for($role)->create())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_access_is_granted_once_the_item_is_assigned_to_the_role(): void
    {
        $role = Role::factory()->create();
        $role->menuItems()->attach(MenuItem::where('slug', 'dashboard')->value('id'));

        $this->actingAs(User::factory()->for($role)->create())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_seeders_are_idempotent_and_create_a_single_super_admin(): void
    {
        config(['appingles.super_admin.password' => 'ClaveDePrueba2026']);

        $this->seed([RoleSeeder::class, MenuSeeder::class, SuperAdminSeeder::class, SuperAdminSeeder::class]);

        $this->assertSame(9, Role::count());
        $this->assertSame(1, User::count());
        $this->assertTrue(User::sole()->isPlatformAdmin());
        $this->assertSame(1, MenuItem::where('slug', 'dashboard')->count());
    }
}
