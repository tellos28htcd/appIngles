<?php

namespace Tests\Feature\Users;

use App\Enums\UserStatus;
use App\Livewire\Auth\AcceptInvitation;
use App\Livewire\Auth\Login;
use App\Livewire\Users\UserEditor;
use App\Livewire\Users\UserIndex;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Notifications\UserInvitation;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;

    private School $schoolB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
        $this->schoolA = School::factory()->create(['name' => 'ESCUELA A']);
        $this->schoolB = School::factory()->create(['name' => 'ESCUELA B']);
    }

    private function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    private function userWithRole(string $slug, ?School $school = null, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => $this->roleId($slug),
            'school_id' => $school?->id,
            ...$attributes,
        ]);
    }

    // ---- Listado y filtros -------------------------------------------------

    public function test_platform_admin_sees_himself_and_all_schools_users(): void
    {
        $admin = $this->userWithRole(Role::PLATFORM_ADMIN, attributes: ['first_name' => 'Administrador', 'last_name' => 'AppIngles']);
        $this->userWithRole(Role::TEACHER, $this->schoolA, ['first_name' => 'Ana', 'last_name' => 'López']);
        $this->userWithRole(Role::RECEPTION, $this->schoolB, ['first_name' => 'Beto', 'last_name' => 'Ruiz']);

        $this->actingAs($admin)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Administrador AppIngles')
            ->assertSee('Ana López')
            ->assertSee('Beto Ruiz');
    }

    public function test_platform_admin_filters_by_school_and_role(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));
        $this->userWithRole(Role::TEACHER, $this->schoolA, ['first_name' => 'Ana', 'last_name' => 'López']);
        $this->userWithRole(Role::RECEPTION, $this->schoolA, ['first_name' => 'Carla', 'last_name' => 'Díaz']);
        $this->userWithRole(Role::TEACHER, $this->schoolB, ['first_name' => 'Beto', 'last_name' => 'Ruiz']);

        Livewire::test(UserIndex::class)
            ->set('school', (string) $this->schoolA->id)
            ->assertSee('Ana López')->assertSee('Carla Díaz')->assertDontSee('Beto Ruiz')
            ->set('role', (string) $this->roleId(Role::TEACHER))
            ->assertSee('Ana López')->assertDontSee('Carla Díaz')
            ->call('clearFilters')
            ->assertSee('Beto Ruiz');
    }

    public function test_school_filter_can_show_platform_users_only(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN, attributes: ['first_name' => 'Super', 'last_name' => 'Admin']));
        $this->userWithRole(Role::TEACHER, $this->schoolA, ['first_name' => 'Ana', 'last_name' => 'López']);

        Livewire::test(UserIndex::class)
            ->set('school', 'plataforma')
            ->assertSee('Super Admin')
            ->assertDontSee('Ana López');
    }

    // ---- Aislamiento entre escuelas (RN-11) -----------------------------------

    public function test_school_admin_only_sees_users_of_his_school(): void
    {
        $this->userWithRole(Role::PLATFORM_ADMIN, attributes: ['first_name' => 'Super', 'last_name' => 'Admin']);
        $this->userWithRole(Role::TEACHER, $this->schoolA, ['first_name' => 'Ana', 'last_name' => 'López']);
        $this->userWithRole(Role::TEACHER, $this->schoolB, ['first_name' => 'Beto', 'last_name' => 'Ruiz']);

        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA))
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Ana López')
            ->assertDontSee('Beto Ruiz')
            ->assertDontSee('Super Admin');
    }

    public function test_school_admin_cannot_open_or_change_users_of_another_school(): void
    {
        $other = $this->userWithRole(Role::TEACHER, $this->schoolB);
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        $this->get(route('users.edit', $other))->assertForbidden();

        Livewire::test(UserIndex::class)->call('confirmDeletion', $other->id)->assertNotFound();
        Livewire::test(UserIndex::class)->call('confirmDeactivation', $other->id)->assertNotFound();
        Livewire::test(UserIndex::class)->call('activate', $other->id)->assertNotFound();

        $this->assertModelExists($other);
        $this->assertTrue($other->fresh()->isActive());
    }

    public function test_school_admin_creates_users_only_in_his_school_and_without_platform_role(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ana@appingles.com')
            ->set('form.role_id', $this->roleId(Role::PLATFORM_ADMIN))
            ->call('save')
            ->assertHasErrors(['form.role_id']);

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ana@appingles.com')
            ->set('form.role_id', $this->roleId(Role::TEACHER))
            ->set('form.school_id', $this->schoolB->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($this->schoolA->id, User::where('email', 'ana@appingles.com')->value('school_id'));
    }

    public function test_teacher_cannot_access_users_module(): void
    {
        $this->actingAs($this->userWithRole(Role::TEACHER, $this->schoolA))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    // ---- Alta, invitación y reglas RN-16 -----------------------------------

    public function test_new_user_belongs_to_one_school_and_receives_invitation(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'María')
            ->set('form.last_name', 'Pérez')
            ->set('form.second_last_name', 'Gómez')
            ->set('form.email', ' Maria@AppIngles.com ')
            ->set('form.role_id', $this->roleId(Role::TEACHER))
            ->set('form.school_id', $this->schoolA->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('users.index'));

        $user = User::where('email', 'maria@appingles.com')->sole();
        $this->assertSame('María Pérez Gómez', $user->name);
        $this->assertSame($this->schoolA->id, $user->school_id);
        $this->assertFalse($user->hasPassword());
        Notification::assertSentTo($user, UserInvitation::class);
    }

    public function test_school_role_requires_a_school(): void
    {
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ana@appingles.com')
            ->set('form.role_id', $this->roleId(Role::RECEPTION))
            ->call('save')
            ->assertHasErrors(['form.school_id' => 'required']);
    }

    public function test_platform_role_cannot_have_a_school(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Otro')
            ->set('form.last_name', 'Admin')
            ->set('form.email', 'otro@appingles.com')
            ->set('form.school_id', $this->schoolA->id)
            ->set('form.role_id', $this->roleId(Role::PLATFORM_ADMIN))
            ->assertSet('form.school_id', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(User::where('email', 'otro@appingles.com')->value('school_id'));
    }

    public function test_email_is_unique_across_the_whole_platform(): void
    {
        $this->userWithRole(Role::TEACHER, $this->schoolB, ['email' => 'ana@appingles.com']);
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ANA@appingles.com')
            ->set('form.role_id', $this->roleId(Role::TEACHER))
            ->set('form.school_id', $this->schoolA->id)
            ->call('save')
            ->assertHasErrors(['form.email' => 'unique']);
    }

    public function test_user_cannot_change_own_role(): void
    {
        $admin = $this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA);
        $this->actingAs($admin);

        Livewire::test(UserEditor::class, ['user' => $admin])
            ->set('form.last_name', 'Nuevo')
            ->set('form.role_id', $this->roleId(Role::TEACHER))
            ->call('save')
            ->assertHasErrors(['form.role_id']);
    }

    public function test_invited_user_creates_password_and_can_log_in(): void
    {
        Notification::fake();
        $this->actingAs($this->userWithRole(Role::PLATFORM_ADMIN));

        Livewire::test(UserEditor::class)
            ->set('form.first_name', 'Ana')
            ->set('form.last_name', 'López')
            ->set('form.email', 'ana@appingles.com')
            ->set('form.role_id', $this->roleId(Role::TEACHER))
            ->set('form.school_id', $this->schoolA->id)
            ->call('save');

        $user = User::where('email', 'ana@appingles.com')->sole();
        $token = null;
        Notification::assertSentTo($user, UserInvitation::class, function (UserInvitation $notification) use ($user, &$token) {
            preg_match('#crear-contrasena/([^?]+)#', $notification->toMail($user)->actionUrl, $match);
            $token = $match[1] ?? null;

            return $token !== null;
        });

        auth()->logout();

        $this->get(route('invitation.accept', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee('Crea tu contraseña');

        Livewire::withQueryParams(['email' => $user->email])
            ->test(AcceptInvitation::class, ['token' => $token])
            ->set('password', 'MiClave2026')
            ->set('password_confirmation', 'MiClave2026')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('MiClave2026', $user->fresh()->password));
    }

    public function test_user_without_password_cannot_log_in(): void
    {
        $user = $this->userWithRole(Role::TEACHER, $this->schoolA, ['password' => null]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    // ---- Activar, desactivar y eliminar (RN-24) ------------------------------

    public function test_unused_user_can_be_deleted(): void
    {
        $teacher = $this->userWithRole(Role::TEACHER, $this->schoolA, ['last_login_at' => now()]);
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserIndex::class)
            ->call('confirmDeletion', $teacher->id)
            ->assertSet('confirmingDeletionId', $teacher->id)
            ->call('delete');

        $this->assertModelMissing($teacher);
    }

    public function test_user_with_module_records_cannot_be_deleted(): void
    {
        $teacher = $this->userWithRole(Role::TEACHER, $this->schoolA);
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        $withRecords = new class extends User
        {
            protected $table = 'users';

            public function hasModuleRecords(): bool
            {
                return true;
            }
        };

        $this->assertFalse(auth()->user()->can('delete', $withRecords->newFromBuilder($teacher->getAttributes())));
        $this->assertTrue(auth()->user()->can('toggleStatus', $teacher));
    }

    public function test_users_can_be_deactivated_and_reactivated(): void
    {
        $teacher = $this->userWithRole(Role::TEACHER, $this->schoolA);
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserIndex::class)
            ->call('confirmDeactivation', $teacher->id)
            ->call('deactivate');
        $this->assertSame(UserStatus::Inactive, $teacher->fresh()->status);

        Livewire::test(UserIndex::class)->call('activate', $teacher->id);
        $this->assertSame(UserStatus::Active, $teacher->fresh()->status);
    }

    public function test_nobody_can_delete_or_deactivate_himself(): void
    {
        $admin = $this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA);
        $this->actingAs($admin);

        Livewire::test(UserIndex::class)->call('confirmDeletion', $admin->id)->assertForbidden();
        Livewire::test(UserIndex::class)->call('confirmDeactivation', $admin->id)->assertForbidden();
    }

    public function test_last_active_platform_admin_is_protected(): void
    {
        $admin = $this->userWithRole(Role::PLATFORM_ADMIN);
        $second = $this->userWithRole(Role::PLATFORM_ADMIN);

        $this->assertTrue($admin->can('delete', $second));

        $second->update(['status' => UserStatus::Inactive]);
        $this->actingAs($second->fresh());
        $this->assertFalse($second->fresh()->can('toggleStatus', $admin));
    }

    public function test_invitation_can_be_resent_while_password_is_pending(): void
    {
        Notification::fake();
        $pending = $this->userWithRole(Role::TEACHER, $this->schoolA, ['password' => null]);
        $this->actingAs($this->userWithRole(Role::SCHOOL_ADMIN, $this->schoolA));

        Livewire::test(UserIndex::class)->call('resendInvitation', $pending->id)->assertDispatched('toast');

        Notification::assertSentTo($pending, UserInvitation::class);
    }
}
