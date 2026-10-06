<?php

namespace App\Livewire\Auth\Concerns;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Enlaces de correo (invitación, recuperar contraseña) que se pueden abrir
 * con otra sesión iniciada en el mismo navegador: en vez de mandar al
 * Inicio de esa sesión, se avisa y se ofrece cerrarla para continuar.
 */
trait RequiresSignedOut
{
    #[Locked]
    public string $returnUrl = '';

    protected function rememberReturnUrl(): void
    {
        $this->returnUrl = request()->fullUrl();
    }

    public function signOutAndContinue(): void
    {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect($this->returnUrl ?: route('login'));
    }

    protected function ensureSignedOut(): void
    {
        abort_if(Auth::check(), 403);
    }
}
