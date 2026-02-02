<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use App\Models\Tenant\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class UserGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super']);
        $this->middleware('permission:view user groups')->only(['index', 'show']);
        $this->middleware('permission:create user groups')->only(['create', 'store']);
        $this->middleware('permission:edit user groups')->only(['edit', 'update']);
        $this->middleware('permission:delete user groups')->only(['destroy']);
        $this->middleware('permission:assign user groups')->only(['assignUsers', 'removeUser']);
        $this->middleware('permission:manage group permissions')->only(['managePermissions', 'updatePermissions']);
    }

    /**
     * Display a listing of user groups
     */
    public function index(Request $request)
    {
        $query = UserGroup::with(['users', 'permissions']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->filled('status')) {
            $query->where('is_active', $request->get('status') === 'active');
        }

        // Filter by legacy groups
        if ($request->filled('legacy')) {
            if ($request->get('legacy') === 'yes') {
                $query->whereNotNull('legacy_group_id');
            } else {
                $query->whereNull('legacy_group_id');
            }
        }

        $userGroups = $query->ordered()->paginate(15);

        return view('admin.user-groups.index', compact('userGroups'));
    }

    /**
     * Show the form for creating a new user group
     */
    public function create()
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($permission) {
            return explode(' ', $permission->name)[0]; // Group by first word
        });

        return view('admin.user-groups.create', compact('permissions'));
    }

    /**
     * Store a newly created user group
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_groups,name',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
            'metadata' => 'nullable|json',
        ]);

        DB::transaction(function () use ($validated) {
            $userGroup = UserGroup::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'metadata' => $validated['metadata'] ? json_decode($validated['metadata'], true) : null,
            ]);

            // Assign permissions
            if (!empty($validated['permissions'])) {
                $userGroup->syncPermissions($validated['permissions']);
            }
        });

        return redirect()
            ->route('admin.user-groups.index')
            ->with('success', 'User group created successfully.');
    }

    /**
     * Display the specified user group
     */
    public function show(UserGroup $userGroup)
    {
        $userGroup->load(['users.roles', 'permissions']);
        
        $userGroupUsers = $userGroup->users()
            ->withPivot(['is_primary_group', 'assigned_at', 'expires_at'])
            ->paginate(20);

        return view('admin.user-groups.show', compact('userGroup', 'userGroupUsers'));
    }

    /**
     * Show the form for editing the specified user group
     */
    public function edit(UserGroup $userGroup)
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($permission) {
            return explode(' ', $permission->name)[0];
        });

        $userGroupPermissions = $userGroup->getPermissionNames();

        return view('admin.user-groups.edit', compact('userGroup', 'permissions', 'userGroupPermissions'));
    }

    /**
     * Update the specified user group
     */
    public function update(Request $request, UserGroup $userGroup)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:user_groups,name,' . $userGroup->id,
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name',
            'metadata' => 'nullable|json',
        ]);

        DB::transaction(function () use ($userGroup, $validated) {
            $userGroup->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? '',
                'is_active' => $validated['is_active'] ?? true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'metadata' => $validated['metadata'] ? json_decode($validated['metadata'], true) : null,
            ]);

            // Sync permissions
            if (isset($validated['permissions'])) {
                $userGroup->syncPermissions($validated['permissions']);
            }
        });

        return redirect()
            ->route('admin.user-groups.show', $userGroup)
            ->with('success', 'User group updated successfully.');
    }

    /**
     * Remove the specified user group
     */
    public function destroy(UserGroup $userGroup)
    {
        if ($userGroup->users()->exists()) {
            return back()->with('error', 'Cannot delete user group that has assigned users.');
        }

        $userGroup->delete();

        return redirect()
            ->route('admin.user-groups.index')
            ->with('success', 'User group deleted successfully.');
    }

    /**
     * Show form to assign users to group
     */
    public function assignUsers(UserGroup $userGroup)
    {
        $availableUsers = User::whereDoesntHave('userGroups', function ($query) use ($userGroup) {
            $query->where('user_group_id', $userGroup->id);
        })->with('roles')->paginate(20);

        return view('admin.user-groups.assign-users', compact('userGroup', 'availableUsers'));
    }

    /**
     * Assign users to group
     */
    public function storeUserAssignment(Request $request, UserGroup $userGroup)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'is_primary_group' => 'boolean',
            'expires_at' => 'nullable|date|after:today',
        ]);

        DB::transaction(function () use ($userGroup, $validated) {
            foreach ($validated['user_ids'] as $userId) {
                $user = User::find($userId);
                
                $user->assignToGroup(
                    $userGroup,
                    $validated['is_primary_group'] ?? false,
                    null,
                    $validated['expires_at'] ? new \DateTime($validated['expires_at']) : null
                );
            }
        });

        return redirect()
            ->route('admin.user-groups.show', $userGroup)
            ->with('success', 'Users assigned to group successfully.');
    }

    /**
     * Remove user from group
     */
    public function removeUser(UserGroup $userGroup, User $user)
    {
        $user->removeFromGroup($userGroup);

        return back()->with('success', 'User removed from group successfully.');
    }

    /**
     * Manage group permissions
     */
    public function managePermissions(UserGroup $userGroup)
    {
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($permission) {
            return explode(' ', $permission->name)[0];
        });

        $groupPermissions = $userGroup->permissions()
            ->withPivot(['is_granted'])
            ->get()
            ->keyBy('name');

        return view('admin.user-groups.permissions', compact('userGroup', 'permissions', 'groupPermissions'));
    }

    /**
     * Update group permissions
     */
    public function updatePermissions(Request $request, UserGroup $userGroup)
    {
        $validated = $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'in:grant,deny,remove',
        ]);

        DB::transaction(function () use ($userGroup, $validated) {
            foreach ($validated['permissions'] as $permissionName => $action) {
                switch ($action) {
                    case 'grant':
                        $userGroup->givePermissionTo($permissionName);
                        break;
                    case 'deny':
                        $userGroup->denyPermissionTo($permissionName);
                        break;
                    case 'remove':
                        $userGroup->revokePermissionTo($permissionName);
                        break;
                }
            }
        });

        return back()->with('success', 'Group permissions updated successfully.');
    }

    /**
     * Get user groups for AJAX requests
     */
    public function ajax(Request $request)
    {
        $query = UserGroup::active();

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $userGroups = $query->ordered()->get(['id', 'name', 'display_name']);

        return response()->json($userGroups);
    }
}