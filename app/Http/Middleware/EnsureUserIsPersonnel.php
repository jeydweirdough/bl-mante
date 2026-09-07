<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the staff area as a whole.
 *
 * Per-action authorisation still goes through policies; this only keeps
 * customers out of the back office entirely, so a policy failure is never the
 * first line of defence for a whole section of the site.
 */
class EnsureUserIsPersonnel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->isPersonnel(), 403, 'This area is for hotel staff.');

        return $next($request);
    }
}
