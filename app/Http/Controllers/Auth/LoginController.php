<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Database\Models\Domain;

class LoginController extends Controller
{
    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($this->attemptLogin($request)) {
            $request->session()->regenerate();
            return redirect()->intended('central/dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }
    
    public function showLoginForm()
    {
        $domain = request()->getHost();
        $isCentralDomain = in_array($domain, config('tenancy.central_domains'));
        
        return view('auth.login', compact('isCentralDomain'));
    }

    protected function attemptLogin(Request $request)
    {
        $credentials = $this->credentials($request);
        
        // Add tenant scope for tenant domain logins
        $domain = request()->getHost();
        if (!in_array($domain, config('tenancy.central_domains'))) {
            $tenantDomain = Domain::where('domain', $domain)->first();
            if ($tenantDomain) {
                $credentials['tenant_id'] = $tenantDomain->tenant_id;
            }
        }
        
        return $this->guard()->attempt(
            $credentials, $request->filled('remember')
        );
    }

    /**
     * Get the guard to be used during authentication.
     */
    protected function guard()
    {
        return Auth::guard('web'); // Use 'web' guard for central admin
    }

    /**
     * Get the needed authentication credentials from the request.
     */
    protected function credentials(Request $request)
    {
        return $request->only('email', 'password');
    }
}