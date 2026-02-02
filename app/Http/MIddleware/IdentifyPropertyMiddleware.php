<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Property;

class IdentifyPropertyMiddleware
{
    public function handle($request, Closure $next)
    {
        // Skip for auth routes and API auth routes
        if ($this->shouldSkipMiddleware($request)) {
            return $next($request);
        }

        // For authenticated users
        if (auth()->check()) {
            $user = auth()->user();

            // Skip property identification for super admin (property_id = null)
            if ($user->property_id === null) {
                // Super admin can access all properties, so we don't set a specific property
                return $next($request);
            }
            
            // For regular users with a property
            if ($user->property_id) {
                $property = Property::find($user->property_id);
                if ($property) {
                    $request->attributes->set('currentProperty', $property);
                    app()->instance('currentProperty', $property);
                    return $next($request);
                }
            }
        }

        // If no property is found and user is not super admin, redirect to property selection
        return redirect()->route('property.select');
    }

    protected function shouldSkipMiddleware(Request $request): bool
    {
        $skipRoutes = [
            'tenant.login',
            'tenant.register',
            'password/*',
            'auth/*',
            'property/select',
            'sanctum/*', // Skip for Sanctum routes
            'api/login',
            'api/register',
            'central/*', // Add this for central routes
        ];

        foreach ($skipRoutes as $route) {
            if ($request->routeIs($route)) {
                return true;
            }
        }

        // foreach ($skipRoutes as $route) {
        //     if ($request->is($route)) {
        //         return true;
        //     }
        // }

        return false;
    }
}