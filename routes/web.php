<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inicio');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
    Route::livewire('/recuperar-contrasena', ForgotPassword::class)->name('password.request');
    Route::livewire('/restablecer-contrasena/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware(['auth', 'active', 'menu.access'])->group(function () {
    Route::livewire('/inicio', Dashboard::class)->name('dashboard');
});

Route::post('/logout', LogoutController::class)->middleware('auth')->name('logout');
