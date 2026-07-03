<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the current user can access admin pages.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && ((bool) $user->is_admin || $user->role === 'admin'), 403);

        return $next($request);
    }
}
