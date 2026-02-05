<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\UserGroup;
use App\Models\Tenant\User;
use App\Models\Tenant\PowerbiLinkType;
use App\Models\Tenant\FileType;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();
        
        $this->middleware(['auth', 'permission:view user groups'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create user groups'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit user groups'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:assign user groups'])->only(['assignUsers', 'assignFbos', 'assignCommodities']);
        $this->middleware(['auth', 'permission:delete user groups'])->only(['destroy']);
        // Additional permissions for trashed items
        $this->middleware(['auth', 'permission:restore trashed items'])->only(['restore', 'bulkRestore']);
        $this->middleware(['auth', 'permission:view trashed items'])->only(['trashed']);
        $this->middleware(['auth', 'permission:force delete trashed items'])->only(['forceDelete', 'bulkForceDelete']);

        $this->notificationService = $notificationService;
        $this->userAccessService = $userAccessService;
    }
    
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Fetch all user groups, with filtering and pagination as needed
        $query = UserGroup::with('permissions', 'users', 'fileTypes')->withCount('users')->withCount('fileTypes');

        // search filter by name or display_name or description
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // filter by is_active
        if (!is_null(request('is_active'))) {
            $isActive = filter_var(request('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_null($isActive)) {
                $query->where('is_active', $isActive);
            }
        }
        // Build filters array
        $filters = [
            'search' => $request->input('search', ''),
            'is_active' => $request->input('is_active', ''),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_order' => $request->input('sort_order', 'asc'),
            'per_page' => $request->input('per_page', 15), // filter to set the number of items per page
        ];

        // if all
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = UserGroup::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }
        
        // sorting
        $sortField = $filters['sort_by'];
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $allowedSortFields = ['name', 'display_name', 'is_active', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        }

        $groups = $query->paginate($filters['per_page'])->withQueryString();

        return view('groups.index', compact('groups', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Show form to create a new user group
        $fileTypes = FileType::where('is_active', true)->orderBy('name')->get();
        $powerbiLinkTypes = PowerbiLinkType::where('is_active', true)->orderBy('name')->get();
        return view('groups.create', compact('fileTypes', 'powerbiLinkTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate and store the new user group
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:user_groups,name',
                'display_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                'file_types' => 'nullable|array',
                'file_types.*' => 'exists:file_types,id',
                'powerbi_link_types' => 'nullable|array',
                'powerbi_link_types.*' => 'exists:powerbi_link_types,id',
            ]);

            // start transaction
            \DB::beginTransaction();

            // Create the user group
            $group = UserGroup::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'created_by' => auth()->id(),
            ]);
            // Attach file types if provided
            if (!empty($validated['file_types'])) {
                $group->fileTypes()->sync($validated['file_types']);
            }
            // Attach powerbi link types if provided
            if (!empty($validated['powerbi_link_types'])) {
                $group->powerbiLinkTypes()->sync($validated['powerbi_link_types']);
            }

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'user_groups',
                $group->id,
                'Created user group: ' . $group->name,
                'User group "' . $group->name . '" created successfully.'
            );
            \DB::commit();
            return redirect()->route('master-data.groups.index')->with('success', 'User group created successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@store: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to create user group. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(UserGroup $group)
    {
        // Show details of a specific user group
        $group->load('permissions', 'users', 'fileTypes', 'powerbiLinkTypes');
        $activityLogs = $this->getActivityLogs('user_groups', $group->id);
        $allUsers = User::where('is_active', true)->orderBy('name')->get();
        $allFileTypes = FileType::where('is_active', true)->orderBy('name')->get();
        $allPowerbiLinkTypes = PowerbiLinkType::where('is_active', true)->orderBy('name')->get();

        return view('groups.show', compact('group', 'activityLogs', 'allUsers', 'allFileTypes', 'allPowerbiLinkTypes'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(UserGroup $group)
    {
        // Show form to edit a user group
        $fileTypes = FileType::where('is_active', true)->orderBy('name')->get();
        $powerbiLinkTypes = PowerbiLinkType::where('is_active', true)->orderBy('name')->get();
        return view('groups.edit', compact('group', 'fileTypes', 'powerbiLinkTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserGroup $group)
    {
        // Validate and update the user group
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:user_groups,name,' . $group->id,
                'display_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'required|boolean',
                // 'file_types' => 'nullable|array',
                // 'file_types.*' => 'exists:file_types,id',
                // 'powerbi_link_types' => 'nullable|array',
                // 'powerbi_link_types.*' => 'exists:powerbi_link_types,id',
            ]);

            // Get the page parameter
            $page = $request->input('page', 1);

            // start transaction
            \DB::beginTransaction();
            $group->update([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'updated_by' => auth()->id(),
            ]);
            // // Sync file types
            // if (isset($validated['file_types'])) {
            //     $group->fileTypes()->sync($validated['file_types']);
            // } else {
            //     $group->fileTypes()->detach();
            // }
            // // Sync powerbi link types
            // if (isset($validated['powerbi_link_types'])) {
            //     $group->powerbiLinkTypes()->sync($validated['powerbi_link_types']);
            // } else {
            //     $group->powerbiLinkTypes()->detach();
            // }
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'user_groups',
                $group->id,
                'Updated user group: ' . $group->name,
                'User group "' . $group->name . '" updated successfully.'
            );
            
            \DB::commit();
            return redirect()->route('master-data.groups.index', ['page' => $page])->with('success', 'User group updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@update: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update user group. Error: ' . $e->getMessage()])->withInput();
        }
    }

        /**
     * Listing of trashed commodities.
     */
    public function trashed()
    {
        $query = UserGroup::onlyTrashed()->withCount('users');

        // search filter by name or display_name or description
        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('display_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // sorting
        $sortBy = request('sort_by', 'deleted_at');
        $sortOrder = request('sort_order', 'desc');
        $allowedSortFields = ['name', 'display_name', 'deleted_at', 'created_at', 'updated_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $groups = $query->paginate(15)->withQueryString();

        return view('groups.trashed', compact('groups'));
    }

    /**
     * Restore a trashed group.
     */
    public function restore($id)
    {
        try {
            $group = UserGroup::onlyTrashed()->findOrFail($id);
            // start transaction
            \DB::beginTransaction();
            $group->restore();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'user_groups',
                $group->id,
                'Restored user group: ' . $group->name,
                'User group "' . $group->name . '" restored successfully.'
            );
            \DB::commit();
            return redirect()->route('trashed-data.groups.index')->with('success', 'User group restored successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@restore: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to restore user group. Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Permanently delete a trashed group.
     */
    public function forceDelete($id)
    {
        try {
            $group = UserGroup::onlyTrashed()->findOrFail($id);
            // start transaction
            \DB::beginTransaction();
            $groupName = $group->name;
            $group->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'user_groups',
                $group->id,
                'Permanently deleted user group: ' . $groupName,
                'User group "' . $groupName . '" permanently deleted successfully.'
            );
            \DB::commit();
            return redirect()->route('trashed-data.groups.index')->with('success', 'User group permanently deleted successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@forceDelete: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to permanently delete user group. Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, UserGroup $group)
    {
        // Get the page parameter
        $page = $request->input('page', 1);

        // Delete the user group
        try {
            // start transaction
            \DB::beginTransaction();
            $groupName = $group->name;
            $group->delete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'user_groups',
                $group->id,
                'Deleted user group: ' . $groupName,
                'User group "' . $groupName . '" deleted successfully.'
            );
            \DB::commit();
            return redirect()->route('trashed-data.groups.index', ['page' => $page])->with('success', 'User group deleted successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@destroy: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to delete user group. Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Assign users to the group
     */
    public function assignUsers(Request $request, UserGroup $group)
    {
        try {
            $validated = $request->validate([
                'users' => 'nullable|array',
                'users.*' => 'exists:users,id',
            ]);
            // start transaction
            \DB::beginTransaction();
            $group->users()->sync($validated['users'] ?? []);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'user_groups',
                $group->id,
                'Updated users for group: ' . $group->name,
                'User group "' . $group->name . '" users updated successfully.'
            );
            \DB::commit();
            return redirect()->route('master-data.groups.show', $group->id)->with('success', 'Group users updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@assignUsers: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update group users. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Assign file types to the group
     */
    public function assignFileTypes(Request $request, UserGroup $group)
    {
        try {
            $validated = $request->validate([
                'file_types' => 'nullable|array',
                'file_types.*' => 'exists:file_types,id',
            ]);
            // start transaction
            \DB::beginTransaction();
            $group->fileTypes()->sync($validated['file_types'] ?? []);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'user_groups',
                $group->id,
                'Updated file types for group: ' . $group->name,
                'User group "' . $group->name . '" file types updated successfully.'
            );
            \DB::commit();
            return redirect()->route('master-data.groups.show', $group->id)->with('success', 'Group file types updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@assignFileTypes: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update group file types. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Assign PowerBiLink types to the group
     */
    public function assignPowerbiLinkTypes(Request $request, UserGroup $group)
    {
        try {
            $validated = $request->validate([
                'powerbi_link_types' => 'nullable|array',
                'powerbi_link_types.*' => 'exists:powerbi_link_types,id',
            ]);
            // start transaction
            \DB::beginTransaction();
            if (isset($validated['powerbi_link_types'])) {
                \Log::info('Assigning powerbi link types: ' . implode(',', $validated['powerbi_link_types']));
            } else {
                \Log::info('No powerbi link types provided, detaching all.');
            }
            $group->powerbiLinkTypes()->sync($validated['powerbi_link_types'] ?? []);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'user_groups',
                $group->id,
                'Updated powerbi link types for group: ' . $group->name,
                'User group "' . $group->name . '" powerbi link types updated successfully.'
            );
            \DB::commit();
            return redirect()->route('master-data.groups.show', $group->id)->with('success', 'Group powerbi link types updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error("Error in UserGroupController@assignPowerbiLinkTypes: " . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to update group powerbi link types. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Generate a unique group slug via AJAX
     */
    public function generateGroupSlug(Request $request)
    {
        \Log::info('generateGroupSlug called');
        try {
            $this->validate($request, [
                'slug' => 'required|string|max:255'
            ]);

            $slug = $request->input('slug');
            $exists = UserGroup::where('name', $slug)->exists();

            if ($exists) {
                $count = UserGroup::where('name', 'like', "{$slug}-%")->count();
                $slug .= '-' . ($count + 1);
            }
            // \Log::info("Generated group slug: {$slug}");                

            return response()->json(['slug' => $slug]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error("Validation error in generateGroupSlug: " . $e->getMessage());
            // Validation errors
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            // Other errors
            \Log::error("Error in generateGroupSlug: " . $e->getMessage());
            return response()->json(['error' => 'Invalid input'], 400);
        }
    }
}
