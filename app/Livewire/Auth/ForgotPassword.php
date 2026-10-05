<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email|max:255')]
    public string $email = '';

    public bool $sent = false;

    public function sendLink(): void
    {
        $this->validate();

        $key = 'forgot-password|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($key),
                'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ]));

            return;
        }

        RateLimiter::hit($key, 300);

        // Mismo mensaje exista o no el correo, para no revelar qué cuentas existen.
        Password::sendResetLink(['email' => Str::lower(trim($this->email))]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password')->title(__('access.forgot.title'));
    }
}
