<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasWaliAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && ((bool) $user->can_access_wali || $user->role === 'admin'), 403);

        return $next($request);
    }
}
