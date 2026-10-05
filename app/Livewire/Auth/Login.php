<?php

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
class Login extends Component
{
    #[Validate('required|string|email|max:255')]
    public string $email = '';

    #[Validate('required|string|max:255')]
    public string $password = '';

    public bool $remember = false;

    public function login(AuthenticateUser $authenticate): void
    {
        // El teclado del celular suele agregar un espacio o mayúscula inicial.
        $this->email = Str::lower(trim($this->email));

        $this->validate();

        $authenticate->handle($this->email, $this->password, $this->remember);

        $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.login')->title(__('access.login.title'));
    }
}
