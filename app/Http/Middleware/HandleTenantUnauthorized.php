<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

class HandleTenantUnauthorized
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (AuthorizationException $e) {
            // Log the authorization failure for debugging
            Log::warning('Authorization failed', [
                'user_id' => auth('tenant')->id(),
                'url' => $request->fullUrl(),
                'message' => $e->getMessage(),
            ]);

            // If this is an AJAX request, return JSON response
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action. Please select a property from the dashboard.',
                    'redirect' => route('tenant.dashboard')
                ], 403);
            }

            // For regular requests, redirect to dashboard with error message
            return redirect()
                ->route('tenant.dashboard')
                ->with('error', 'Unauthorized action. Please select a property to continue.');
        }
    }
}
