<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the tenant admin login form
     */
    public function showLoginForm()
    {
        return view('portal.auth.login');
    }

    /**
     * Handle tenant admin login attempt
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $remember = $request->has('remember');

        if (Auth::guard('tenant_admin')->attempt($credentials, $remember)) {
            $request->session()->regenerate();
            
            // Update last login timestamp
            Auth::guard('tenant_admin')->user()->update([
                'last_login_at' => now(),
            ]);

            return redirect()->intended(route('portal.dashboard'))
                ->with('success', 'Welcome back!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log the tenant admin out
     */
    public function logout(Request $request)
    {
        Auth::guard('tenant_admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')
            ->with('success', 'You have been logged out successfully.');
    }
}
