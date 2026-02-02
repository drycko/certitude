<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureTenantSession
{
    /**
     * Handle an incoming request.
     *
     * This middleware is actually not needed because:
     * 1. Session configuration should be set before middleware runs
     * 2. Changing session config during request causes session recreation
     * 3. CacheTenancyBootstrapper already isolates cache by tenant
     * 
     * Keeping this as a pass-through for now. Consider removing from bootstrap/app.php
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Simply pass through - session config should be set in config/session.php
        // or via environment variables, not dynamically during request
        return $next($request);
    }
}
