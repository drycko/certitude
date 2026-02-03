<?php

namespace App\Http\Controllers\Tenant\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Models\Tenant\Company;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\UserGroup;
use App\Models\Tenant\Grower;
use App\Models\Tenant\Fbo;
use App\Models\Tenant\AccessRequest;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    protected NotificationService $notificationService;
    // Log user activities and notifications
    use \App\Traits\LogsUserActivity;

    public function __construct(NotificationService $notificationService)
    {
        $this->middleware(['auth', 'permission:view users'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create users'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit users'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete users'])->only(['destroy']);
        $this->middleware(['auth', 'permission:impersonate users'])->only(['impersonate']);
        // middleware for stop impersonation should be under auth only
        $this->middleware(['auth'])->only(['stopImpersonation']); # Why am I getting 403 here? - I moved the route out of the permission group
        
        $this->notificationService = $notificationService;
    }

    /**
     * Display users list
     */
    public function index(Request $request)
    {
        $query = User::with(['company', 'roles', 'commodities']);

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'company_id' => $request->input('company_id'),
            'commodity_id' => $request->input('commodity_id'),
            'role' => $request->input('role'),
            'user_status' => $request->input('user_status'),
            'sort_by' => $request->input('sort_by', 'id'),
            'sort_direction' => $request->input('sort_direction', 'desc'),
        ];

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('grower_number', 'like', "%{$search}%");
            });
        }

        // Filter by company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by commodity
        if ($request->filled('commodity_id')) {
            $query->whereHas('commodities', function ($q) use ($request) {
                $q->where('commodity_id', $request->commodity_id);
            });
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        // Filter by user status
        if ($request->filled('user_status')) {
            $query->where('is_active', $request->user_status === 'active');
        }

        // Sorting
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];
        $allowedSortFields = ['id', 'name', 'email', 'created_at', 'company_id'];
        
        if (in_array($sortBy, $allowedSortFields) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            // Default sort
            $query->orderBy('id', 'desc');
        }

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $users = $query->paginate($query->count())->withQueryString();
        } else {
            $users = $query->paginate($perPage)->withQueryString();
        }

        // Get filter options
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();

        return view('users.index', compact('users', 'companies', 'commodities', 'roles', 'filters'));
    }

    /**
     * Show user creation form
     */
    public function create()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $growers = Grower::where('is_active', true)->orderBy('name')->get();
        $fbos = Fbo::where('is_active', true)->orderBy('name')->get();
        
        // Get N/A default company
        $defaultCompany = Company::getDefaultCompany();

        // Prefill data: priority (A) old input from validation error, (B) session flash, (C) query string
        $prefill = [];
        if (session()->has('prefill')) {
            $prefill = session('prefill');
            // \Log::debug('User creation prefill data from session:', $prefill);
        } elseif (request()->has('prefill')) {
            $prefill = request()->input('prefill');
            // \Log::debug('User creation prefill data from request:', $prefill);
        } elseif (request()->all()) {
            $prefill = request()->all();
            // \Log::debug('User creation prefill data from request all():', $prefill);
        }
        // nothing is coming through any of the debug logs? )

        return view('users.create', compact('companies', 'roles', 'commodities', 'growers', 'fbos', 'prefill', 'defaultCompany'));
    }
    // public function create()
    // {
    //     $companies = Company::where('is_active', true)->orderBy('name')->get();
    //     $roles = Role::orderBy('name')->get();
    //     $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
    //     $growers = Grower::where('is_active', true)->orderBy('name')->get(); // for grower number selection

    //     return view('users.create', compact('companies', 'roles', 'commodities', 'growers'));
    // }

    /**
     * Store new user
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'company_id' => 'sometimes|exists:companies,id', // if company_id is not provided
            'role' => 'required|exists:roles,name',
            'growers' => 'nullable|array', // growers is optional but must be an array if provided
            'growers.*' => 'exists:growers,id', // each grower must exist
            'fbos' => 'nullable|array', // fbos is optional but must be an array if provided
            'fbos.*' => 'exists:fbos,id', // each fbo must exist
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'commodities' => 'nullable|array',
            'commodities.*' => 'exists:commodities,id',
        ]);

        // Validate growers for grower role
        if ($request->role === 'grower' && (empty($request->growers) || !is_array($request->growers) || count($request->growers) === 0)) {
            return back()->withErrors(['growers' => 'At least one grower is required for grower role.']);
        }

        $growerIds = null;
        $companyId = $request->company_id ?? null;

        // find us the first grower number from the selected growers
        $main_grower_number = null;
        if ($request->filled('growers') && is_array($request->growers) && count($request->growers) > 0) {
            $growerIds = $request->input('growers');
            $firstGrower = Grower::find($growerIds[0]);
            if ($firstGrower) {
                $main_grower_number = $firstGrower->grower_number;
            }
        }

        // if company_id is not provided, we should create a new company
        if (!$companyId && $request->has('new_company_name')) {
            $companyName = $request->input('new_company_name');
            $companyAddress = $request->input('new_company_address');

            if (empty($companyName)) {
                return back()->withErrors(['new_company_name' => 'Company name is required when no company is selected.'])->withInput();
            }
            if (empty($companyAddress)) {
                return back()->withErrors(['new_company_address' => 'Company address is required when no company is selected.'])->withInput();
            }
        } else {
            // if user is not a grower and company_id is not provided and new_company_name is not provided, return error
            if (!$companyId && !$growerId) {
                return back()->withErrors(['company_id' => 'Please select a company or enter a new company.'])->withInput();
            }
        }

        DB::beginTransaction();
        
        try {
            // Generate temporary password
            $temporaryPassword = $this->notificationService->generateTemporaryPassword();

            $accessRequestId = $request->input('access_request_id');
            if ($accessRequestId) {
                // Optionally, fetch the access request and store metadata
                $accessRequest = AccessRequest::find($accessRequestId);
                // Attach metadata or link as necessary
                if ($accessRequest) {
                    // get additional metadata from access request and add them to user metadata
                    $request->merge([
                        'metadata' => array_merge($request->metadata ?? [], [
                            'access_request_id' => $accessRequest->id,
                            'country' => $accessRequest->additional_data['country'] ?? null,
                        ])
                    ]);
                }
            }

            // Create new company if needed
            if (!$companyId && $request->has('new_company_name')) {
                $companyData = [
                    'name' => $request->input('new_company_name'),
                    'code' => Company::generateCompanyCodeFromName($request->input('new_company_name')),
                    'address' => $request->input('new_company_address'),
                    'contact_person' => $request->input('new_company_contact_person') ?? $request->first_name . ' ' . $request->last_name,
                    'email' => $request->input('new_company_email') ?? $request->email,
                    'phone' => $request->input('new_company_phone') ?? $request->phone,
                    'is_active' => true,
                ];
                $company = Company::create($companyData);
                $request->merge(['company_id' => $company->id]);
                $companyId = $company->id;

                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'create',
                    'companies',
                    $companyId,
                    'Created company: ' . $company->name,
                    'Company "' . $company->name . '" created successfully while creating user.'
                );
            }
            
            // If no company_id provided, use N/A company as default
            if (!$companyId) {
                $defaultCompany = Company::getDefaultCompany();
                $companyId = $defaultCompany ? $defaultCompany->id : null;
            }

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($temporaryPassword),
                'company_id' => $companyId,
                'grower_number' => $main_grower_number ?? null,
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active', true),
                'must_change_password' => true,
                'metadata' => $request->metadata ?? [],
            ]);

            // Assign role
            $user->assignRole($request->role);

            // attach groups that match the role name
            $group = UserGroup::where('name', $request->role)->first();
            if ($group) {
                $user->userGroups()->attach($group);
            }

            // Attach commodities
            if ($request->commodities) {
                $user->commodities()->attach($request->commodities);
            }

            // if grower attach user to grower
            if ($request->filled('growers') && $request->role === 'grower') {
                $user->growers()->attach($request->input('growers'));
            }
            // if this was an access request approval, mark the access request as completed
            if ($request->input('access_request_id') && $accessRequest) {
                $accessRequest->update(['status' => 'approved']);
            }
            // if fbo is provided and role is grower, attach fbos
            if ($request->has('fbos') && $request->role === 'grower') {
                $user->fbos()->attach($request->input('fbos'));
            }

            DB::commit();

            // Send welcome email
            $this->notificationService->sendWelcomeEmail($user, $temporaryPassword);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'users',
                $user->id,
                'Created user: ' . $user->name,
                'User "' . $user->name . '" created successfully.'
            );

            return redirect()->route('users.index')
                ->with('success', 'User created successfully. Welcome email sent.');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Failed to create user', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to create user. Please try again.'])->withInput();
        }
    }

    /**
     * Show user details
     */
    public function show(User $user)
    {
        $user->load(['company', 'roles', 'commodities', 'userGroups', 'documents']);
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $groups = UserGroup::where('is_active', true)->orderBy('name')->get();

        // load user groups
        $user->load('userGroups');

        return view('users.show', compact('user', 'companies', 'roles', 'commodities', 'groups'));
    }

    /**
     * Show user edit form
     */
    public function edit(User $user)
    {
        $user->load(['company', 'commodities', 'documents', 'userGroups', 'growers', 'fbos']);
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $groups = UserGroup::where('is_active', true)->orderBy('name')->get();
        $growers = Grower::where('is_active', true)->orderBy('name')->get();
        
        $fbos = Fbo::where('is_active', true)->orderBy('name')->get();

        $prefill = [
            'growers' => $user->growers->pluck('id')->toArray(),
        ];

        return view('users.edit', compact('user', 'companies', 'roles', 'commodities', 'groups', 'growers', 'fbos', 'prefill'));
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'company_id' => 'nullable|exists:companies,id',
            'role' => 'required|exists:roles,name',
            'groups' => 'nullable|array',
            'groups.*' => 'exists:user_groups,id',
            'growers' => 'nullable|array',
            'growers.*' => 'exists:growers,id',
            'fbos' => 'nullable|array',
            'fbos.*' => 'exists:fbos,id',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'commodities' => 'nullable|array',
            'commodities.*' => 'exists:commodities,id',
        ]);

        DB::beginTransaction();
        
        try {
            // Update user
            $user->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => $request->first_name . ' ' . $request->last_name,
                'email' => $request->email,
                'company_id' => $request->company_id,
                'phone' => $request->phone,
                'is_active' => $request->boolean('is_active', true),
            ]);

            // Update role
            $user->syncRoles([$request->role]);

            // Update commodities
            if ($request->commodities) {
                $user->commodities()->sync($request->commodities);
            } else {
                $user->commodities()->detach();
            }

            // Update groups
            if ($request->groups) {
                $user->userGroups()->sync($request->groups);
            } else {
                $user->userGroups()->detach();
            }

            // Update growers
            if ($request->growers) {
                $user->growers()->sync($request->growers);
            } else {
                $user->growers()->detach();
            }

            // Update fbos
            if ($request->fbos) {
                $user->fbos()->sync($request->fbos);
            } else {
                $user->fbos()->detach();
            }

            DB::commit();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'users',
                $user->id,
                'Updated user: ' . $user->name,
                'User "' . $user->name . '" updated successfully.'
            );

            $page = $request->input('page', 1);
            return redirect()->route('users.index', ['page' => $page])
                ->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Failed to update user: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update user. Please try again.'])->withInput();
        }
    }

    /**
     * Search suggestions for user names and emails (for AJAX requests)
     */
    public function searchSuggestions(Request $request)
    {
        $query = $request->input('query');

        $users = User::where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
              ->orWhere('email', 'LIKE', "%{$query}%");
        })->get();

        return response()->json($users);
    }
    

    /**
     * Reset user password
     */
    public function resetPassword(User $user)
    {
        try {
            // Generate new temporary password
            $temporaryPassword = $this->notificationService->generateTemporaryPassword();
            
            $user->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);

            // Send notification
            $this->notificationService->sendWelcomeEmail($user, $temporaryPassword);

            return back()->with('success', 'Password reset successfully. New password sent to user.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to reset password. Please try again.']);
        }
    }

    /**
     * Update user password (Admin with verification)
     */
    public function updatePassword(Request $request, User $user)
    {
        // Validate request
        $request->validate([
            'admin_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
            'force_password_reset' => 'nullable|boolean',
        ]);

        try {
            // Verify admin password
            if (!Hash::check($request->admin_password, Auth::user()->password)) {
                return back()->withErrors(['admin_password' => 'Your admin password is incorrect.'])->withInput();
            }

            // Update user password
            $user->update([
                'password' => Hash::make($request->new_password),
                'must_change_password' => $request->boolean('force_password_reset', false),
                'password_changed_at' => now(),
            ]);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'users',
                $user->id,
                'Changed password for user: ' . $user->name,
                'Password for user "' . $user->name . '" changed successfully by ' . Auth::user()->name . '.'
            );

            return back()->with('success', 'Password updated successfully for ' . $user->name . '.');

        } catch (\Exception $e) {
            \Log::error('Failed to update user password: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update password. Please try again.'])->withInput();
        }
    }

    /**
     * Re-send email verification link
     */
    public function resendVerification(User $user)
    {
        try {
            if ($user->hasVerifiedEmail()) {
                return back()->with('info', 'User email is already verified.');
            }

            $this->notificationService->sendEmailVerification($user);

            return back()->with('success', 'Verification email resent successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to resend verification email. Please try again.']);
        }
    }

    /**
     * Re-send welcome registration email
     */
    public function resendWelcomeEmail(User $user)
    {
        try {
            // Clear cache lock to allow resending
            $cacheKey = "welcome_email_sent_{$user->id}";
            \Cache::forget($cacheKey);
            
            $temporaryPassword = $this->notificationService->generateTemporaryPassword();
            $user->update([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'password_changed_at' => null,
            ]);
            
            $result = $this->notificationService->sendWelcomeEmail($user, $temporaryPassword);
            
            if (!$result) {
                return back()->withErrors(['error' => 'Failed to send welcome email. User may be inactive.']);
            }

            return back()->with('success', 'Welcome email resent successfully.');

        } catch (\Exception $e) {
            \Log::error('Error resending welcome email: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['error' => 'Failed to resend welcome email. Please try again.']);
        }
    }

    /**
     * Mark email as verified (admin action)
     */
    public function markEmailAsVerified(User $user)
    {
        try {
            if ($user->hasVerifiedEmail()) {
                return back()->with('info', 'User email is already verified.');
            }

            $user->markEmailAsVerified();

            return back()->with('success', 'User email marked as verified successfully.');

        } catch (\Exception $e) {
            \Log::error('Failed to mark email as verified', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to mark email as verified. Please try again.']);
        }
    }

    /**
     * Impersonate user
     */
    public function impersonate(User $user)
    {
        try {
            // Store original user ID in session
            session(['impersonator_id' => Auth::id()]);
            Auth::login($user);
            return redirect()->route('dashboard')->with('success', 'You are now impersonating ' . $user->name);
        } catch (\Exception $e) {
            \Log::error('Failed to impersonate user', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to impersonate user. Please try again.']);
        }
    }
    /**
     * Stop impersonation (how do we make this work because this is under )
     */
    public function stopImpersonation()
    {
        try {
            // Assuming original user ID is stored in session
            $originalUserId = session('impersonator_id');
            if ($originalUserId) {
                $originalUser = User::find($originalUserId);
                // Log back in as original user
                Auth::login($originalUser);
                session()->forget('impersonator_id');
                return redirect()->route('dashboard')->with('success', 'Impersonation ended. You are now back to your account.');
            } else {
                return back()->withErrors(['error' => 'No impersonation session found.']);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to stop impersonation', ['error' => $e->getMessage()]);
            return back()->withErrors(['error' => 'Failed to stop impersonation. Please try again.']);
        }
    }
    /**
     * Delete user
     */
    public function destroy(User $user)
    {
        try {
            // soft deletes instead of delete to preserve document relationships
            $user->delete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'users',
                $user->id,
                'Moved user to trash: ' . $user->name,
                'User "' . $user->name . '" soft deleted successfully.'
            );
            
            return redirect()->route('users.index')
                ->with('success', 'User soft deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to soft delete user. Please try again.']);
        }
    }

    /**
     * Toggle user active status (activate/deactivate)
     */
    public function toggleStatus(User $user)
    {
        try {
            // Toggle the is_active status
            $user->is_active = !$user->is_active;
            $user->save();

            $status = $user->is_active ? 'activated' : 'deactivated';

            // Log activity
            $this->logUserActivityAndNotification(
                'update',
                'users',
                $user->id,
                "User {$status}: {$user->name}",
                "User \"{$user->name}\" has been {$status} successfully."
            );

            return back()->with('success', "User has been {$status} successfully.");

        } catch (\Exception $e) {
            \Log::error('Failed to toggle user status', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return back()->withErrors(['error' => 'Failed to update user status. Please try again.']);
        }
    }

    /**
     * Display trashed users list
     */
    public function trashed(Request $request)
    {
        $query = User::onlyTrashed()->with(['company', 'roles', 'commodities']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('grower_number', 'like', "%{$search}%");
            });
        }

        // Filter by company
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filter by role
        if ($request->filled('role')) {
            $query->role($request->role);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }
        // latest users first
        $users = $query->orderBy('id', 'desc')->paginate(20);

        // Get filter options
        $companies = Company::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $roles = Role::orderBy('name')->get();
        return view('users.trashed', compact('users', 'companies', 'commodities', 'roles'));
    }

    /**
     * Restore soft deleted user
     */
    public function restore($id)
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);

            // make sure user is soft deleted
            if (!$user->trashed()) {
                return back()->withErrors(['error' => 'User is not in trash.']);
            }
            $user->restore();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'users',
                $user->id,
                'Restored user: ' . $user->name,
                'User "' . $user->name . '" restored successfully.'
            );

            return redirect()->route('trashed-data.users.index')
                ->with('success', 'User restored successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to restore user. Please try again.']);
        }
    }

    /**
     * Permanently delete user
     */
    public function forceDelete($id)
    {
        try {
            $user = User::onlyTrashed()->findOrFail($id);
            $userId = $user->id;
            // make sure user is soft deleted
            if (!$user->trashed()) {
                return back()->withErrors(['error' => 'User must be soft deleted before permanent deletion.']);
            }
            // make sure user has no documents
            if ($user->documents()->exists()) {
                return back()->withErrors(['error' => 'User cannot be permanently deleted because they have associated documents. Please reassign or delete the documents first.']);
            }
            
            // permanently delete user
            $user->forceDelete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'users',
                $userId,
                'Permanently deleted user: ' . $user->name,
                'User "' . $user->name . '" permanently deleted successfully.'
            );

            return redirect()->route('trashed-data.users.index')
                ->with('success', 'User permanently deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to permanently delete user. Please try again.']);
        }
    }
}