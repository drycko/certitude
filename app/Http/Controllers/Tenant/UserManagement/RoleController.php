<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Traits\LogsUserActivity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
  use LogsUserActivity;
  
  public function __construct()
  {
    $this->middleware(['auth', 'permission:view roles'])->only(['index', 'show']);
    $this->middleware(['auth', 'permission:create roles'])->only(['create', 'store']);
    $this->middleware(['auth', 'permission:edit roles'])->only(['edit', 'update', 'syncPermissions']);
    $this->middleware(['auth', 'permission:delete roles'])->only(['destroy']);
  }
  
  /**
   * Display a listing of roles
   */
  public function index()
  {
    try {
      $roles = Role::with('permissions')
        ->withCount('users')
        ->orderBy('name')
        ->get();
      
      return view('roles.index', compact('roles'));
      
    } catch (\Exception $e) {
      \Log::error('Roles index failed: ' . $e->getMessage());
      return back()->with('error', 'Failed to load roles. Please try again.');
    }
  }
  
  /**
   * Show the form for creating a new role
   */
  public function create()
  {
    try {
      $permissions = Permission::orderBy('name')->get();
      $groupedPermissions = $this->groupPermissions($permissions);
      
      return view('roles.create', compact('permissions', 'groupedPermissions'));
      
    } catch (\Exception $e) {
      \Log::error('Role create form failed: ' . $e->getMessage());
      return back()->with('error', 'Failed to load role creation form. Please try again.');
    }
  }
  
  /**
   * Store a newly created role
   */
  public function store(Request $request)
  {
    $request->validate([
      'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
      'display_name' => ['nullable', 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:500'],
      'permissions' => ['nullable', 'array'],
      'permissions.*' => ['exists:permissions,id'],
    ]);
    
    DB::beginTransaction();
    
    try {
      $role = Role::create([
        'name' => $request->name,
        'display_name' => $request->display_name,
        'description' => $request->description,
        'guard_name' => config('auth.defaults.guard', 'web'),
      ]);
      
      if ($request->has('permissions') && !empty($request->permissions)) {
        $permissions = Permission::whereIn('id', $request->permissions)->get();
        $role->syncPermissions($permissions);
      }
      
      DB::commit();
      
      $this->logUserActivityAndNotification(
        'create',
        'roles',
        $role->id,
        'Created role: ' . $role->name,
        'Role "' . $role->name . '" created successfully.'
      );
      
      return redirect()
        ->route('roles.show', $role)
        ->with('success', 'Role created successfully.');
        
    } catch (\Exception $e) {
      DB::rollBack();
      \Log::error('Role creation failed: ' . $e->getMessage());
      
      return back()
        ->withInput()
        ->with('error', 'Failed to create role. Please try again.');
    }
  }
  
  /**
   * Display the specified role
   */
  public function show(Role $role)
  {
    try {
      $role->load(['permissions', 'users' => function($query) {
        $query->orderBy('name');
      }]);
      
      $groupedPermissions = $this->groupPermissions($role->permissions);
      
      return view('roles.show', compact('role', 'groupedPermissions'));
      
    } catch (\Exception $e) {
      \Log::error('Role show failed: ' . $e->getMessage());
      return back()->with('error', 'Failed to load role details. Please try again.');
    }
  }
  
  /**
   * Show the form for editing the specified role
   */
  public function edit(Role $role)
  {
    // Prevent editing system roles (by just hiding the edit form but only show the permission assignment)
    // if ($this->isSystemRole($role)) {
    //   return redirect()
    //     ->route('roles.show', $role)
    //     ->with('warning', 'System roles cannot be edited.');
    // }
    $isSystemRole = $this->isSystemRole($role);
    
    try {
      $permissions = Permission::orderBy('name')->get();
      $groupedPermissions = $this->groupPermissions($permissions);
      $rolePermissions = $role->permissions->pluck('id')->toArray();

      return view('roles.edit', compact('role', 'permissions', 'groupedPermissions', 'rolePermissions', 'isSystemRole'));

    } catch (\Exception $e) {
      \Log::error('Role edit form failed: ' . $e->getMessage());
      return back()->with('error', 'Failed to load role edit form. Please try again.');
    }
  }
  
  /**
   * Update the specified role
   */
  public function update(Request $request, Role $role)
  {
    // Prevent editing system roles
    // if ($this->isSystemRole($role)) {
    //   return redirect()
    //     ->route('roles.show', $role)
    //     ->with('warning', 'System roles cannot be edited.');
    // }
    $isSystemRole = $this->isSystemRole($role);

    if ($isSystemRole) {
      // If it's a system role, skip validation for name and other basic info
      $request->validate([
        'permissions' => ['nullable', 'array'],
        'permissions.*' => ['exists:permissions,id'],
      ]);
    } else {
      $request->validate([
        'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
        'display_name' => ['nullable', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:500'],
        'permissions' => ['nullable', 'array'],
        'permissions.*' => ['exists:permissions,id'],
      ]);
    }
    
    DB::beginTransaction();
    
    try {
      if (!$role) {
        return redirect()
          ->route('roles.index')
          ->with('error', 'Role not found.');
      }

      $originalName = $role->name;
      
      // Update basic info only if not a system role
      if (!$isSystemRole) {
        $role->update([
          'name' => $request->name,
          'display_name' => $request->display_name,
          'description' => $request->description,
        ]);
      }
      
      // Handle permissions - sync with empty array if no permissions provided
      $permissions = $request->has('permissions') ? Permission::whereIn('id', $request->permissions)->get() : [];
      $role->syncPermissions($permissions);
      
      DB::commit();
      
      $this->logUserActivityAndNotification(
        'update',
        'roles',
        $role->id,
        'Updated role: ' . $originalName . ' to ' . $role->name,
        'Role "' . $role->name . '" updated successfully.'
      );
      
      return redirect()
        ->route('roles.show', $role)
        ->with('success', 'Role updated successfully.');
        
    } catch (\Exception $e) {
      DB::rollBack();
      \Log::error('Role update failed: ' . $e->getMessage());
      
      return back()
        ->withInput()
        ->with('error', 'Failed to update role. Please try again.');
    }
  }
  
  /**
   * Remove the specified role
   */
  public function destroy(Role $role)
  {
    // Prevent deleting system roles
    if ($this->isSystemRole($role)) {
      return redirect()
        ->route('roles.index')
        ->with('warning', 'System roles cannot be deleted.');
    }
    
    // Check if role has users
    if ($role->users()->count() > 0) {
      return redirect()
        ->route('roles.show', $role)
        ->with('warning', 'Cannot delete role that has assigned users. Please reassign users first.');
    }
    
    DB::beginTransaction();
    
    try {
      $roleName = $role->name;
      $roleId = $role->id;
      
      // Remove all permissions first
      $role->syncPermissions([]);
      
      $role->delete();
      
      DB::commit();
      
      $this->logUserActivityAndNotification(
        'delete',
        'roles',
        $roleId,
        'Deleted role: ' . $roleName,
        'Role "' . $roleName . '" deleted successfully.'
      );
      
      return redirect()
        ->route('roles.index')
        ->with('success', 'Role deleted successfully.');
        
    } catch (\Exception $e) {
      DB::rollBack();
      \Log::error('Role deletion failed: ' . $e->getMessage());
      
      return redirect()
        ->route('roles.show', $role)
        ->with('error', 'Failed to delete role. Please try again.');
    }
  }
  
  /**
   * Sync permissions for a role (AJAX endpoint)
   */
  public function syncPermissions(Request $request, Role $role)
  {
    // Prevent modifying system roles
    if ($this->isSystemRole($role)) {
      return response()->json([
        'success' => false,
        'message' => 'System roles cannot be modified.'
      ], 403);
    }
    
    $request->validate([
      'permissions' => ['required', 'array'],
      'permissions.*' => ['exists:permissions,id'],
    ]);
    
    DB::beginTransaction();
    
    try {
      $permissions = Permission::whereIn('id', $request->permissions)->get();
      $role->syncPermissions($permissions);
      
      DB::commit();
      
      $this->logUserActivityAndNotification(
        'sync',
        'roles',
        $role->id,
        'Synced permissions for role: ' . $role->name,
        'Role "' . $role->name . '" permissions updated successfully.'
      );
      
      return response()->json([
        'success' => true,
        'message' => 'Permissions updated successfully.'
      ]);
      
    } catch (\Exception $e) {
      DB::rollBack();
      \Log::error('Role permissions sync failed: ' . $e->getMessage());
      
      return response()->json([
        'success' => false,
        'message' => 'Failed to update permissions.'
      ], 500);
    }
  }
  
  /**
   * Get role users count (AJAX endpoint)
   */
  public function usersCount(Role $role)
  {
    try {
      $count = $role->users()->count();
      
      return response()->json([
        'success' => true,
        'count' => $count
      ]);
      
    } catch (\Exception $e) {
      \Log::error('Role users count failed: ' . $e->getMessage());
      
      return response()->json([
        'success' => false,
        'message' => 'Failed to get users count.'
      ], 500);
    }
  }
  
  /**
   * Group permissions by category for display
   */
  private function groupPermissions($permissions)
  {
    $grouped = [];
    
    foreach ($permissions as $permission) {
      $parts = explode(' ', $permission->name);
      $action = $parts[0] ?? 'other';
      $resource = implode(' ', array_slice($parts, 1)) ?: 'general';
      
      if (!isset($grouped[$resource])) {
        $grouped[$resource] = [];
      }
      
      $grouped[$resource][] = $permission;
    }
    
    // Sort groups and permissions within each group
    ksort($grouped);
    foreach ($grouped as &$group) {
      usort($group, function($a, $b) {
        return strcmp($a->name, $b->name);
      });
    }
    
    return $grouped;
  }
  
  /**
   * Check if a role is a system role that shouldn't be modified
   */
  private function isSystemRole(Role $role)
  {
    $systemRoles = ['super-user', 'admin', 'grower', 'customer', 'super-admin'];
    return in_array($role->name, $systemRoles);
  }
}