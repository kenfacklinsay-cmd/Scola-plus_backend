<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Restreindre une route aux rôles scolaires autorisés. */
class EnsureUserHasRole
{
    /** @param  array<int, string>  $roles */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            $request->user() !== null
                && $request->user()->status === 'ACTIF'
                && in_array($request->user()->role, $roles, true),
            403,
            $request->user()?->status === 'INACTIF'
                ? 'Ce compte est suspendu.'
                : 'Vous n’êtes pas autorisé à accéder à cette ressource.',
        );

        return $next($request);
    }
}
