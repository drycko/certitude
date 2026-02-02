<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  ...$guards
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // Redirect based on the guard
                switch ($guard) {
                    case 'tenant':
                        return redirect(RouteServiceProvider::TENANT_HOME);
                    case 'web':
                        // Check if on central domain
                        if ($request->getHost() === config('tenancy.central_domains')[0]) {
                            return redirect(RouteServiceProvider::HOME);
                        }
                        return redirect('/');
                    default:
                        return redirect('/');
                }
            }
        }

        return $next($request);
    }
}
