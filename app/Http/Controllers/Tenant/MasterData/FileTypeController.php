<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FileType;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FileTypeController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();

        $this->middleware(['auth', 'permission:view file types'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create file types'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit file types'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete file types'])->only(['destroy']);
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
        // Fetch all file types
        $query = FileType::query();

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'parent_id' => $request->input('parent_id'),
            'is_active' => $request->input('is_active'),
            'has_files' => $request->input('has_files'),
            'top_level' => $request->input('top_level'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        // Search functionality (must not be case-sensitive)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }
        
        // Filter by parent_id if provided
        if ($request->filled('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Filter by is_active if provided
        if ($request->filled('is_active')) {
            $query->where('is_active', (int)$request->is_active);
        }

        // Filter by has_files (has files or not)
        if ($request->filled('has_files')) {
            if ($request->has_files == '1') {
                $query->has('files');
            } elseif ($request->has_files == '0') {
                $query->doesntHave('files');
            }
        }

        // Filter for top level only (no parent)
        if ($request->filled('top_level') && $request->top_level) {
            $query->whereNull('parent_id');
        }

        // Sorting
        $sortField = $filters['sort_by'];
        $sortOrder = $filters['sort_order'];
        $allowedSortFields = ['name', 'created_at', 'updated_at', 'files_count'];
        
        if (in_array($sortField, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            // Default sorting
            $query->orderBy('name', 'asc');
        }

        $topLevelTypes = FileType::roots()->orderBy('name')->get();

        // Load relationships and counts
        $query->with('parent', 'children', 'files')->withCount('files');

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $fileTypes = $query->paginate($query->count())->withQueryString();
        } else {
            $fileTypes = $query->paginate($perPage)->withQueryString();
        }

        return view('file-types.index', compact('fileTypes', 'topLevelTypes', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Fetch top-level file types for parent selection
        $topLevelTypes = FileType::whereNull('parent_id')->orderBy('name')->get();
        return view('file-types.create', compact('topLevelTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $attributeTypes = implode(',', FileType::ATTRIBUTE_TYPES);
            // Add custom validation rule for attribute_type
            \Validator::extend('in:ATTRIBUTE_TYPES', function ($attribute, $value, $parameters, $validator) use ($attributeTypes) {
                return in_array($value, FileType::ATTRIBUTE_TYPES);
            }, 'The selected :attribute is invalid. Allowed values are: ' . $attributeTypes);
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:file_types,name',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:file_types,id',
                'attribute_type' => 'required|in:' . $attributeTypes,
                'is_active' => 'required|boolean',
            ]);

            // Attach the authenticated user as the creator
            $validated['created_by'] = Auth::id();

            // start transaction
            \DB::beginTransaction();
            // Create file type
            $fileType = FileType::create($validated);

            // log activity
            $this->logUserActivity('create', 'file_types', $fileType->id, 'Created file type: ' . $fileType->name);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('master-data.file-types.index')
                            ->with('success', 'Files type created successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error creating file type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while creating the file type. Please try again.']);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(FileType $fileType)
    {
        // Load related data
        $fileType->load('parent', 'children', 'files', 'userGroups', 'creator');
        $fileCount = $fileType->files()->count();
        return view('file-types.show', compact('fileType', 'fileCount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FileType $fileType)
    {
        // Fetch top-level file types for parent selection, excluding self and descendants
        $topLevelTypes = FileType::whereNull('parent_id')
            ->where('id', '!=', $fileType->id)
            ->whereNotIn('id', $fileType->descendants()->pluck('id'))
            ->orderBy('name')
            ->get();
        return view('file-types.edit', compact('fileType', 'topLevelTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, FileType $fileType)
    {
        try {
            $attributeTypes = implode(',', FileType::ATTRIBUTE_TYPES);
            // Add custom validation rule for attribute_type
            \Validator::extend('in:ATTRIBUTE_TYPES', function ($attribute, $value, $parameters, $validator) use ($attributeTypes) {
                return in_array($value, FileType::ATTRIBUTE_TYPES);
            }, 'The selected :attribute is invalid. Allowed values are: ' . $attributeTypes);
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:file_types,name,' . $fileType->id,
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:file_types,id|not_in:' . $fileType->id,
                'attribute_type' => 'required|in:' . $attributeTypes,
                'is_active' => 'required|boolean',
            ]);

            // start transaction
            \DB::beginTransaction();
            // Update file type
            $fileType->update($validated);

            // log activity
            $this->logUserActivity('update', 'file_types', $fileType->id, 'Updated file type: ' . $fileType->name);
            // commit transaction
            \DB::commit();
            
            // Redirect back to the file types index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.file-types.index', ['page' => $page])
                ->with('success', 'File type updated successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error updating file type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while updating the file type. Please try again.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, FileType $fileType)
    {
        // Check if file type has associated files (this is soft delete, so check count)
        if ($fileType->files()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete file type with associated files. Please reassign or remove associated files first.']);
        }

        // also check if it has child file types
        if ($fileType->children()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete file type with child file types. Please reassign or remove child file types first.']);
        }

        try {
            // start transaction
            \DB::beginTransaction();
            $fileTypeName = $fileType->name;
            // Delete file type
            $fileType->delete();

            // log activity
            $this->logUserActivity('delete', 'file_types', $fileType->id, 'Deleted file type: ' . $fileTypeName);
            // commit transaction
            \DB::commit();
            
            // Redirect back to the file types index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.file-types.index', ['page' => $page])
                ->with('success', 'File type deleted successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error deleting file type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while deleting the file type. Please try again.']);
        }
    }

    /**
     * Listing of deleted file types for restoration
     */
    public function trashed(Request $request)
    {
        $query = FileType::onlyTrashed();
        
        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        // Search functionality (not case-sensitive)
        $searchTerm = trim(request('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('description', 'LIKE', '%' . $searchTerm . '%');
            });
        }
        // Sorting
        $sortField = $filters['sort_by'];
        $sortOrder = $filters['sort_order'];
        $allowedSortFields = ['name', 'created_at', 'deleted_at', 'files_count'];
        if (in_array($sortField, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            // Default sorting
            $query->orderBy('name', 'asc');
        }
        $fileTypes = $query->with('parent')->paginate(15)->withQueryString();

        return view('file-types.trashed', compact('fileTypes', 'filters'));
    }

    /**
     * Restore a deleted file type
     */
    public function restore($id)
    {
        $fileType = FileType::onlyTrashed()->findOrFail($id);
        try {
            // start transaction
            \DB::beginTransaction();
            $fileType->restore();
            // log activity
            $this->logUserActivity('restore', 'file_types', $fileType->id, 'Restored file type: ' . $fileType->name);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.file-types.trashed')
                ->with('success', 'File type restored successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error restoring file type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while restoring the file type. Please try again.']);
        }
    }

    /**
     * Permanently delete a file type
     */
    public function forceDelete($id)
    {
        $fileType = FileType::onlyTrashed()->findOrFail($id);
        // Check if file type has associated files (even soft deleted ones)
        if ($fileType->files()->withTrashed()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot permanently delete file type with associated files. Please reassign or remove associated files first.']);
        }
        // also check if it has child file types
        if ($fileType->children()->withTrashed()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot permanently delete file type with child file types. Please reassign or remove child file types first.']);
        }
        try {
            // start transaction
            \DB::beginTransaction();
            $fileTypeName = $fileType->name;
            $fileType->forceDelete();
            // log activity
            $this->logUserActivity('force_delete', 'file_types', $id, 'Permanently deleted file type: ' . $fileTypeName);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.file-types.trashed')
                ->with('success', 'File type permanently deleted.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error permanently deleting file type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while permanently deleting the file type. Please try again.']);
        }
    }
}
