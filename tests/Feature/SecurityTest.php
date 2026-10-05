<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, MenuSeeder::class]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('slug', Role::PLATFORM_ADMIN)->value('id')]);
    }

    public function test_responses_send_security_headers(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);

        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_every_script_tag_carries_the_csp_nonce(): void
    {
        $response = $this->actingAs($this->admin())->get(route('dashboard'))->assertOk();

        preg_match("/'nonce-([A-Za-z0-9]+)'/", $response->headers->get('Content-Security-Policy'), $match);
        preg_match_all('/<script\b[^>]*>/i', $response->getContent(), $scripts);

        $this->assertNotEmpty($scripts[0]);
        foreach ($scripts[0] as $tag) {
            $this->assertStringContainsString('nonce="'.$match[1].'"', $tag, "Script sin nonce: {$tag}");
        }
    }

    public function test_user_supplied_text_is_escaped(): void
    {
        $school = School::factory()->create(['name' => '<script>alert(1)</script>']);
        User::factory()->create([
            'role_id' => Role::where('slug', Role::TEACHER)->value('id'),
            'school_id' => $school->id,
            'first_name' => '<img src=x onerror=alert(1)>',
            'last_name' => 'Prueba',
        ]);

        $this->actingAs($this->admin())
            ->get(route('users.index'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
    }

    public function test_session_cookie_is_http_only(): void
    {
        $cookie = collect($this->get(route('login'))->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_changing_password_ends_sessions_on_other_devices(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        // Otra sesión cambia la contraseña: la sesión actual queda invalidada.
        $user->forceFill(['password' => 'OtraClave2026'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_weak_passwords_are_rejected_by_the_policy(): void
    {
        $validator = validator(['password' => 'abc12345'], ['password' => Password::defaults()]);

        $this->assertTrue($validator->fails());
        $this->assertFalse(validator(['password' => 'Academia2026'], ['password' => Password::defaults()])->fails());
    }

    public function test_web_routes_are_protected_against_csrf(): void
    {
        $webGroup = app('router')->getMiddlewareGroups()['web'];

        $this->assertContains(PreventRequestForgery::class, $webGroup);
        $this->assertContains(SecurityHeaders::class, $webGroup);
    }
}
