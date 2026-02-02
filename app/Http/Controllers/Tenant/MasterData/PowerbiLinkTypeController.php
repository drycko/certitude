<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PowerbiLinkType;
use App\Models\Tenant\UserGroup;
use App\Services\NotificationService;
use App\Services\UserAccessService;
use Illuminate\Http\Request;

class PowerbiLinkTypeController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();

        $this->middleware(['auth', 'permission:view powerbi types'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create powerbi types'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit powerbi types'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete powerbi types'])->only(['destroy']);
        // Additional permissions for trashed items
        $this->middleware(['auth', 'permission:restore trashed items'])->only(['restore', 'bulkRestore']);
        $this->middleware(['auth', 'permission:view trashed items'])->only(['trashed']);
        $this->middleware(['auth', 'permission:force delete trashed items'])->only(['forceDelete', 'bulkForceDelete']);

        $this->notificationService = $notificationService;
        $this->userAccessService = $userAccessService;
        $this->allowedAttributeTypes = PowerbiLinkType::ATTRIBUTE_TYPES;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Fetch all powerbi link types
        $query = PowerbiLinkType::with('links', 'userGroups', 'creator')->withCount('links', 'userGroups');

        // Search functionality (not case-sensitive)
        $searchTerm = trim(request('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        // has links
        $hasLinks = request('has_links', '');
        if ($hasLinks === '1') {
            $query->has('links');
        } elseif ($hasLinks === '0') {
            $query->doesntHave('links');
        }

        // filter by attribute type
        $attributeType = request('attribute_type', '');
        if (in_array($attributeType, $this->allowedAttributeTypes)) {
            $query->where('attribute_type', $attributeType);
        }

        // by parent type
        $parentType = request('parent_id', '');
        if (!empty($parentType)) {
            $query->where('parent_id', $parentType);
        }

        // Build filters array
        $filters = [
            'search' => $searchTerm,
            'has_links' => $hasLinks,
            'attribute_type' => $attributeType,
            'parent_id' => $parentType,
            'is_active' => request('is_active', ''),
            'per_page' => request('per_page', 15), // filter to set the number of items per page
        ];

        // by status
        if ($filters['is_active'] === '1') {
            $query->where('is_active', true);
        } elseif ($filters['is_active'] === '0') {
            $query->where('is_active', false);
        }

        // if all
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = PowerbiLinkType::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'name'; // default sort by name
        $sortOrder = $filters['sort_direction'] ?? 'asc'; // default ascending
        $allowedSortFields = ['name', 'attribute_type', 'is_active', 'links_count', 'user_groups_count', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'name';
            $sortOrder = 'asc';
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        $powerbiTypes = $query->paginate($filters['per_page']);

        // filter options
        $attributeTypes = PowerbiLinkType::select('attribute_type')
            ->distinct()
            ->orderBy('attribute_type')
            ->pluck('attribute_type')
            ->toArray();

         $topLevelTypes = PowerbiLinkType::roots()->orderBy('name')->get();

        return view('powerbi-link-types.index', compact('powerbiTypes', 'topLevelTypes', 'attributeTypes', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $topLevelTypes = PowerbiLinkType::select('attribute_type')
            ->distinct()
            ->orderBy('attribute_type')
            ->pluck('attribute_type')
            ->toArray();

        $attributeTypes = $this->allowedAttributeTypes;
        $topLevelTypes = PowerbiLinkType::roots()->orderBy('name')->get();
        // Show create form
        return view('powerbi-link-types.create', compact('topLevelTypes', 'attributeTypes', 'filters'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:powerbi_link_types,name',
                'description' => 'nullable|string',
                'attribute_type' => 'required|string|in:' . implode(',', $this->allowedAttributeTypes),
                'is_active' => 'required|boolean',
            ]);
            // add icon to metadata if provided
            if ($request->has('icon') && !empty($request->input('icon'))) {
                $validated['metadata'] = ['icon' => $request->input('icon')];
            }

            // if parent_id is provided, validate it exists
            if ($request->has('parent_id') && !empty($request->input('parent_id'))) {
                $parent = PowerbiLinkType::find($request->input('parent_id'));
                if (!$parent) {
                    return redirect()->back()
                        ->withErrors(['parent_id' => 'Selected parent powerbi type does not exist.'])->withInput();
                }
                $validated['parent_id'] = $parent->id;
            }

            // Create new powerbi link type
            $powerbiLinkType = new PowerbiLinkType();
            $powerbiLinkType->name = $validated['name'];
            $powerbiLinkType->description = $validated['description'] ?? null;
            $powerbiLinkType->attribute_type = $validated['attribute_type'];
            $powerbiLinkType->is_active = $validated['is_active'];
            $powerbiLinkType->parent_id = $validated['parent_id'] ?? null;
            $powerbiLinkType->metadata = $validated['metadata'] ?? null;
            $powerbiLinkType->created_by = auth()->id();
            $powerbiLinkType->save();

            // Log activity
            $this->logUserActivity(
                'create',
                'powerbi_link_types',
                $powerbiLinkType->id,
                'Created powerbi link type: ' . $powerbiLinkType->name
            );

            // Success notification
            // $this->notificationService->success('powerbi link type created successfully.');

            return redirect()->route('master-data.powerbi-link-types.index')
                ->with('success', 'powerbi link type created successfully.');
        } catch (\Exception $e) {
            \Log::error('Error creating powerbi link type', ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while creating the powerbi type. Please try again'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PowerbiLinkType $powerbiLinkType)
    {
        // Load related data
        $powerbiLinkType->load('links', 'userGroups', 'creator')->withCount('links', 'userGroups');
        $groups = UserGroup::active()->ordered()->get();
        $powerbiLinkType->icon = $powerbiLinkType->getMetadataValue('icon', null);
        return view('powerbi-link-types.show', compact('powerbiLinkType', 'groups'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PowerbiLinkType $powerbiLinkType)
    {
        // Load related data
        $powerbiLinkType->load('links', 'userGroups', 'creator')->withCount('links', 'userGroups');
        $attributeTypes = $this->allowedAttributeTypes;
        $topLevelTypes = PowerbiLinkType::roots()->where('id', '!=', $powerbiLinkType->id)->orderBy('name')->get();
        $powerbiLinkType->icon = $powerbiLinkType->getMetadataValue('icon', null);
        return view('powerbi-link-types.edit', compact('powerbiLinkType', 'attributeTypes', 'topLevelTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PowerbiLinkType $powerbiLinkType)
    {
        try {
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:powerbi_link_types,name,' . $powerbiLinkType->id,
                'description' => 'nullable|string',
                'attribute_type' => 'required|string|in:' . implode(',', $this->allowedAttributeTypes),
                'is_active' => 'required|boolean',
            ]);
            // add icon to metadata if provided
            if ($request->has('icon') && !empty($request->input('icon'))) {
                $validated['metadata'] = ['icon' => $request->input('icon')];
            } else {
                $validated['metadata'] = null;
            }

            // if parent_id is provided, validate it exists and is not self
            if ($request->has('parent_id') && !empty($request->input('parent_id'))) {
                if ($request->input('parent_id') == $powerbiLinkType->id) {
                    return redirect()->back()
                        ->withErrors(['parent_id' => 'A powerbi type cannot be its own parent.'])->withInput();
                }
                $parent = PowerbiLinkType::find($request->input('parent_id'));
                if (!$parent) {
                    return redirect()->back()
                        ->withErrors(['parent_id' => 'Selected parent powerbi type does not exist.'])->withInput();
                }
                $validated['parent_id'] = $parent->id;
            } else {
                $validated['parent_id'] = null;
            }

            // Get the page parameter
            $page = $request->input('page', 1);

            // Update powerbi link type
            $powerbiLinkType->name = $validated['name'];
            $powerbiLinkType->description = $validated['description'] ?? null;
            $powerbiLinkType->attribute_type = $validated['attribute_type'];
            $powerbiLinkType->is_active = $validated['is_active'];
            $powerbiLinkType->parent_id = $validated['parent_id'] ?? null;
            $powerbiLinkType->metadata = $validated['metadata'] ?? null;
            $powerbiLinkType->save();

            // Log activity
            $this->logUserActivity(
                'update',
                'powerbi_link_types',
                $powerbiLinkType->id,
                'Updated powerbi link type: ' . $powerbiLinkType->name
            );

            return redirect()->route('master-data.powerbi-link-types.index', ['page' => $page])
                ->with('success', 'powerbi link type updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Error updating powerbi link type', ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while updating the powerbi type. Please try again'])->withInput();
        }
    }

    /**
     * Update user groups associated with the powerbi link type.
     */
    public function updateGroups(Request $request, $id)
    {
        // $user = Auth::user();
        $powerbiLinkType = PowerbiLinkType::findOrFail($id);

        $validated = $request->validate([
            'groups' => 'required|array',
            'groups.*' => 'exists:user_groups,id',
        ]);

        \DB::beginTransaction();
        try {
            // Sync user groups
            $powerbiLinkType->userGroups()->sync($validated['groups']);

            // Log activity
            $this->logUserActivityAndNotification(
                'update',
                'powerbi_link_groups',
                $powerbiLinkType->id,
                'Updated user groups for powerbi link type: ' . $powerbiLinkType->name,
                'User groups for powerbi link type "' . $powerbiLinkType->name . '" updated successfully.'
            );

            \DB::commit();
            return redirect()->route('master-data.powerbi-link-types.show', $powerbiLinkType)
                ->with('success', 'powerbi link type user groups updated successfully.');
        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('Error updating user groups for powerbi link type: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'An error occurred while updating user groups: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, PowerbiLinkType $powerbiLinkType)
    {
        // Prevent deletion if linked to powerbi links or user groups
        if ($powerbiLinkType->links()->exists() || $powerbiLinkType->userGroups()->exists()) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete powerbi type linked to existing links or user groups.']);
        }

        // Get the page parameter
        $page = $request->input('page', 1);

        try {

        // Delete the powerbi link type
        $powerbiLinkType->delete();

        // Log activity
        $this->logUserActivity(
            'delete',
            'powerbi_link_types',
            $powerbiLinkType->id,
            'Deleted powerbi link type: ' . $powerbiLinkType->name
        );

        return redirect()->route('master-data.powerbi-link-types.index', ['page' => $page])
            ->with('success', 'powerbi link type deleted successfully.');

        } catch (\Exception $e) {
            \Log::error('Error deleting powerbi link type', ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while deleting the powerbi type. Please try again']);
        }
            
    }

    /**
     * Listing of soft-deleted resources.
     */
    public function trashed()
    {
        // Fetch all powerbi link types
        $query = PowerbiLinkType::onlyTrashed()->with('links', 'userGroups', 'creator')->withCount('links', 'userGroups');

        // Search functionality (not case-sensitive)
        $searchTerm = trim(request('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        // has links
        $hasLinks = request('has_links', '');
        if ($hasLinks === '1') {
            $query->has('links');
        } elseif ($hasLinks === '0') {
            $query->doesntHave('links');
        }

        // filter by attribute type
        $attributeType = request('attribute_type', '');
        if (in_array($attributeType, $this->allowedAttributeTypes)) {
            $query->where('attribute_type', $attributeType);
        }

        // by parent type
        $parentType = request('parent_id', '');
        if (!empty($parentType)) {
            $query->where('parent_id', $parentType);
        }

        // Sorting
        $sortBy = request('sort_by', 'name'); // default sort by name
        $sortOrder = request('sort_direction', 'asc'); // default ascending
        $allowedSortFields = ['name', 'attribute_type', 'is_active', 'links_count', 'user_groups_count', 'created_at'];
        if (!in_array($sortBy, $allowedSortFields)) {
            $sortBy = 'name';
            $sortOrder = 'asc';
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortOrder);
        $trashedTypes = $query->paginate(15);

        return view('powerbi-link-types.trashed', compact('trashedTypes'));
    }

    /**
     * Restore a soft-deleted resource.
     */
    public function restore($id)
    {
        try {
            $powerbiLinkType = PowerbiLinkType::onlyTrashed()->findOrFail($id);
            $powerbiLinkType->restore();

            // Log activity
            $this->logUserActivity(
                'restore',
                'powerbi_link_types',
                $powerbiLinkType->id,
                'Restored powerbi link type: ' . $powerbiLinkType->name
            );

            return redirect()->route('trashed-data.powerbi-link-types.index')
                ->with('success', 'Powerbi link type restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Error restoring powerbi link type', ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while restoring the powerbi link type. Please try again']);
        }
    }

    /**
     * Permanently delete a soft-deleted resource.
     */
    public function forceDelete($id)
    {
        try {
            $powerbiLinkType = PowerbiLinkType::onlyTrashed()->findOrFail($id);
            // Prevent deletion if linked to powerbi links or user groups
            if ($powerbiLinkType->links()->exists() || $powerbiLinkType->userGroups()->exists()) {
                return redirect()->back()
                    ->withErrors(['error' => 'Cannot permanently delete powerbi type linked to existing links or user groups.']);
            }
            $powerbiLinkType->forceDelete();
            // Log activity
            $this->logUserActivity(
                'force_delete',
                'powerbi_link_types',
                $powerbiLinkType->id,
                'Permanently deleted powerbi link type: ' . $powerbiLinkType->name
            );
            return redirect()->route('trashed-data.powerbi-link-types.index')
                ->with('success', 'powerbi link type permanently deleted.');
        } catch (\Exception $e) {
            \Log::error('Error permanently deleting powerbi link type', ['error' => $e->getMessage(), 'stack' => $e->getTraceAsString()]);
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while permanently deleting the powerbi type. Please try again']);
        }
    
    }

}
