<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Models\Tenant\Fbo;
use App\Models\Tenant\Grower;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;



class FboController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();
        
        $this->middleware(['auth', 'permission:view fbos'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create fbos'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit fbos'])->only(['edit', 'update']);
        // $this->middleware(['auth', 'permission:assign fbos'])->only(['assignUsers', 'assignFbos', 'assignCommodities']);
        $this->middleware(['auth', 'permission:delete fbos'])->only(['destroy']);
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
        // List all FBOs
        $query = Fbo::with('growers', 'files');

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'type' => $request->input('type'),
            'is_active' => $request->input('is_active'),
            'grower_id' => $request->input('grower_id'),
            'sort_by' => $request->input('sort_by', 'code'),
            'sort_direction' => $request->input('sort_direction', 'asc'),
        ];

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query = $query->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                      ->orWhere('name', 'like', "%$search%")
                      ->orWhere('type', 'like', "%$search%")
                      ->orWhere('ggn', 'like', "%$search%")
                      ->orWhere('description', 'like', "%$search%");
            });
        }

        // Filter by type
        if ($request->filled('type')) {
            $type = $request->type;
            if ($type == 'Both') {
                $query = $query->whereIn('type', ['PUC', 'PHC']);
            } elseif ($type == 'Other') {
                $query = $query->where('type', 'OTHER');
            } else {
                $query = $query->where('type', $type);
            }
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $isActive = $request->is_active;
            if ($isActive == '1' || $isActive == '0') {
                $query = $query->where('is_active', $isActive);
            }
        }

        // Filter by grower
        if ($request->filled('grower_id')) {
            $query = $query->whereHas('growers', function ($query) use ($request) {
                $query->where('grower_id', $request->grower_id);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];
        $allowedSorts = ['code', 'name', 'type', 'ggn', 'is_active', 'created_at'];
        
        if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
            $query = $query->orderBy($sortBy, $sortDirection);
        } else {
            $query = $query->orderBy('code', 'asc');
        }

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $fbos = $query->paginate($query->count())->withQueryString();
        } else {
            $fbos = $query->paginate($perPage)->withQueryString();
        }

        $growers = Grower::orderBy('name')->get();

        // Return view with data
        return view('fbos.index', compact('fbos', 'growers', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Show form to create a new FBO
        return view('fbos.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validate input
        $request->validate([
            'code' => 'required|unique:fbos,code|max:10',
            'name' => 'nullable|max:255',
            'type' => 'required|in:PUC,PHC,OTHER',
            'ggn' => 'nullable|max:50',
            'description' => 'nullable|max:1000',
            // 'is_active' => 'required|boolean',
        ]);

        try {
            // Start transaction
            DB::beginTransaction();
            // Create FBO
            $fbo = Fbo::create([
                'code' => $request->input('code'),
                'name' => $request->input('name') ?? $request->input('code'),
                'type' => $request->input('type'),
                'ggn' => $request->input('ggn'),
                'description' => $request->input('description'),
                'is_active' => true,
                'created_by' => Auth::id(),
                'metadata' => [],
            ]);

            DB::commit();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'fbos',
                $fbo->id,
                'Created FBO: ' . $fbo->name,
                'FBO "' . $fbo->name . '" has been created successfully.'
            );

            // Redirect with success message
            return redirect()->route('master-data.fbos.index')
                ->with('success', 'FBO created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating FBO: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while creating the FBO. Please try again.'])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Fbo $fbo)
    {
        // Show details of a specific FBO
        $fbo->load('growers', 'files');
        return view('fbos.show', compact('fbo'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Fbo $fbo)
    {
        // Show form to edit an existing FBO
        return view('fbos.edit', compact('fbo'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Fbo $fbo)
    {
        // Validate input
        $request->validate([
            'code' => 'required|max:10|unique:fbos,code,' . $fbo->id,
            'name' => 'required|max:255',
            'type' => 'required|in:PUC,PHC,OTHER',
            'ggn' => 'nullable|max:50',
            'description' => 'nullable|max:1000',
            'is_active' => 'required|boolean',
        ]);

        // Get the page parameter
        $page = $request->input('page', 1);

        try {
            // Start transaction
            DB::beginTransaction();
            // Update FBO
            $fbo->update([
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'type' => $request->input('type'),
                'ggn' => $request->input('ggn'),
                'description' => $request->input('description'),
                'is_active' => $request->input('is_active'),
            ]);

            DB::commit();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'fbos',
                $fbo->id,
                'Updated FBO: ' . $fbo->name,
                'FBO "' . $fbo->name . '" has been updated successfully.'
            );

            // Redirect with success message
            return redirect()->route('master-data.fbos.index', ['page' => $page])
                             ->with('success', 'FBO updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating FBO: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while updating the FBO. Please try again.'])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Fbo $fbo)
    {
        // Get the page parameter
        $page = $request->input('page', 1);

        // Delete an FBO
        try {
            // Start transaction
            DB::beginTransaction();
            $fboName = $fbo->name;
            $fboId = $fbo->id;
            $fbo->delete();
            DB::commit();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'fbos',
                $fboId,
                'Deleted FBO: ' . $fboName,
                'FBO "' . $fboName . '" has been deleted successfully.'
            );

            // Redirect with success message
            return redirect()->route('master-data.fbos.index', ['page' => $page])
                             ->with('success', 'FBO deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting FBO: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while deleting the FBO. Please try again.']);
        }
    }

    /**
     * display a listing of soft-deleted resources.
     */
    public function trashed()
    {
        // List all soft-deleted FBOs
        $fbos = Fbo::onlyTrashed()->with('growers', 'files')->orderBy('code')->paginate(15);
        return view('fbos.trashed', compact('fbos'));
    }

    /**
     * Restore the specified resource from storage.
     */
    public function restore($id)
    {
        // Restore a soft-deleted FBO
        try {
            // Start transaction
            DB::beginTransaction();
            $fbo = Fbo::onlyTrashed()->findOrFail($id);
            $fboName = $fbo->name;
            $fboId = $fbo->id;
            $fbo->restore();
            DB::commit();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'fbos',
                $fboId,
                'Restored FBO: ' . $fboName,
                'FBO "' . $fboName . '" has been restored successfully.'
            );
            // Redirect with success message
            return redirect()->route('trashed-data.fbos.index')
                             ->with('success', 'FBO restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error restoring FBO: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while restoring the FBO. Please try again.']);
        }
    }

    /**
     * Bulk restore all resources from storage.
     */
    public function bulkRestore()
    {
        // Bulk restore all soft-deleted FBOs
        try {
            // Start transaction
            DB::beginTransaction();
            $fbos = Fbo::onlyTrashed()->get();
            foreach ($fbos as $fbo) {
                $fboName = $fbo->name;
                $fboId = $fbo->id;
                $fbo->restore();
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'restore',
                    'fbos',
                    $fboId,
                    'Restored FBO: ' . $fboName,
                    'FBO "' . $fboName . '" has been restored successfully.'
                );
            }
            DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.fbos.index')
                             ->with('success', 'All FBOs restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error bulk restoring FBOs: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while restoring the FBOs. Please try again.']);
        }
    }

    /**
     * Force delete the specified resource from storage.
     */
    public function forceDelete($id)
    {
        // Force delete an FBO
        try {
            // Start transaction
            DB::beginTransaction();
            $fbo = Fbo::withTrashed()->findOrFail($id);
            $fboName = $fbo->name;
            $fboId = $fbo->id;
            $fbo->forceDelete();
            DB::commit();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'fbos',
                $fboId,
                'Permanently Deleted FBO: ' . $fboName,
                'FBO "' . $fboName . '" has been permanently deleted.'
            );
            // Redirect with success message
            return redirect()->route('trashed-data.fbos.index')
                             ->with('success', 'FBO permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error permanently deleting FBO: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while permanently deleting the FBO. Please try again.']);
        }
    }

    /**
     * Bulk force delete all resources from storage.
     */
    public function bulkForceDelete()
    {
        // Bulk force delete all soft-deleted FBOs
        try {
            // Start transaction
            DB::beginTransaction();
            $fbos = Fbo::withTrashed()->get();
            foreach ($fbos as $fbo) {
                $fboName = $fbo->name;
                $fboId = $fbo->id;
                $fbo->forceDelete();
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'force_delete',
                    'fbos',
                    $fboId,
                    'Permanently Deleted FBO: ' . $fboName,
                    'FBO "' . $fboName . '" has been permanently deleted.'
                );
            }
            DB::commit();
            // Redirect with success message
            return redirect()->route('trashed-data.fbos.index')
                             ->with('success', 'All FBOs permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error bulk permanently deleting FBOs: ' . $e->getMessage());
            // Handle errors
            return back()->withErrors(['error' => 'An error occurred while permanently deleting the FBOs. Please try again.']);
        }
    }
}
