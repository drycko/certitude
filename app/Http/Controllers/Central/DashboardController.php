<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Display the central admin dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get statistics for dashboard
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'total_users' => User::count(),
            'recent_tenants' => Tenant::with('domains')
                ->latest()
                ->take(5)
                ->get(),
        ];

        return view('central.dashboard', compact('stats'));
    }
}
