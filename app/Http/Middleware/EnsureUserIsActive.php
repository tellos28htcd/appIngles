<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado, o cuya escuela fue
 * suspendida, mientras estaba dentro.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->loadMissing('school');

        if ($user === null) {
            return $next($request);
        }

        $message = match (true) {
            ! $user->isActive() => __('access.inactive'),
            $user->school !== null && ! $user->school->isActive() => __('access.school_suspended'),
            default => null,
        };

        if ($message !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => $message]);
        }

        return $next($request);
    }
}
