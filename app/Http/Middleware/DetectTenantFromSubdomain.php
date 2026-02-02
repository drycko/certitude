<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant;

class DetectTenantFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());

        // Try to use the tenancy package's configured central domain first (more authoritative)
        $centralDomains = config('tenancy.central_domains', []);
        $base = null;
        if (!empty($centralDomains) && is_array($centralDomains)) {
            $base = strtolower($centralDomains[0]);
        } else {
            // fallback to APP_URL if tenancy config not present
            $appUrl = config('app.url') ?? '';
            $base = parse_url($appUrl, PHP_URL_HOST) ?: null;
        }

        // Normalize (strip ports, lowercase)
        $base = $base ? preg_replace('/:\d+$/', '', strtolower($base)) : null;
        $host = preg_replace('/:\d+$/', '', $host);

        Log::debug('DetectTenantFromSubdomain: running', [
            'host' => $host,
            'base' => $base,
        ]);

        // If host equals base (central domain), don't try to detect a tenant.
        if ($base && $host === $base) {
            // Central domain - clear any custom tenant instance we might set, but don't abort.
            app()->instance('current_tenant', null);
            Log::debug('DetectTenantFromSubdomain: central host detected - skipping tenant lookup', ['host' => $host]);
            return $next($request);
        }

        // If we don't have a base to compare to, bail out safely.
        if (! $base) {
            Log::warning('DetectTenantFromSubdomain: no base host configured, skipping tenant detection', ['host' => $host]);
            return $next($request);
        }

        // If host does not end with ".base", it's not a tenant subdomain; skip detection.
        if (! str_ends_with($host, '.' . $base)) {
            Log::debug('DetectTenantFromSubdomain: host does not appear to be a tenant subdomain - skipping', ['host' => $host, 'base' => $base]);
            return $next($request);
        }

        // Extract the tenant subdomain part (everything before ".base")
        $subdomain = substr($host, 0, -(strlen($base) + 1));
        if (empty($subdomain)) {
            Log::debug('DetectTenantFromSubdomain: empty subdomain extracted - skipping', ['host' => $host, 'base' => $base]);
            return $next($request);
        }

        // Lookup tenant by subdomain
        $tenant = Tenant::where('subdomain', $subdomain)->first();

        if ($tenant) {
            app()->instance('current_tenant', $tenant);
            Log::debug('DetectTenantFromSubdomain: tenant resolved', ['subdomain' => $subdomain, 'tenant_id' => $tenant->id]);
        } else {
            // Important: do NOT abort here. Logging and continuing is safer,
            // because stancl tenancy middleware or other layers may handle it,
            // and aborting causes central requests to fail if we mis-detect.
            Log::warning('DetectTenantFromSubdomain: tenant not found for subdomain', [
                'subdomain' => $subdomain,
                'host' => $host,
            ]);
            // keep current_tenant null and continue
            app()->instance('current_tenant', null);
        }

        return $next($request);
    }
}