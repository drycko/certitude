<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;


// app/Providers/RouteServiceProvider.php to separate central and tenant routes
class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';
    public const TENANT_HOME = '/t/dashboard';
    public const PORTAL_HOME = '/p/dashboard';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
        
        $this->routes(function () {
            // Central authentication routes (must be loaded first)
            Route::middleware('web')
                ->domain(config('tenancy.central_domains')[0])
                ->group(base_path('routes/auth.php'));
                
            // Central routes (admin, tenant management)
            Route::middleware('web')
                ->domain(config('tenancy.central_domains')[0])
                ->group(base_path('routes/web.php'));
                
            // Portal routes (tenant admin billing/subscription management)
            Route::middleware('web')
                ->domain(config('tenancy.central_domains')[0])
                ->group(base_path('routes/portal.php'));
                
            // API routes
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
                
            // Tenant routes (customer-facing applications)
            Route::middleware('web')
                ->group(base_path('routes/tenant.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
