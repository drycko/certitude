<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Variety;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class VarietyController extends Controller
{
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        $this->middleware(['auth', 'permission:view varieties'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create varieties'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit varieties'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete varieties'])->only(['destroy']);
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
        // List all varieties
        $varieties = Variety::query();

        // Filter by search query
        if ($search = request()->input('search')) {
            $varieties = $varieties->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // filter to set the number of items per page
        $filters['per_page'] = $request->input('per_page', 15);
        // if all
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = Variety::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }

        // sort by from get parameter
        if (request()->input('sort_by') && request()->input('sort_direction')) {
            $sortBy = request()->input('sort_by');
            $sortDirection = request()->input('sort_direction');
            $allowedSorts = ['code', 'name', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $varieties = $varieties->orderBy($sortBy, $sortDirection);
            }
    
        } else {
            // Default sort by created_at desc
            $varieties = $varieties->orderBy('created_at', 'desc');
        }

        $varieties = $varieties->paginate($filters['per_page'])->withQueryString();

        // filter options
        $filterOptions = [
            'sort_by' => ['code', 'name', 'created_at'],
            'sort_direction' => ['asc', 'desc'],
        ];

        return view('varieties.index', compact('varieties', 'filterOptions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try {
            return view('varieties.create');
        } catch (\Exception $e) {
            \Log::error('Failed to log user activity: ' . $e->getMessage());
            return view('varieties.create')->withErrors('An error occurred while preparing the form. Please try again.');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:varieties,code',
                'description' => 'nullable|string',
            ]);

            $variety = Variety::create($validatedData);
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'varieties',
                $variety->id,
                'Created Variety: ' . $variety->name,
                'Variety "' . $variety->name . '" has been created successfully.'
            );

            return redirect()->route('master-data.varieties.index')->with('success', 'Variety created successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to create variety: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while creating the variety: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Variety $variety)
    {
        return view('varieties.show', compact('variety'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Variety $variety)
    {
        return view('varieties.edit', compact('variety'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Variety $variety)
    {
        try {
            DB::beginTransaction();

            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:100|unique:varieties,code,' . $variety->id,
                'description' => 'nullable|string',
            ]);

            $variety->update($validatedData);
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'varieties',
                $variety->id,
                'Updated Variety: ' . $variety->name,
                'Variety "' . $variety->name . '" has been updated successfully.'
            );

            DB::commit();

            $page = $request->input('page', 1);
            return redirect()->route('master-data.varieties.index', ['page' => $page])->with('success', 'Variety updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to update variety: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while updating the variety: Error: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Variety $variety)
    {
        try {
            DB::beginTransaction();
            $varietyName = $variety->name;
            $varietyId = $variety->id;
            $variety->delete();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'varieties',
                $varietyId,
                'Deleted Variety: ' . $varietyName,
                'Variety "' . $varietyName . '" has been deleted successfully.'
            );
            
            DB::commit();
            
            $page = $request->input('page', 1);
            return redirect()->route('master-data.varieties.index', ['page' => $page])->with('success', 'Variety deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to delete variety: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while deleting the variety: Error: ' . $e->getMessage());
        }
    }

    /**
     * Listing of trashed varieties.
     */
    public function trashed()
    {
        $varieties = Variety::onlyTrashed();

        // Filter by search query
        if ($search = request()->input('search')) {
            $varieties = $varieties->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // sort by from get parameter
        if (request()->input('sort_by') && request()->input('sort_direction')) {
            $sortBy = request()->input('sort_by');
            $sortDirection = request()->input('sort_direction');
            $allowedSorts = ['code', 'name', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $varieties = $varieties->orderBy($sortBy, $sortDirection);
            }
    
        } else {
            // Default sort by deleted_at desc
            $varieties = $varieties->orderBy('deleted_at', 'desc');
        }

        $varieties = $varieties->paginate(15)->withQueryString();
        
        // add deleted_by user from the user activity logs
        foreach ($varieties as $variety) {
            $deletionLog = \App\Models\UserActivity::where('activity_type', 'delete')
                ->where('table_name', 'varieties')
                ->where('record_id', $variety->id)
                ->latest()
                ->first();
            $variety->deleted_by = $deletionLog ? $deletionLog->user : null;
        }

        // filter options
        $filterOptions = [
            'sort_by' => ['code', 'name', 'created_at'],
            'sort_direction' => ['asc', 'desc'],
        ];

        return view('varieties.trashed', compact('varieties', 'filterOptions'));
    }

    /**
     * Restore a trashed variety.
     */
    public function restore($id)
    {
        try {
            $variety = Variety::onlyTrashed()->findOrFail($id);
            $variety->restore();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'varieties',
                $variety->id,
                'Restored Variety: ' . $variety->name,
                'Variety "' . $variety->name . '" has been restored successfully.'
            );
            
            return redirect()->route('master-data.varieties.index')->with('success', 'Variety restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to restore variety: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring the variety: Error: ' . $e->getMessage());
        }
    }

    /**
     * Permanently delete a trashed variety.
     */
    public function forceDelete($id)
    {
        try {
            $variety = Variety::onlyTrashed()->findOrFail($id);
            $varietyName = $variety->name;
            $varietyId = $variety->id;
            $variety->forceDelete();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'varieties',
                $varietyId,
                'Permanently Deleted Variety: ' . $varietyName,
                'Variety "' . $varietyName . '" has been permanently deleted.'
            );
            
            return redirect()->route('trashed-data.varieties.index')->with('success', 'Variety permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to permanently delete variety: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting the variety: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk restore trashed varieties.
     */
    public function bulkRestore(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            $ids = Variety::onlyTrashed()->pluck('id');
        }
        
        try {
            $varieties = Variety::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($varieties as $variety) {
                $variety->restore();
                
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'restore',
                    'varieties',
                    $variety->id,
                    'Restored Variety: ' . $variety->name,
                    'Variety "' . $variety->name . '" has been restored successfully.'
                );
            }
            
            return redirect()->route('master-data.varieties.index')->with('success', count($varieties) . ' varieties restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk restore varieties: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while restoring varieties: Error: ' . $e->getMessage());
        }
    }

    /**
     * Bulk permanently delete trashed varieties.
     */
    public function bulkForceDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        if (empty($ids)) {
            $ids = Variety::onlyTrashed()->pluck('id');
        }
        
        try {
            $varieties = Variety::onlyTrashed()->whereIn('id', $ids)->get();
            foreach ($varieties as $variety) {
                $varietyName = $variety->name;
                $varietyId = $variety->id;
                $variety->forceDelete();
                
                // log activity and create notification
                $this->logUserActivityAndNotification(
                    'force_delete',
                    'varieties',
                    $varietyId,
                    'Permanently Deleted Variety: ' . $varietyName,
                    'Variety "' . $varietyName . '" has been permanently deleted.'
                );
            }
            
            return redirect()->route('trashed-data.varieties.index')->with('success', count($varieties) . ' varieties permanently deleted successfully.');
        } catch (\Exception $e) {
            \Log::error('Failed to bulk permanently delete varieties: ' . $e->getMessage());
            return redirect()->back()->withErrors('An error occurred while permanently deleting varieties: Error: ' . $e->getMessage());
        }
    }

    /**
     * Export varieties to CSV format.
     */
    public function exportCsv(Request $request)
    {
        // Build query with same filters as index
        $varieties = Variety::query();

        // Filter by search query
        if ($search = $request->input('search')) {
            $varieties = $varieties->where(function ($query) use ($search) {
                $query->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            });
        }

        // Apply sorting
        if ($request->input('sort_by') && $request->input('sort_direction')) {
            $sortBy = $request->input('sort_by');
            $sortDirection = $request->input('sort_direction');
            $allowedSorts = ['code', 'name', 'created_at'];
            if (in_array($sortBy, $allowedSorts) && in_array($sortDirection, ['asc', 'desc'])) {
                $varieties = $varieties->orderBy($sortBy, $sortDirection);
            }
        } else {
            $varieties = $varieties->orderBy('created_at', 'desc');
        }

        // Get all matching varieties
        $varieties = $varieties->get();

        // Generate CSV content
        $filename = 'varieties_export_' . now()->format('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($varieties) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, ['Code', 'Name', 'Description', 'Files Count', 'Created At', 'Updated At']);
            
            // Add data rows
            foreach ($varieties as $variety) {
                fputcsv($file, [
                    $variety->code,
                    $variety->name,
                    $variety->description,
                    $variety->files()->count(),
                    $variety->created_at->format('Y-m-d H:i:s'),
                    $variety->updated_at->format('Y-m-d H:i:s'),
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}