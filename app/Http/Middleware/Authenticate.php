<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            // Check if we're on the central portal route (tenant admin manages subscriptions on central domain)
            if ($request->is('p/*') || $request->is('portal') || $request->is('portal/*')) {
                // Only redirect to portal.login if we're on central domain
                if ($request->getHost() === config('tenancy.central_domains')[0]) {
                    return route('portal.login');
                }
                // If somehow accessing /p/* on tenant domain, redirect to tenant login
                return route('tenant.login');
            }
            
            // Check if we're on guest portal routes (passwordless authentication)
            if ($request->is('guest/*') || $request->is('guest-portal/*')) {
                return route('tenant.guest-portal.login');
            }
            
            // Check if we're on tenant admin routes (/t/* or /dashboard, etc.)
            if ($request->is('t/*') || $request->is('dashboard') || $request->is('dashboard/*')) {
                return route('tenant.login');
            }
            
            // Check if we're in a tenant context (not central domain)
            if ($request->getHost() !== config('tenancy.central_domains')[0]) {
                // For general tenant routes, redirect to tenant admin login
                return route('tenant.login');
            }
            
            // For central domain - redirect to central login
            return route('central.login');
        }
        return null;
    }
}