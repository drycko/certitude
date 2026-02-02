<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckResourceLimit
{
    /**
     * Resource type to count mapping
     */
    protected array $resourceCounts = [
        'users' => 'max_users',
        'properties' => 'max_properties',
        'bookings' => 'max_bookings_per_month',
        'amenities' => 'max_amenities',
        'storage' => 'max_storage_gb',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $resource  The resource type to check (users, properties, bookings, etc.)
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = tenant();

        if (!$tenant || !$tenant->hasActiveSubscription()) {
            return $next($request);
        }

        // Map resource to limitation key
        $limitationKey = $this->resourceCounts[$resource] ?? null;

        if (!$limitationKey) {
            // Unknown resource type, allow through
            return $next($request);
        }

        // Get current count based on resource type
        $currentCount = $this->getCurrentCount($tenant, $resource);

        // Check if within limit
        if (!$tenant->isWithinLimit($limitationKey, $currentCount)) {
            $remaining = $tenant->getRemainingLimit($limitationKey, $currentCount);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Resource limit reached.',
                    'message' => "You have reached your plan's limit for {$resource}. Current: {$currentCount}",
                    'limit_key' => $limitationKey,
                    'current_count' => $currentCount,
                    'remaining' => $remaining,
                ], 403);
            }

            return redirect()->back()
                ->with('error', "You have reached your plan's limit for {$resource}. Please upgrade your subscription to add more.");
        }

        return $next($request);
    }

    /**
     * Get current count for a resource type
     */
    protected function getCurrentCount($tenant, string $resource): int
    {
        return match ($resource) {
            // 'users' => tenancy()->central(function () use ($tenant) {
            //     return \App\Models\User::where('tenant_id', $tenant->id)->count();
            // }),
            'users' => \App\Models\Tenant\User::count(),
            'properties' => \App\Models\Tenant\Property::count(),
            'bookings' => \App\Models\Tenant\Booking::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'amenities' => \App\Models\Tenant\Amenity::count(),
            'storage' => $this->calculateStorageUsage($tenant),
            default => 0,
        };
    }

    /**
     * Calculate storage usage in GB
     */
    protected function calculateStorageUsage($tenant): int
    {
        $storagePath = storage_path("app/tenant{$tenant->id}");
        
        if (!file_exists($storagePath)) {
            return 0;
        }

        $totalSize = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($storagePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $totalSize += $file->getSize();
            }
        }

        // Convert bytes to GB
        return (int) ceil($totalSize / (1024 * 1024 * 1024));
    }
}
