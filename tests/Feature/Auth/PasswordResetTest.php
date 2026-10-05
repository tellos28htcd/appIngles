<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_to_registered_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendLink')
            ->assertSet('sent', true);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_unknown_email_shows_the_same_confirmation(): void
    {
        Notification::fake();

        Livewire::test(ForgotPassword::class)
            ->set('email', 'nadie@appingles.com')
            ->call('sendLink')
            ->assertSet('sent', true);

        Notification::assertNothingSent();
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk();

        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'NuevaClave2026')
            ->set('password_confirmation', 'NuevaClave2026')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('NuevaClave2026', $user->fresh()->password));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::test(ResetPassword::class, ['token' => 'token-falso'])
            ->set('email', $user->email)
            ->set('password', 'NuevaClave2026')
            ->set('password_confirmation', 'NuevaClave2026')
            ->call('resetPassword')
            ->assertHasErrors('email');
    }

    public function test_weak_password_is_rejected(): void
    {
        Livewire::test(ResetPassword::class, ['token' => 'cualquiera'])
            ->set('email', 'alguien@appingles.com')
            ->set('password', 'corta')
            ->set('password_confirmation', 'corta')
            ->call('resetPassword')
            ->assertHasErrors('password');
    }
}
