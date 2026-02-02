<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMustChangePassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->guard('tenant')->user();

        // Check if user is authenticated and must change password
        if ($user && $user->must_change_password) {
            // Allow access to change password routes and logout
            $allowedRoutes = [
                'tenant.password.change',
                'tenant.password.update',
                'tenant.logout',
            ];

            if (!in_array($request->route()->getName(), $allowedRoutes)) {
                return redirect()
                    ->route('tenant.password.change')
                    ->with('warning', 'You must change your password before continuing.');
            }
        }

        return $next($request);
    }
}
