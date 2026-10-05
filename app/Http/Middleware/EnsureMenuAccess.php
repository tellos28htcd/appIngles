<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea las rutas de módulos que el rol del usuario no tiene asignados.
 */
class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName !== null && ! Navigation::allows($request->user(), $routeName)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
