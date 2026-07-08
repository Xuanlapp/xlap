<?php

use App\Http\Middleware\EnsureUserHasProductAccess;
use App\Http\Middleware\EnsureUserHasWaliAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Services\Monitoring\TelegramErrorReporter;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'verified' => EnsureEmailIsVerified::class,
            'admin' => EnsureUserIsAdmin::class,
            'product' => EnsureUserHasProductAccess::class,
            'wali' => EnsureUserHasWaliAccess::class,
        ]);

        $middleware->appendToGroup('web', EnsureUserIsActive::class);

        $csrfExcept = [
            'webhook/telegram',
        ];

        if ((['APP_ENV'] ?? ['APP_ENV'] ?? getenv('APP_ENV')) === 'local') {
            $csrfExcept[] = 'login';
        }

        $middleware->validateCsrfTokens(except: $csrfExcept);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            app(TelegramErrorReporter::class)->report($exception);
        });
    })->create();
