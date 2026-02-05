<?php

namespace App\Http\Controllers\Tenant\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Tenant\UserAccessService;
use App\Services\Tenant\PowerbiService;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\HelpArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    protected UserAccessService $userAccessService;
    protected PowerbiService $powerbiService;
    use \App\Traits\LogsUserActivity;

    public function __construct(UserAccessService $userAccessService, PowerbiService $powerbiService)
    {
        $this->userAccessService = $userAccessService;
        $this->powerbiService = $powerbiService;
    }

    /**
     * Display the main dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get recent files accessible by user
        $recentFiles = $this->userAccessService
            ->getAccessibleFiles($user)
            ->latest()
            ->take(5)
            ->get();

        // if customer role, redirect to files page
        if ($user->isCustomerRole()) {
            return redirect()->route('tenant.customer.index');
        }

        // Get powerbi links for user
        $powerbiLinks = $this->powerbiService->getLinksForUser($user);

        // Get expiring files (for admin/super users)
        $expiringFiles = [];
        if ($user->isAdminRole() || $user->isSuperUserRole()) {
            $expiringFiles = $this->userAccessService
                ->getAccessibleFiles($user)
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>=', now())
                ->orderBy('expiry_date')
                ->take(5)
                ->get();
        }

        $commodities = $this->userAccessService->getAccessibleCommodities($user);

        // Dashboard statistics
        $stats = $this->getDashboardStats($user);

        return view('tenant.dashboard.index', compact(
            'recentFiles',
            'powerbiLinks',
            'expiringFiles',
            'stats', 'commodities'
        ));
    }

    /**
     * Get dashboard statistics based on user role
     */
    private function getDashboardStats($user): array
    {
        $stats = [];

        if ($user->isAdminRole() || $user->isSuperUserRole()) {
            // Admin stats
            $stats = [
                'total_files' => \App\Models\Tenant\File::where('is_active', true)->count(),
                'total_users' => \App\Models\Tenant\User::where('is_active', true)->count(),
                'total_companies' => \App\Models\Tenant\Company::where('is_active', true)->count(),
                'expired_files' => \App\Models\Tenant\File::whereNotNull('expiry_date')
                    ->where('expiry_date', '<', now())
                    ->where('is_active', true)
                    ->count(),
            ];
        } elseif ($user->isGrowerRole()) {
            // Grower stats
            $stats = [
                'my_files' => $user->files()->where('is_active', true)->count(),
                'accessible_files' => $this->userAccessService
                    ->getAccessibleFiles($user)
                    ->count(),
                'powerbi_dashboards' => $this->powerbiService->getLinksForUser($user)->count(),
                'commodities' => $user->commodities->count(),
                // count of accessible files per commodity
                'commodity_files' => $user->commodities->mapWithKeys(function ($commodity) use ($user) {
                    return [$commodity->id => $this->userAccessService
                        ->getAccessibleFilesByCommodity($commodity, $user)
                        ->count()];
                })->toArray(),
                // 'commodity_files' => $user->commodities->mapWithKeys(function ($commodity) {
                //     return [$commodity->id => $commodity->countFiles()];
                // })->toArray(),
            ];
        } elseif ($user->isCustomerRole()) {
            // Customer stats
            $stats = [
                'accessible_files' => $this->userAccessService
                    ->getAccessibleFiles($user)
                    ->count(),
                'powerbi_dashboards' => $this->powerbiService->getLinksForUser($user)->count(),
                'commodities' => $user->commodities->count(),
            ];
        } elseif ($user->isDoleRole()) {
            // Dole stats
            $stats = [
                'total_files' => \App\Models\Tenant\File::where('is_active', true)->count(),
                'quality_reports' => \App\Models\Tenant\File::whereHas('fileType', function ($q) {
                    $q->where('name', 'Quality Reports');
                })->where('is_active', true)->count(),
                'powerbi_dashboards' => $this->powerbiService->getLinksForUser($user)->count(),
            ];
        }

        return $stats;
    }

    /**
     * Show user profile
     */
    public function profile()
    {
        $user = Auth::user();
        return view('dashboard.profile', compact('user'));
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'name' => 'required|string|max:255',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);

            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'phone' => $request->phone,
                'name' => $request->name,
            ]);

            // log activity
            $this->logUserActivity(
                'profile_update',
                'users',
                $user->id,
                'User updated their own profile'
            );

            return redirect()->route('dashboard.profile')
                ->with('success', 'Profile updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to update profile: ' . $e->getMessage());
            return redirect()->route('dashboard.profile')->with('error', 'Failed to update profile.');
        }
    }

    /**
     * Display user notifications (unread)
     */
    public function notifications()
    {
        $user = Auth::user();
        $notifications = $user->unreadNotifications()->latest()->paginate(10);
        return view('dashboard.notifications.index', compact('notifications'));
    }

    /**
     * Mark a notification as read
     */
    public function notificationsMarkAsRead(Request $request)
    {
        $request->validate([
            'notification_id' => 'required|exists:user_notifications,id',
        ]);

        $user = Auth::user();
        $notification = $user->notifications()->where('id', $request->notification_id)->first();

        if ($notification) {
            $notification->markAsRead();
            return redirect()->back()->with('success', 'Notification marked as read.');
        }

        return redirect()->back()->with('error', 'Notification not found.');
    }

    /**
     * Mark all notifications as read
     */
    public function notificationsMarkAllAsRead()
    {
        try {
            $user = Auth::user();
            $userNotifications = $user->notifications()->where('is_read', false)->get();
            //loop through and mark each as read
            foreach ($userNotifications as $notification) {
                // \Log::info('Marking notification as read: ' . $notification->id);
                $notification->markAsRead();
            }
            
            return redirect()->back()->with('success', 'All notifications marked as read.');
        } catch (\Exception $e) {
            \Log::error('Failed to mark all notifications as read: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to mark all notifications as read.');
        }

    }

    /**
     * Display the central knowledge base
     */
    public function knowledgeBase()
    {
        // use defined constant for path
        $knowledgeBasePath = base_path('resources/knowledge-base/ADMIN_KNOWLEDGE_BASE.md');
        
        if (!file_exists($knowledgeBasePath)) {
            abort(404, 'Portal administration knowledge base not found');
        }
        
        $content = file_get_contents($knowledgeBasePath);

        return view('dashboard.knowledge-base', compact('content'));
    }

    /**
     * Display the help center with all active help articles
     */
    public function help()
    {
        $articles = \App\Models\Tenant\HelpArticle::where('is_active', true)
            ->orderBy('updated_at', 'desc')
            ->get();
        return view('dashboard.help', compact('articles'));
    }

    /**
     * Display an error page for tenants
     */
    public function error()
    {
        return view('tenant.dashboard.error');
    }
}