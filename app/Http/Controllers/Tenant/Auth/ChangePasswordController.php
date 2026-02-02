<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    /**
     * Display the change password form
     */
    public function create()
    {
        $user = auth()->guard('tenant')->user();
        
        // Only allow access if user must change password
        if (!$user->must_change_password) {
            return redirect()->route('tenant.dashboard');
        }
        
        return view('tenant.auth.passwords.change');
    }

    /**
     * Handle the password change request
     */
    public function store(Request $request)
    {
        $user = auth()->guard('tenant')->user();
        
        // Validate the request
        $request->validate([
            'current_password' => ['required', 'current_password:tenant'],
            'password' => ['required', 'confirmed', Password::min(8)
                ->mixedCase()
                ->numbers()
                ->symbols()
            ],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.mixed' => 'Password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one special character.',
        ]);

        // Update the password
        $user->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        // Log the activity
        \Log::info("User {$user->email} changed their password successfully");

        return redirect()
            ->route('tenant.dashboard')
            ->with('success', 'Your password has been changed successfully. You can now access your account.');
    }
}
