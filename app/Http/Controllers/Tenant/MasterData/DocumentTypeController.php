<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DocumentType;
use App\Services\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentTypeController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();

        $this->middleware(['auth', 'permission:view document types'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create document types'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit document types'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete document types'])->only(['destroy']);
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
        // Fetch all document types
        $query = DocumentType::query();

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

        // Filter by has_files (has documents)
        if ($request->filled('has_files')) {
            if ($request->has_files == '1') {
                $query->has('documents');
            } elseif ($request->has_files == '0') {
                $query->doesntHave('documents');
            }
        }

        // Filter for top level only (no parent)
        if ($request->filled('top_level') && $request->top_level) {
            $query->whereNull('parent_id');
        }

        // Sorting
        $sortField = $filters['sort_by'];
        $sortOrder = $filters['sort_order'];
        $allowedSortFields = ['name', 'created_at', 'updated_at', 'documents_count'];
        
        if (in_array($sortField, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            // Default sorting
            $query->orderBy('name', 'asc');
        }

        $topLevelTypes = DocumentType::roots()->orderBy('name')->get();

        // Load relationships and counts
        $query->with('parent', 'children', 'documents')->withCount('documents');

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $documentTypes = $query->paginate($query->count())->withQueryString();
        } else {
            $documentTypes = $query->paginate($perPage)->withQueryString();
        }

        return view('document-types.index', compact('documentTypes', 'topLevelTypes', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Fetch top-level document types for parent selection
        $topLevelTypes = DocumentType::whereNull('parent_id')->orderBy('name')->get();
        return view('document-types.create', compact('topLevelTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $attributeTypes = implode(',', DocumentType::ATTRIBUTE_TYPES);
            // Add custom validation rule for attribute_type
            \Validator::extend('in:ATTRIBUTE_TYPES', function ($attribute, $value, $parameters, $validator) use ($attributeTypes) {
                return in_array($value, DocumentType::ATTRIBUTE_TYPES);
            }, 'The selected :attribute is invalid. Allowed values are: ' . $attributeTypes);
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:document_types,name',
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:document_types,id',
                'attribute_type' => 'required|in:' . $attributeTypes,
                'is_active' => 'required|boolean',
            ]);

            // Attach the authenticated user as the creator
            $validated['created_by'] = Auth::id();

            // start transaction
            \DB::beginTransaction();
            // Create document type
            $documentType = DocumentType::create($validated);

            // log activity
            $this->logUserActivity('create', 'document_types', $documentType->id, 'Created document type: ' . $documentType->name);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('master-data.document-types.index')
                            ->with('success', 'Document type created successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error creating document type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while creating the document type. Please try again.']);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentType $documentType)
    {
        // Load related data
        $documentType->load('parent', 'children', 'documents', 'userGroups', 'creator');
        $documentCount = $documentType->documents()->count();
        return view('document-types.show', compact('documentType', 'documentCount'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentType $documentType)
    {
        // Fetch top-level document types for parent selection, excluding self and descendants
        $topLevelTypes = DocumentType::whereNull('parent_id')
            ->where('id', '!=', $documentType->id)
            ->whereNotIn('id', $documentType->descendants()->pluck('id'))
            ->orderBy('name')
            ->get();
        return view('document-types.edit', compact('documentType', 'topLevelTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentType $documentType)
    {
        try {
            $attributeTypes = implode(',', DocumentType::ATTRIBUTE_TYPES);
            // Add custom validation rule for attribute_type
            \Validator::extend('in:ATTRIBUTE_TYPES', function ($attribute, $value, $parameters, $validator) use ($attributeTypes) {
                return in_array($value, DocumentType::ATTRIBUTE_TYPES);
            }, 'The selected :attribute is invalid. Allowed values are: ' . $attributeTypes);
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:document_types,name,' . $documentType->id,
                'description' => 'nullable|string',
                'parent_id' => 'nullable|exists:document_types,id|not_in:' . $documentType->id,
                'attribute_type' => 'required|in:' . $attributeTypes,
                'is_active' => 'required|boolean',
            ]);

            // start transaction
            \DB::beginTransaction();
            // Update document type
            $documentType->update($validated);

            // log activity
            $this->logUserActivity('update', 'document_types', $documentType->id, 'Updated document type: ' . $documentType->name);
            // commit transaction
            \DB::commit();
            
            // Redirect back to the document types index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.document-types.index', ['page' => $page])
                ->with('success', 'Document type updated successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error updating document type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while updating the document type. Please try again.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, DocumentType $documentType)
    {
        // Check if document type has associated documents (this is soft delete, so check count)
        if ($documentType->documents()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete document type with associated documents. Please reassign or remove associated documents first.']);
        }

        // also check if it has child document types
        if ($documentType->children()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot delete document type with child document types. Please reassign or remove child document types first.']);
        }

        try {
            // start transaction
            \DB::beginTransaction();
            $documentTypeName = $documentType->name;
            // Delete document type
            $documentType->delete();

            // log activity
            $this->logUserActivity('delete', 'document_types', $documentType->id, 'Deleted document type: ' . $documentTypeName);
            // commit transaction
            \DB::commit();
            
            // Redirect back to the document types index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.document-types.index', ['page' => $page])
                ->with('success', 'Document type deleted successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error deleting document type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while deleting the document type. Please try again.']);
        }
    }

    /**
     * Listing of deleted document types for restoration
     */
    public function trashed(Request $request)
    {
        $query = DocumentType::onlyTrashed();
        
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
        $allowedSortFields = ['name', 'created_at', 'deleted_at', 'documents_count'];
        if (in_array($sortField, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            // Default sorting
            $query->orderBy('name', 'asc');
        }
        $documentTypes = $query->with('parent')->paginate(15)->withQueryString();

        return view('document-types.trashed', compact('documentTypes', 'filters'));
    }

    /**
     * Restore a deleted document type
     */
    public function restore($id)
    {
        $documentType = DocumentType::onlyTrashed()->findOrFail($id);
        try {
            // start transaction
            \DB::beginTransaction();
            $documentType->restore();
            // log activity
            $this->logUserActivity('restore', 'document_types', $documentType->id, 'Restored document type: ' . $documentType->name);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.document-types.trashed')
                ->with('success', 'Document type restored successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error restoring document type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while restoring the document type. Please try again.']);
        }
    }

    /**
     * Permanently delete a document type
     */
    public function forceDelete($id)
    {
        $documentType = DocumentType::onlyTrashed()->findOrFail($id);
        // Check if document type has associated documents (even soft deleted ones)
        if ($documentType->documents()->withTrashed()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot permanently delete document type with associated documents. Please reassign or remove associated documents first.']);
        }
        // also check if it has child document types
        if ($documentType->children()->withTrashed()->count() > 0) {
            return redirect()->back()
                ->withErrors(['error' => 'Cannot permanently delete document type with child document types. Please reassign or remove child document types first.']);
        }
        try {
            // start transaction
            \DB::beginTransaction();
            $documentTypeName = $documentType->name;
            $documentType->forceDelete();
            // log activity
            $this->logUserActivity('force_delete', 'document_types', $id, 'Permanently deleted document type: ' . $documentTypeName);
            // commit transaction
            \DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.document-types.trashed')
                ->with('success', 'Document type permanently deleted.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            // Log the error for debugging
            \Log::error('Error permanently deleting document type: ' . $e->getMessage());
            // Redirect back with error message
            return redirect()->back()
                ->withErrors(['error' => 'An error occurred while permanently deleting the document type. Please try again.']);
        }
    }
}
