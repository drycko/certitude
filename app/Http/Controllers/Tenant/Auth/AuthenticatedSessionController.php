<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant\TenantUserActivity;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create()
    {
        return view('tenant.auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->authenticate('tenant');

        $request->session()->regenerate();

        $user = auth()->guard('tenant')->user();

        // Log the login activity
        TenantUserActivity::create([
            'tenant_user_id' => $user->id,
            'activity_type' => TenantUserActivity::ACTIVITY_TYPES['LOGIN'],
            'description' => 'User logged in',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'location' => $this->getLocationFromIp($request->ip()),
        ]);

        // Check if user must change password
        if ($user->must_change_password) {
            return redirect()
                ->route('tenant.password.change')
                ->with('warning', 'You must change your password before continuing.');
        }

        return redirect()->intended(RouteServiceProvider::TENANT_HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        $user = auth()->guard('tenant')->user();

        // Log the logout activity
        if ($user) {
            TenantUserActivity::create([
                'tenant_user_id' => $user->id,
                'activity_type' => TenantUserActivity::ACTIVITY_TYPES['LOGOUT'],
                'description' => 'User logged out',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('tenant')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('tenant.login');
    }

    /**
     * Get approximate location from IP address.
     */
    protected function getLocationFromIp($ip)
    {
        // For local/private IPs
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return 'Local Network';
        }

        // You can integrate with a GeoIP service here if needed
        // For now, just return null
        return null;
    }
}