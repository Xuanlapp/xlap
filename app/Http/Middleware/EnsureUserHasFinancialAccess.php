<?php

namespace App\Http\Middleware;

use App\Services\Financial\FinancialAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasFinancialAccess
{
    public function __construct(private readonly FinancialAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $this->access->visibleAccountsQuery($user)->exists(), 403);

        return $next($request);
    }
}
