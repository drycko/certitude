<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $limitType  The type of limit to check (properties, users, rooms, guests, etc.)
     */
    public function handle(Request $request, Closure $next, string $limitType): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            return $next($request);
        }

        $subscription = $tenant->currentSubscription();

        if (!$subscription || !$subscription->plan) {
            return $next($request);
        }

        $plan = $subscription->plan;
        $exceeded = false;
        $message = '';

        // Check the specific limit type
        switch ($limitType) {
            case 'properties':
                if ($plan->max_properties > 0) {
                    $currentCount = \App\Models\Tenant\Property::count();
                    if ($currentCount >= $plan->max_properties) {
                        $exceeded = true;
                        $message = "You have reached your plan limit of {$plan->max_properties} properties. Please upgrade your plan to add more.";
                    }
                }
                break;

            case 'users':
                if ($plan->max_users > 0) {
                    $currentCount = \App\Models\Tenant\User::count();
                    if ($currentCount >= $plan->max_users) {
                        $exceeded = true;
                        $message = "You have reached your plan limit of {$plan->max_users} users. Please upgrade your plan to add more.";
                    }
                }
                break;

            case 'rooms':
                if ($plan->max_rooms > 0) {
                    $currentCount = \App\Models\Tenant\Room::count();
                    if ($currentCount >= $plan->max_rooms) {
                        $exceeded = true;
                        $message = "You have reached your plan limit of {$plan->max_rooms} rooms. Please upgrade your plan to add more.";
                    }
                }
                break;

            case 'guests':
                if ($plan->max_guests > 0) {
                    $currentCount = \App\Models\Tenant\Guest::count();
                    if ($currentCount >= $plan->max_guests) {
                        $exceeded = true;
                        $message = "You have reached your plan limit of {$plan->max_guests} guests. Please upgrade your plan to add more.";
                    }
                }
                break;

            case 'analytics':
                if (!$plan->has_analytics) {
                    $exceeded = true;
                    $message = "Analytics is not available in your current plan. Please upgrade to access this feature.";
                }
                break;

            case 'api':
                if (!$plan->has_api_access) {
                    $exceeded = true;
                    $message = "API access is not available in your current plan. Please upgrade to access this feature.";
                }
                break;

            case 'support':
                if (!$plan->has_support) {
                    $exceeded = true;
                    $message = "Priority support is not available in your current plan. Please upgrade to access this feature.";
                }
                break;
        }

        if ($exceeded) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => $message,
                    'limit_type' => $limitType,
                    'upgrade_url' => route('tenant.subscription.upgrade')
                ], 403);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }
}
