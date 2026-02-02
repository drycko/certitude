<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant\Guest;

class GuestPortalMiddleware
{
    public function handle($request, Closure $next)
    {
        $guest = null;
        if (session('guest_id')) {
            $guest = Guest::find(session('guest_id'));
        }
        view()->share('guest', $guest);
        return $next($request);
    }
}