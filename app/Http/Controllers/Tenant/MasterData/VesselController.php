<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Vessel;
use App\Services\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VesselController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        $this->middleware(['auth', 'permission:view vessels'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create vessels'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit vessels'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete vessels'])->only(['destroy']);
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
        // List all vessels
        $vessels = Vessel::query();

        // Filter by search query
        if ($search = request()->input('search')) {
            $vessels = $vessels->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // filter to set the number of items per page
        $filters['per_page'] = $request->input('per_page', 15);
        // if all
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = Vessel::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }

        // sort by from get parameter
        if (request()->input('sort_by') && request()->input('sort_direction')) {
            $sortBy = request()->input('sort_by');
            $sortDirection = request()->input('sort_direction');
            $allowedSorts = ['name', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $vessels = $vessels->orderBy($sortBy, $sortDirection);
            }
    
        } else {
            // Default sort by created_at desc
            $vessels = $vessels->orderBy('created_at', 'desc');
        }

        $vessels = $vessels->paginate($filters['per_page'])->withQueryString();

        // filter options
        $filterOptions = [
            'sort_by' => ['name', 'created_at'],
            'sort_direction' => ['asc', 'desc'],
        ];

        return view('vessels.index', compact('vessels', 'filterOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            return view('vessels.create');
        } catch (\Exception $e) {
            \Log::error('Failed to log user activity: ' . $e->getMessage());
            return view('vessels.create')->withErrors('An error occurred while preparing the form. Please try again.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:vessels,name',
                'description' => 'nullable|string',
            ]);

            $vessel = Vessel::create($validatedData);
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'vessels',
                $vessel->id,
                'Created Vessel: ' . $vessel->name,
                'Vessel "' . $vessel->name . '" has been created successfully.'
            );

            return redirect()->route('master-data.vessels.index')->with('success', 'Vessel created successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to create vessel: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while creating the vessel: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Vessel $vessel)
    {
        return view('vessels.show', compact('vessel'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vessel $vessel)
    {
        return view('vessels.edit', compact('vessel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vessel $vessel)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:vessels,name,' . $vessel->id,
                'description' => 'nullable|string',
            ]);

            $vessel->update($validatedData);
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'vessels',
                $vessel->id,
                'Updated Vessel: ' . $vessel->name,
                'Vessel "' . $vessel->name . '" has been updated successfully.'
            );

            DB::commit();

            $page = $request->input('page', 1);
            return redirect()->route('master-data.vessels.index', ['page' => $page])->with('success', 'Vessel updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update vessel: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while updating the vessel: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Vessel $vessel)
    {
        try {
            DB::beginTransaction();
            $vesselName = $vessel->name;
            $vesselId = $vessel->id;
            $vessel->delete();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'vessels',
                $vesselId,
                'Deleted Vessel: ' . $vesselName,
                'Vessel "' . $vesselName . '" has been deleted successfully.'
            );
            
            DB::commit();
            
            $page = $request->input('page', 1);
            return redirect()->route('master-data.vessels.index', ['page' => $page])->with('success', 'Vessel deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete vessel: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while deleting the vessel: Error: ' . $e->getMessage());
        }
    }

    /**
     * Listing of trashed vessels.
     */
    public function trashed()
    {
        $vessels = Vessel::onlyTrashed();

        // Filter by search query
        if ($search = request()->input('search')) {
            $vessels = $vessels->where(function ($query) use ($search) {
                $query->where('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // sort by from get parameter
        if (request()->input('sort_by') && request()->input('sort_direction')) {
            $sortBy = request()->input('sort_by');
            $sortDirection = request()->input('sort_direction');
            $allowedSorts = ['name', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $vessels = $vessels->orderBy($sortBy, $sortDirection);
            }
    
        } else {
            // Default sort by deleted_at desc
            $vessels = $vessels->orderBy('deleted_at', 'desc');
        }

        $vessels = $vessels->paginate(15)->withQueryString();
        
        // add deleted_by user from the user activity logs
        foreach ($vessels as $vessel) {
            $deletionLog = \App\Models\UserActivity::where('activity_type', 'delete')
                ->where('table_name', 'vessels')
                ->where('record_id', $vessel->id)
                ->latest()
                ->first();
            $vessel->deleted_by = $deletionLog ? $deletionLog->user : null;
        }

        // filter options
        $filterOptions = [
            'sort_by' => ['name', 'created_at'],
            'sort_direction' => ['asc', 'desc'],
        ];

        return view('vessels.trashed', compact('vessels', 'filterOptions'));
    }

    /**
     * Restore a trashed vessel.
     */
    public function restore($id)
    {
        try {
            $vessel = Vessel::onlyTrashed()->findOrFail($id);
            $vessel->restore();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'vessels',
                $vessel->id,
                'Restored Vessel: ' . $vessel->name,
                'Vessel "' . $vessel->name . '" has been restored successfully.'
            );
            
            return redirect()->route('master-data.vessels.index')->with('success', 'Vessel restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to restore vessel: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring the vessel: Error: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a trashed vessel.
     */
    public function forceDelete($id)
    {
        try {
            $vessel = Vessel::onlyTrashed()->findOrFail($id);
            $vesselName = $vessel->name;
            $vesselId = $vessel->id;
            $vessel->forceDelete();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'vessels',
                $vesselId,
                'Permanently Deleted Vessel: ' . $vesselName,
                'Vessel "' . $vesselName . '" has been permanently deleted.'
            );
            
            return redirect()->route('trashed-data.vessels.index')->with('success', 'Vessel permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to permanently delete vessel: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting the vessel: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore trashed vessels.
     */
    public function bulkRestore(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            $ids = Vessel::onlyTrashed()->pluck('id');
        }
        
        try {
            $vessels = Vessel::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($vessels as $vessel) {
                $vessel->restore();
                
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'restore',
                    'vessels',
                    $vessel->id,
                    'Restored Vessel: ' . $vessel->name,
                    'Vessel "' . $vessel->name . '" has been restored successfully.'
                );
            }
            
            return redirect()->route('master-data.vessels.index')->with('success', count($vessels) . ' vessels restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk restore vessels: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring vessels: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk permanently delete trashed vessels.
     */
    public function bulkForceDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            $ids = Vessel::onlyTrashed()->pluck('id');
        }
        
        try {
            $vessels = Vessel::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($vessels as $vessel) {
                $vesselName = $vessel->name;
                $vesselId = $vessel->id;
                $vessel->forceDelete();
                
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'force_delete',
                    'vessels',
                    $vesselId,
                    'Permanently Deleted Vessel: ' . $vesselName,
                    'Vessel "' . $vesselName . '" has been permanently deleted.'
                );
            }
            
            return redirect()->route('trashed-data.vessels.index')->with('success', count($vessels) . ' vessels permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk permanently delete vessels: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting vessels: Error: ' . $e->getMessage());
        }
    }
}
