<?php

namespace App\Http\Controllers\Auth;

use App\Enums\LoginEvent;
use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            LoginLog::record(LoginEvent::Logout, $user->email, $user, $request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
