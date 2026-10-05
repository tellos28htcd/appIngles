<?php

namespace App\Actions\Auth;

use App\Enums\LoginEvent;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Inicia sesión con correo y contraseña, limita intentos y deja registro
 * de cada intento en login_logs.
 */
final class AuthenticateUser
{
    public function __construct(private readonly Request $request) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $email, string $password, bool $remember): User
    {
        $email = Str::lower(trim($email));
        $throttleKey = $email.'|'.$this->request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, config('appingles.login.max_attempts'))) {
            LoginLog::record(LoginEvent::Locked, $email, null, $this->request);

            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                    'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        $user = User::query()->with('role')->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, config('appingles.login.decay_seconds'));
            LoginLog::record(LoginEvent::Failed, $email, $user, $this->request);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive() || $user->role === null || ! $user->role->is_active) {
            LoginLog::record(LoginEvent::Inactive, $email, $user, $this->request);

            throw ValidationException::withMessages(['email' => __('access.inactive')]);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('web')->login($user, $remember);
        session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        LoginLog::record(LoginEvent::Succeeded, $email, $user, $this->request);

        return $user;
    }
}
