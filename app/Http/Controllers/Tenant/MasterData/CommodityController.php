<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Commodity;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CommodityController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();

        $this->middleware(['auth', 'permission:view commodities'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create commodities'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit commodities'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete commodities'])->only(['destroy']);
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
        // List all commodities
        $commodities = Commodity::query();

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'status' => $request->input('status'),
            'sort_by' => $request->input('sort_by', 'sort_order'),
            'sort_direction' => $request->input('sort_direction', 'asc'),
        ];

        // Filter by search query
        if ($request->filled('search')) {
            $search = $request->search;
            $commodities = $commodities->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // Filter by status
        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'])) {
            $isActive = $request->status === 'active' ? 1 : 0;
            $commodities = $commodities->where('is_active', $isActive);
        }

        // Sorting
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];
        $allowedSorts = ['code', 'name', 'type', 'is_active', 'created_at', 'sort_order'];
        
        if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
            $commodities = $commodities->orderBy($sortBy, $sortDirection);
        } else {
            // Default sort
            $commodities = $commodities->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
        }

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $commodities = $commodities->paginate($commodities->count())->withQueryString();
        } else {
            $commodities = $commodities->paginate($perPage)->withQueryString();
        }

        // Filter options
        $filterOptions = [
            'status' => ['active', 'inactive'],
            'sort_by' => ['code', 'name', 'type', 'is_active', 'created_at'],
            'sort_direction' => ['asc', 'desc'],
        ];

        return view('commodities.index', compact('commodities', 'filterOptions', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            // Show form to create a new commodity
            return view('commodities.create');
        } catch (\Exception $e) {
            // Log the error but don't interrupt the user flow
            \Log::error('Failed to log user activity: ' . $e->getMessage());
            return view('commodities.create')->withErrors('An error occurred while preparing the form. Please try again.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        try {
            // is_active checkbox handling
            $request->merge(['is_active' => $request->has('is_active') ? 1 : 0]);
            // Validate and store new commodity
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:commodities,code',
                'description' => 'nullable|string',
                'color_code' => 'nullable|string|max:7',
                'icon_code' => 'nullable|string|max:50',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            $commodity = Commodity::create($validatedData);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'commodities',
                $commodity->id,
                'Created Commodity: ' . $commodity->name,
                'Commodity "' . $commodity->name . '" has been created successfully.'
            );

            return redirect()->route('master-data.commodities.index')->with('success', 'Commodity created successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to create commodity: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while creating the commodity: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Commodity $commodity)
    {
        // Show details of a specific commodity
        return view('commodities.show', compact('commodity'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Commodity $commodity)
    {
        // Show form to edit an existing commodity
        return view('commodities.edit', compact('commodity'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Commodity $commodity)
    {
        // Validate and update the commodity
        try {
            // start transaction
            DB::beginTransaction();

            // is_active checkbox handling
            $request->merge(['is_active' => $request->has('is_active') ? 1 : 0]);
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:commodities,code,' . $commodity->id,
                'color_code' => 'nullable|string|max:7',
                'icon_code' => 'nullable|string|max:50',
                'description' => 'nullable|string',
                'sort_order' => 'nullable|integer|min:0',
                'is_active' => 'boolean',
            ]);

            $commodity->update($validatedData);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'commodities',
                $commodity->id,
                'Updated Commodity: ' . $commodity->name,
                'Commodity "' . $commodity->name . '" has been updated successfully.'
            );

            DB::commit();

            // Redirect back to the commodities index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.commodities.index', ['page' => $page])->with('success', 'Commodity updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update commodity: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while updating the commodity: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Commodity $commodity)
    {
        // Delete the commodity
        try {
            DB::beginTransaction();
            $commodityName = $commodity->name;
            $commodityId = $commodity->id;
            $commodity->delete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'commodities',
                $commodityId,
                'Deleted Commodity: ' . $commodityName,
                'Commodity "' . $commodityName . '" has been deleted successfully.'
            );
            DB::commit();
            
            // Redirect back to the commodities index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.commodities.index', ['page' => $page])->with('success', 'Commodity deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete commodity: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while deleting the commodity: Error: ' . $e->getMessage());
        }
    }

    /**
     * Listing of trashed commodities.
     */
    public function trashed(Request $request)
    {
        // $trashedCommodities = Commodity::onlyTrashed()->get();

        // List all commodities
        $commodities = Commodity::onlyTrashed();

        $filters = $request->only(['search', 'status', 'sort_by', 'sort_order', 'per_page']);

        // Filter by search query
        if ($search = request()->input('search')) {
            $commodities = $commodities->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // filter by status
        if (in_array(request()->input('status'), ['active', 'inactive'])) {
            $isActive = request()->input('status') === 'active' ? 1 : 0;
            $commodities = $commodities->where('is_active', $isActive);
        }

        // sort by from get parameter
        if (request()->input('sort_by') && request()->input('sort_order')) {
            $sortBy = request()->input('sort_by');
            $sortDirection = request()->input('sort_order');
            $allowedSorts = ['code', 'name', 'type', 'is_active', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $commodities = $commodities->orderBy($sortBy, $sortDirection);
            }
    
        } else {
            // Default sort by deleted_at desc
            $commodities = $commodities->orderBy('deleted_at', 'desc');
        }

        $commodities = $commodities->paginate(15)->withQueryString();
        // add deleted_by user from the user activity logs
        foreach ($commodities as $commodity) {
            $deletionLog = \App\Models\UserActivity::where('activity_type', 'delete')
                ->where('table_name', 'commodities')
                ->where('record_id', $commodity->id)
                ->latest()
                ->first();
            $commodity->deleted_by = $deletionLog ? $deletionLog->user : null;
        }

        // filter options
        $filterOptions = [
            'status' => ['active', 'inactive'],
            'sort_by' => ['code', 'name', 'type', 'is_active', 'created_at'],
            'sort_order' => ['asc', 'desc'],
        ];

        return view('commodities.trashed', compact('commodities', 'filterOptions', 'filters'));
    }

    /**
     * Restore a trashed commodity.
     */
    public function restore($id)
    {
        try {
            $commodity = Commodity::onlyTrashed()->findOrFail($id);
            $commodity->restore();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'commodities',
                $commodity->id,
                'Restored Commodity: ' . $commodity->name,
                'Commodity "' . $commodity->name . '" has been restored successfully.'
            );
            return redirect()->route('master-data.commodities.index')->with('success', 'Commodity restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to restore commodity: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring the commodity: Error: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a trashed commodity.
     */
    public function forceDelete($id)
    {
        try {
            $commodity = Commodity::onlyTrashed()->findOrFail($id);
            $commodityName = $commodity->name;
            $commodityId = $commodity->id;
            $commodity->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'commodities',
                $commodityId,
                'Permanently Deleted Commodity: ' . $commodityName,
                'Commodity "' . $commodityName . '" has been permanently deleted.'
            );
            return redirect()->route('trashed-data.commodities.index')->with('success', 'Commodity permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to permanently delete commodity: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting the commodity: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore trashed commodities.
     */
    public function bulkRestore(Request $request)
    {
        $ids = $request->input('ids', []);
        // for now this restores all trashed commodities if none selected

        if (empty($ids)) {
            $ids = Commodity::onlyTrashed()->pluck('id');
        }
        try {
            $commodities = Commodity::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($commodities as $commodity) {
                $commodity->restore();
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'restore',
                    'commodities',
                    $commodity->id,
                    'Restored Commodity: ' . $commodity->name,
                    'Commodity "' . $commodity->name . '" has been restored successfully.'
                );
            }
            return redirect()->route('master-data.commodities.index')->with('success', count($commodities) . ' commodities restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk restore commodities: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring commodities: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk permanently delete trashed commodities.
     */
    public function bulkForceDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        // for now this deletes all trashed commodities if none selected
        if (empty($ids)) {
            $ids = Commodity::onlyTrashed()->pluck('id');
        }
        try {
            $commodities = Commodity::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($commodities as $commodity) {
                $commodityName = $commodity->name;
                $commodityId = $commodity->id;
                $commodity->forceDelete();
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'force_delete',
                    'commodities',
                    $commodityId,
                    'Permanently Deleted Commodity: ' . $commodityName,
                    'Commodity "' . $commodityName . '" has been permanently deleted.'
                );
            }
            return redirect()->route('trashed-data.commodities.index')->with('success', count($commodities) . ' commodities permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk permanently delete commodities: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting commodities: Error: ' . $e->getMessage());
        }
    }
}
