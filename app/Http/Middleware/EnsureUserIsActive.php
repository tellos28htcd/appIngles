<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado mientras estaba dentro.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('access.inactive')]);
        }

        return $next($request);
    }
}
