<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Limiter un compte provisoire à son profil, sa déconnexion et son changement de mot de passe. */
class RequirePasswordUpdate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $allowedRoutes = ['api.user', 'api.logout', 'api.account.password.update'];

        if ($user?->must_change_password && ! in_array($request->route()?->getName(), $allowedRoutes, true)) {
            return response()->json([
                'message' => 'Vous devez choisir un nouveau mot de passe avant d’accéder à l’application.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 423);
        }

        return $next($request);
    }
}
