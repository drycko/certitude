<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use App\Models\Commodity;
use Spatie\Permission\Models\Role;
use App\Traits\LogsUserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleAssignmentController extends Controller
{
  use LogsUserActivity;
  
  public function __construct()
  {

    $this->middleware(['auth', 'permission:assign roles']);
  }
  
  /**
  * Display role assignments overview
  */
  public function index(Request $request)
  {
    $query = User::with(['company', 'roles', 'commodities']);

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

    // filter by commodity
    if ($request->filled('commodity_id')) {
        $commodityId = $request->input('commodity_id');
        $query->whereHas('commodities', function ($q) use ($commodityId) {
            $q->where('commodity_id', $commodityId);
        });
    }

    // Filter by role
    if ($request->filled('role')) {
        $query->role($request->role);
    }

    // Filter by status
    if ($request->filled('user_status')) {
        $query->where('is_active', $request->user_status === 'active');
    }
    // latest users first
    $users = $query->orderBy('id', 'desc')->paginate(20);

    $roles = Role::orderBy('name')->get();
    // Get filter options
    $companies = Company::where('is_active', true)->orderBy('name')->get();
    $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

    // Collect filters for view persistence
    $filters = $request->only(['search', 'company_id', 'commodity_id', 'role', 'user_status']);
    return view('role-assignments.index', compact('users', 'roles', 'companies', 'commodities', 'filters'));
  }
  
  /**
  * Show role assignment form for a user
  */
  public function edit(User $user)
  {
    // Check if user can manage this user
    if (!$this->canManageUser($user)) {
      return redirect()
      ->route('role-assignments.index')
      ->with('error', 'You cannot manage roles for this user.');
    }
    
    $roles = Role::orderBy('name')->get();
    $userRoles = $user->roles->pluck('id')->toArray();
    
    return view('role-assignments.edit', compact('user', 'roles', 'userRoles'));
  }
  
  /**
  * Update user role assignments
  */
  public function update(Request $request, User $user)
  {
    if (!$this->canManageUser($user)) {
      return redirect()
      ->route('role-assignments.index')
      ->with('error', 'You cannot manage roles for this user.');
    }
    
    $request->validate([
      'roles' => ['nullable', 'array'],
      'roles.*' => ['exists:roles,id'],
    ]);
    
    DB::beginTransaction();
    try {
      $oldRoles = $user->roles->pluck('name')->toArray();
      
      if ($request->roles) {
        $roles = Role::whereIn('id', $request->roles)->get();
        $user->syncRoles($roles);
      } else {
        $user->syncRoles([]);
      }
      
      $newRoles = $user->fresh()->roles->pluck('name')->toArray();
      
      DB::commit();
      
      $this->logUserActivityAndNotification(
        'update',
        'roles',
        $user->id,
        'Updated roles for user: ' . $user->name,
        'Roles for user "' . $user->name . '" updated successfully.'
      );
        
      return redirect()
      ->route('role-assignments.index')
      ->with('success', "Roles updated successfully for {$user->name}.");
      
    } catch (\Exception $e) {
      DB::rollBack();
      return back()
      ->withInput()
      ->with('error', 'Failed to update roles. Please try again.');
    }
  }
    
    /**
    * Bulk assign role to multiple users
    */
    public function bulkAssign(Request $request)
    {
      $request->validate([
        'users' => ['required', 'array', 'min:1'],
        'users.*' => ['exists:users,id'],
        'role' => ['required', 'exists:roles,id'],
        'action' => ['required', 'in:assign,remove'],
      ]);
      
      $role = Role::findOrFail($request->role);
      $users = User::whereIn('id', $request->users);
      
      // Filter users that current user can manage
      $users = $users->get()->filter(function ($user) {
        return $this->canManageUser($user);
      });

      $users = $users->get();
      
      if ($users->isEmpty()) {
        return back()->with('warning', 'No valid users selected for role assignment.');
      }
      
      DB::beginTransaction();
      try {
        $updatedUsers = [];
        
        foreach ($users as $user) {
          if ($request->action === 'assign') {
            if (!$user->hasRole($role)) {
              $user->assignRole($role);
              $updatedUsers[] = $user->name;
            }
          } else {
            if ($user->hasRole($role)) {
              $user->removeRole($role);
              $updatedUsers[] = $user->name;
            }
          }
        }
        
        DB::commit();
        
        $this->logUserActivityAndNotification(
          'update',
          'roles',
          $user->id,
          'Updated roles for user: ' . $user->name,
          'Roles for user "' . $user->name . '" updated successfully.'
        );

        $message = count($updatedUsers) . " users updated with role '{$role->name}'.";
        return back()->with('success', $message);

      } catch (\Exception $e) {
          DB::rollBack();
          return back()->with('error', 'Failed to update user roles. Please try again.');
        }
      }
      
      /**
      * Check if current user can manage the target user
      */
      private function canManageUser(User $user)
      {
        $currentUser = auth()->user();
        
        // Super users can manage anyone
        if ($currentUser->hasRole(['super-user', 'admin'])) {
          return true;
        }
        
        // Users cannot manage themselves
        if ($currentUser->id === $user->id) {
          return false;
        }
        return false;
      }
    }