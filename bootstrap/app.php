<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Exclude payment gateway routes from CSRF verification
        $middleware->validateCsrfTokens(except: [
            'tg/paygate/*',
            'tg/payfast/*',
            '*/tg/paygate/*',
            '*/tg/payfast/*',
            'p/paygate/*',
            'p/payfast/*',
            '*/p/paygate/*',
            '*/p/payfast/*',
        ]);
        
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            // other middlewares
            // 'property.access', // Add the PropertyAccessMiddleware to API routes
            // 'identify.property', // Add the IdentifyPropertyMiddleware to API routes
        ]);
        $middleware->web(prepend: [
            // \App\Http\Middleware\ConfigureTenantSession::class, // DISABLED - was causing session recreation
            \App\Http\Middleware\RefreshPermissionCache::class, // Refresh permission cache per request
        ]);
        
        $middleware->priority([
            // \App\Http\Middleware\ConfigureTenantSession::class, // DISABLED
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \App\Http\Middleware\RefreshPermissionCache::class, // After session is started
            // other middlewares
        ]);

        $middleware->alias([
            'guest.portal' => \App\Http\Middleware\GuestPortalMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'auth:sanctum' => \Laravel\Sanctum\Http\Middleware\Authenticate::class,
            'must.change.password' => \App\Http\Middleware\CheckMustChangePassword::class,
            'subscription.check' => \App\Http\Middleware\CheckSubscriptionStatus::class,
            'resource.limit' => \App\Http\Middleware\CheckResourceLimit::class,
            // other middleware aliases
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
