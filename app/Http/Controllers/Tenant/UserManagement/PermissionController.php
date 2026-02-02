<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Traits\LogsUserActivity;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
  use LogsUserActivity;
  
  public function __construct()
  {
    $this->middleware(['auth', 'permission:view permissions'])->only(['index', 'show']);
    $this->middleware(['auth', 'permission:create permissions'])->only(['create', 'store', 'bulkCreate']);
    $this->middleware(['auth', 'permission:edit permissions'])->only(['edit', 'update']);
    $this->middleware(['auth', 'permission:delete permissions'])->only(['destroy']);
  }
  
  /**
   * Display a listing of permissions
   */
  public function index(Request $request)
  {
    try {
      $search = $request->input('search');
      $resource = $request->input('resource');

      $query = Permission::query();

      if ($search) {
        $query->where('name', 'like', "%{$search}%");
              // ->orWhere('display_name', 'like', "%{$search}%")
              // ->orWhere('description', 'like', "%{$search}%");
      }

      if ($resource) {
        $query->where('name', 'like', "%{$resource}%");
      }

      $permissions = $query->withCount('roles')
        ->orderBy('name')->get();
      // $permissions = Permission::withCount('roles')
      //   ->orderBy('name')
      //   ->get();
      
      $groupedPermissions = $this->groupPermissions($permissions);
      
      return view('permissions.index', compact('permissions', 'groupedPermissions'));
      
    } catch (\Exception $e) {
      \Log::error('Failed to load permissions: ' . $e->getMessage());
      return back()
        ->with('error', 'Failed to load permissions. Please try again.');
    }
  }
  
  /**
   * Show the form for creating a new permission
   */
  public function create()
  {
    return view('permissions.create');
  }
  
  /**
   * Store a newly created permission
   */
  public function store(Request $request)
  {
    $request->validate([
      'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
      'display_name' => ['nullable', 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:500'],
    ]);
    
    try {
      $permission = Permission::create([
        'name' => $request->name,
        'display_name' => $request->display_name,
        'description' => $request->description,
        'guard_name' => config('auth.defaults.guard', 'web'),
      ]);
      
      $this->logUserActivityAndNotification(
        'create',
        'permissions',
        $permission->id,
        'Created permission: ' . $permission->name,
        'Permission "' . $permission->name . '" created successfully.'
      );
      
      return redirect()
        ->route('permissions.show', $permission)
        ->with('success', 'Permission created successfully.');
        
    } catch (\Exception $e) {
      \Log::error('Permission creation failed: ' . $e->getMessage());
      
      return back()
        ->withInput()
        ->with('error', 'Failed to create permission. Error: ' . $e->getMessage());
    }
  }
  
  /**
   * Display the specified permission
   */
  public function show(Permission $permission)
  {
    try {
      $permission->load(['roles' => function($query) {
        $query->orderBy('name');
      }]);
      
      return view('permissions.show', compact('permission'));
      
    } catch (\Exception $e) {
      return back()
        ->with('error', 'Failed to load permission details. Please try again.');
    }
  }
  
  /**
   * Show the form for editing the specified permission
   */
  public function edit(Permission $permission)
  {
    return view('permissions.edit', compact('permission'));
  }
  
  /**
   * Update the specified permission
   */
  public function update(Request $request, Permission $permission)
  {
    $request->validate([
      'name' => ['required', 'string', 'max:255', Rule::unique('permissions')->ignore($permission->id)],
      'display_name' => ['nullable', 'string', 'max:255'],
      'description' => ['nullable', 'string', 'max:500'],
    ]);
    
    try {
      $originalName = $permission->name;
      
      $permission->update([
        'name' => $request->name,
        'display_name' => $request->display_name,
        'description' => $request->description,
      ]);
      
      $this->logUserActivityAndNotification(
        'update',
        'permissions',
        $permission->id,
        "Updated permission: {$originalName} to {$permission->name}",
        'Permission "' . $permission->name . '" updated successfully.'
      );
      
      return redirect()
        ->route('permissions.show', $permission)
        ->with('success', 'Permission updated successfully.');
        
    } catch (\Exception $e) {
      \Log::error('Permission update failed: ' . $e->getMessage());
      
      return back()
        ->withInput()
        ->with('error', 'Failed to update permission. Please try again.');
    }
  }
  
  /**
   * Remove the specified permission
   */
  public function destroy(Permission $permission)
  {
    // Check if permission is assigned to roles
    if ($permission->roles()->count() > 0) {
      return redirect()
        ->route('permissions.show', $permission)
        ->with('warning', 'Cannot delete permission that is assigned to roles. Please remove the permission from all roles first.');
    }
    
    try {
      $permissionName = $permission->name;
      $permissionId = $permission->id;
      
      $permission->delete();
      
      $this->logUserActivityAndNotification(
        'delete',
        'permissions',
        $permissionId,
        "Deleted permission: {$permissionName}",
        'Permission "' . $permissionName . '" deleted successfully.'
      );
      
      return redirect()
        ->route('permissions.index')
        ->with('success', 'Permission deleted successfully.');
        
    } catch (\Exception $e) {
      \Log::error('Permission deletion failed: ' . $e->getMessage());
      
      return redirect()
        ->route('permissions.show', $permission)
        ->with('error', 'Failed to delete permission. Please try again.');
    }
  }
  
  /**
   * Show bulk create permissions form
   */
  public function showBulkCreate()
  {
    return view('permissions.bulk-create');
  }
  
  /**
   * Bulk create permissions
   */
  public function bulkCreate(Request $request)
  {
    $request->validate([
      'resource' => ['required', 'string', 'max:255'],
      'actions' => ['required', 'array', 'min:1'],
      'actions.*' => ['required', 'string', 'max:255', 'in:view,create,edit,delete,manage,export,import'],
    ]);
    
    DB::beginTransaction();
    
    try {
      $created = [];
      $skipped = [];
      
      foreach ($request->actions as $action) {
        $permissionName = trim($action) . ' ' . trim($request->resource);
        
        // Check if permission already exists
        if (Permission::where('name', $permissionName)->exists()) {
          $skipped[] = $permissionName;
          continue;
        }
        
        $permission = Permission::create([
          'name' => $permissionName,
          'display_name' => ucfirst($action) . ' ' . ucfirst(str_replace('_', ' ', $request->resource)),
          'guard_name' => config('auth.defaults.guard', 'web'),
        ]);
        
        $created[] = $permission->name;
      }
      
      DB::commit();
      
      if (count($created) > 0) {
        $this->logUserActivityAndNotification(
          'create',
          'permissions',
          0,
          'Bulk created permissions: ' . implode(', ', $created),
          'Permissions created successfully via bulk operation.'
        );
      }
      
      $message = '';
      if (count($created) > 0) {
        $message .= count($created) . ' permission(s) created successfully. ';
      }
      if (count($skipped) > 0) {
        $message .= count($skipped) . ' permission(s) already existed and were skipped.';
      }
      
      return redirect()
        ->route('permissions.index')
        ->with('success', trim($message));
        
    } catch (\Exception $e) {
      DB::rollBack();
      \Log::error('Bulk permission creation failed: ' . $e->getMessage());
      
      return back()
        ->withInput()
        ->with('error', 'Failed to create permissions. Please try again.');
    }
  }
  
  /**
   * Group permissions by resource for display
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
   * API endpoint for permission search (for autocomplete)
   */
  public function search(Request $request)
  {
    try {
      $query = $request->get('query', '');
      
      if (strlen($query) < 2) {
        return response()->json([]);
      }
      
      $permissions = Permission::where('name', 'like', "%{$query}%")
        ->orWhere('display_name', 'like', "%{$query}%")
        ->limit(10)
        ->get(['id', 'name', 'display_name'])
        ->map(function($permission) {
          return [
            'id' => $permission->id,
            'name' => $permission->name,
            'display_name' => $permission->display_name ?: $permission->name,
          ];
        });
      
      return response()->json($permissions);
      
    } catch (\Exception $e) {
      \Log::error('Permission search failed: ' . $e->getMessage());
      return response()->json([]);
    }
  }
}