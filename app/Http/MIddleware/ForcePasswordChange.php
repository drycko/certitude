<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        // Skip if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Skip if already on password change route
        if ($request->routeIs('password.change*') || $request->routeIs('logout')) {
            return $next($request);
        }

        // Skip for API routes
        if ($request->is('api/*')) {
            return $next($request);
        }

        // Check if user must change password
        if ($user->must_change_password) {
            return redirect()->route('password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return $next($request);
    }
}