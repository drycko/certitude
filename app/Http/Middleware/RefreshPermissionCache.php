<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class RefreshPermissionCache
{
    protected PermissionRegistrar $permissionRegistrar;

    public function __construct(PermissionRegistrar $permissionRegistrar)
    {
        $this->permissionRegistrar = $permissionRegistrar;
    }

    /**
     * Handle an incoming request.
     *
     * Instead of forgetting the global permission cache on every central request,
     * we instruct the PermissionRegistrar to use the tenant-specific cache key for this request.
     * Only when the cache key actually changes we call forgetCachedPermissions() to avoid stale keys.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currentTenantId = null;
        try {
            $currentTenantId = function_exists('tenant') ? tenant('id') : null;
        } catch (\Throwable $e) {
            $currentTenantId = null;
        }

        // Build the desired cache key suffix for comparison
        $desiredSuffix = $currentTenantId ? "tenant:{$currentTenantId}" : 'central';
        $desiredKey = 'spatie.permission.cache:' . $desiredSuffix;

        // Compare with the registrar's current configured key.
        // Some PermissionRegistrar implementations store $this->cacheKey as protected,
        // so we attempt to read it; if not accessible we fall back to forcing set.
        $currentRegisteredKey = null;
        try {
            // attempt to read property; property may be protected - use reflection
            $reflection = new \ReflectionObject($this->permissionRegistrar);
            if ($reflection->hasProperty('cacheKey')) {
                $prop = $reflection->getProperty('cacheKey');
                $prop->setAccessible(true);
                $currentRegisteredKey = $prop->getValue($this->permissionRegistrar);
            }
        } catch (\Throwable $e) {
            // ignore - we will still set the key below
        }

        if ($currentRegisteredKey !== $desiredKey) {
            // set the registrar cache key (calls our TenantAwarePermissionRegistrar::setTenantCacheKey if available)
            try {
                if (method_exists($this->permissionRegistrar, 'setTenantCacheKey')) {
                    $this->permissionRegistrar->setTenantCacheKey($currentTenantId);
                } else {
                    // Fallback: attempt to set cacheKey property directly
                    $reflection = new \ReflectionObject($this->permissionRegistrar);
                    if ($reflection->hasProperty('cacheKey')) {
                        $prop = $reflection->getProperty('cacheKey');
                        $prop->setAccessible(true);
                        $prop->setValue($this->permissionRegistrar, $desiredKey);
                    }
                }

                // Only forget cached permissions when switching keys to avoid unnecessary DB hits
                $this->permissionRegistrar->forgetCachedPermissions();

                Session::put('last_tenant_context', $currentTenantId);
                // Log::debug('RefreshPermissionCache: set/changed permission cache key', [
                //     'desiredKey' => $desiredKey,
                //     'tenant_id' => $currentTenantId,
                // ]);
            } catch (\Throwable $e) {
                Log::warning('RefreshPermissionCache failed to set tenant cache key', ['exception' => $e->getMessage()]);
            }
        } else {
            // Log::debug('RefreshPermissionCache: cache key unchanged', ['cache_key' => $currentRegisteredKey]);
        }

        return $next($request);
    }
}