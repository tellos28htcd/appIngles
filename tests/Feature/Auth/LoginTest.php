<?php

namespace Tests\Feature\Auth;

use App\Enums\LoginEvent;
use App\Livewire\Auth\Login;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
    }

    private function admin(array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', Role::PLATFORM_ADMIN)->value('id'),
            'email' => 'admin@appingles.com',
            ...$attributes,
        ]);
    }

    public function test_login_page_renders_in_spanish(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeLivewire(Login::class)
            ->assertSee('Inicia sesión')
            ->assertSee('¿Olvidaste tu contraseña?');
    }

    public function test_active_user_can_log_in_and_is_logged(): void
    {
        $user = $this->admin();

        Livewire::test(Login::class)
            ->set('email', 'ADMIN@appingles.com ')
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id, 'event' => LoginEvent::Succeeded->value]);
    }

    public function test_wrong_password_is_rejected_and_logged(): void
    {
        $user = $this->admin();

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'incorrecta')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
        $this->assertSame(LoginEvent::Failed, LoginLog::sole()->event);
    }

    public function test_unknown_email_gets_the_same_generic_error(): void
    {
        Livewire::test(Login::class)
            ->set('email', 'nadie@appingles.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $this->admin(['status' => 'inactive']);

        Livewire::test(Login::class)
            ->set('email', 'admin@appingles.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email' => __('access.inactive')]);

        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', ['event' => LoginEvent::Inactive->value]);
    }

    public function test_user_with_inactive_role_cannot_log_in(): void
    {
        $role = Role::factory()->inactive()->create();
        User::factory()->for($role)->create(['email' => 'teacher@appingles.com']);

        Livewire::test(Login::class)
            ->set('email', 'teacher@appingles.com')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_locked_after_too_many_attempts(): void
    {
        $this->admin();
        $component = Livewire::test(Login::class)->set('email', 'admin@appingles.com');

        foreach (range(1, config('appingles.login.max_attempts')) as $attempt) {
            $component->set('password', 'incorrecta')->call('login');
        }

        $component->set('password', 'password')->call('login')->assertHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', ['event' => LoginEvent::Locked->value]);
    }

    public function test_email_and_password_are_required(): void
    {
        Livewire::test(Login::class)
            ->call('login')
            ->assertHasErrors(['email' => 'required', 'password' => 'required']);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect('/inicio');
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $this->actingAs($this->admin())->get(route('login'))->assertRedirect(route('dashboard'));
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = $this->admin();
        $this->actingAs($user);
        $user->update(['status' => 'inactive']);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_user_can_log_out(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id, 'event' => LoginEvent::Logout->value]);
    }
}
