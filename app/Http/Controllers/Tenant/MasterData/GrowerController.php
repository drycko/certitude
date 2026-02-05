<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Tenant\User;
use App\Models\Tenant\Grower;
use App\Models\Tenant\Fbo;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\GrowerUser;
use App\Services\Tenant\NotificationService;
use App\Services\Tenant\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GrowerController extends Controller
{
    protected NotificationService $notificationService;
    protected $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        // $this->user = Auth::user();
        
        $this->middleware(['auth', 'permission:view growers'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create growers'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit growers'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:assign growers'])->only(['assignUsers', 'assignFbos', 'assignCommodities']);
        $this->middleware(['auth', 'permission:delete growers'])->only(['destroy']);
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
        $query = Grower::with('commodities', 'fbos');

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'fbo_id' => $request->input('fbo_id'),
            'commodity_id' => $request->input('commodity_id'),
            'expiry_status' => $request->input('expiry_status'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_direction' => $request->input('sort_direction', 'asc'),
        ];

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('grower_number', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by FBO
        if ($request->filled('fbo_id')) {
            $query->whereHas('fbos', function ($q) use ($request) {
                $q->where('fbo_id', $request->fbo_id);
            });
        }

        // Filter by commodity
        if ($request->filled('commodity_id')) {
            $query->whereHas('commodities', function ($q) use ($request) {
                $q->where('commodity_id', $request->commodity_id);
            });
        }

        // Sorting
        $sortBy = $filters['sort_by'];
        $sortDirection = $filters['sort_direction'];
        $allowedSortFields = ['name', 'grower_number', 'created_at'];
        
        if (in_array($sortBy, $allowedSortFields) && in_array($sortDirection, ['asc', 'desc'])) {
            $query->orderBy($sortBy, $sortDirection);
        } else {
            $query->orderBy('name', 'asc');
        }

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $growers = $query->paginate($query->count())->withQueryString();
        } else {
            $growers = $query->paginate($perPage)->withQueryString();
        }

        // Get filter options
        $fbos = Fbo::orderBy('name')->get();
        $commodities = Commodity::orderBy('sort_order')->orderBy('name')->get();

        return view('growers.index', compact('growers', 'fbos', 'commodities', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $fbos = Fbo::orderBy('code')->get();
        $commodities = Commodity::orderBy('sort_order')->orderBy('name')->get();

        return view('growers.create', compact('fbos', 'commodities'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        // user can store a new grower
        if (!$this->userAccessService->canCreateGrower($user)) {
            abort(403, 'You do not have permission to create a new grower.');
        }
        
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grower_number' => 'required|string|max:100|unique:growers,grower_number',
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'fbo_ids' => 'nullable|array',
            'fbo_ids.*' => 'exists:fbos,id',
            'commodity_ids' => 'nullable|array',
            'commodity_ids.*' => 'exists:commodities,id',
        ]);
        $validated['created_by'] = $user->id;

         // Use transaction to ensure data integrity

        try {
            DB::beginTransaction();
            // Create grower
            $grower = Grower::create($validated);

            // Attach FBOs
            if (!empty($validated['fbo_ids'])) {
                $grower->fbos()->sync($validated['fbo_ids']);
                // foreach ($validated['fbo_ids'] as $fboId) {
                //     $grower->fbos()->create(['fbo_id' => $fboId]);
                // }
            }

            // Attach Commodities
            if (!empty($validated['commodity_ids'])) {
                $grower->commodities()->sync($validated['commodity_ids']);
            }

            // $files[] = $file;
            DB::commit();
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'growers',
                $grower->id,
                'Created grower: ' . $grower->name,
                'Grower "' . $grower->name . '" has been created successfully.'
            );

            return redirect()->route('master-data.growers.show', $grower->id)->with('success', 'Grower created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'An error occurred while creating the grower: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Grower $grower)
    {
        $users = User::orderBy('name')->get();
        $fbos = Fbo::orderBy('code')->get();
        $commodities = Commodity::orderBy('sort_order')->orderBy('name')->get();
        $activityLogs = $this->getActivityLogs('growers', $grower->id);
        // get users assigned to this grower
        $assignedUserIds = $grower->users->pluck('id')->toArray();
         // eager load relationships
        $grower->load('commodities', 'fbos', 'creator');
        return view('growers.show', compact('grower', 'users', 'fbos', 'commodities', 'activityLogs', 'assignedUserIds'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Grower $grower)
    {
        $user = Auth::user();
        // user can store a new grower
        if (!$this->userAccessService->canEditGrower($user, $grower)) {
            abort(403, 'You do not have permission to edit a grower.');
        }
        // eager load relationships
        $grower->load('commodities', 'fbos');
        $fbos = Fbo::orderBy('code')->get();
        $commodities = Commodity::orderBy('sort_order')->orderBy('name')->get();
        return view('growers.edit', compact('grower', 'fbos', 'commodities'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Grower $grower)
    {
        $user = Auth::user();
        // user can store a new grower
        if (!$this->userAccessService->canEditGrower($user, $grower)) {
            abort(403, 'You do not have permission to edit a grower.');
        }
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'grower_number' => 'required|string|max:100|unique:growers,grower_number,' . $grower->id,
            'address' => 'nullable|string|max:500',
            'contact_person' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        // Get the page parameter
        $page = $request->input('page', 1);

        try {
            DB::beginTransaction();
            // Update grower
            $grower->update($validated);
            DB::commit();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'growers',
                $grower->id,
                'Updated grower: ' . $grower->name,
                'Grower "' . $grower->name . '" has been updated successfully.'
            );

            return redirect()->route('master-data.growers.show', ['grower' => $grower->id, 'page' => $page])->with('success', 'Grower updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'An error occurred while updating the grower: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Assign the specified resource to a user.
     */
    public function assignUsers(Request $request, Grower $grower)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'users.*' => 'exists:users,id',
        ]);

        try {
            DB::beginTransaction();

            // validate the grower exists
            if (!$grower) {
                return back()->withErrors(['error' => 'Grower not found.'])->withInput();
            }

            // remove previous grower_number from users not assigned anymore but have grower_number set to this grower
            $previousUserIds = $grower->users->pluck('id')->toArray();
            $removedUserIds = array_diff($previousUserIds, $validated['user_ids']);
            foreach ($removedUserIds as $removedUserId) {
                $user = User::find($removedUserId);
                if ($user && $user->grower_number === $grower->grower_number) {
                    // we can find if user is asinged to any other grower then set to that grower_number
                    $otherGrower = $user->growers()->where('grower_id', '!=', $grower->id)->first();
                    if ($otherGrower) {
                        $user->grower_number = $otherGrower->grower_number;
                    } else {
                        $user->grower_number = null;
                    }
                    $user->save();
                }
            }
            // Sync users
            $grower->users()->sync($validated['user_ids']);

            // i will need to update the user->grower_number field as well
            foreach ($grower->users as $user) {
                $user->grower_number = $grower->grower_number;
                $user->save();
                // log activity for each user assignment
                $this->logUserActivityAndNotification(
                    'assign',
                    'grower_users',
                    $grower->id,
                    'Assigned user: ' . $user->name . ' to grower: ' . $grower->name,
                    'User "' . $user->name . '" has been assigned to grower "' . $grower->name . '" successfully.'
                );
            }
            DB::commit();

            return redirect()->route('master-data.growers.show', $grower)->with('success', 'Users assigned successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error assigning users to grower: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while assigning users: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Assign FBOs to the specified grower.
     */
    public function assignFbos(Request $request, Grower $grower)
    {
        $validated = $request->validate([
            'fbo_ids' => 'required|array',
            'fbo_ids.*' => 'exists:fbos,id',
        ]);

        // $grower = Grower::find($validated['grower_id']);
        if (!$grower) {
            return back()->withErrors(['error' => 'Grower not found.'])->withInput();
        }

        try {
            DB::beginTransaction();
            // Sync FBOs
            $grower->fbos()->sync($validated['fbo_ids']);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'assign',
                'grower_fbos',
                $grower->id,
                'Assigned FBOs to grower: ' . $grower->name,
                'FBOs have been assigned to grower "' . $grower->name . '" successfully.'
            );
            DB::commit();

            return redirect()->route('master-data.growers.show', $grower)->with('success', 'FBOs assigned successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error assigning FBOs to grower: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while assigning FBOs: ' . $e->getMessage()])->withInput();
        }

    }

    /**
     * Assign Commodities to the specified grower.
    */
    public function assignCommodities(Request $request, Grower $grower)
    {
        $validated = $request->validate([
            'commodity_ids' => 'required|array',
            'commodity_ids.*' => 'exists:commodities,id',
        ]);

        try {
            DB::beginTransaction();

            // Sync Commodities
            $grower->commodities()->sync($validated['commodity_ids']);
            
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'assign',
                'grower_commodities',
                $grower->id,
                'Assigned commodities to grower: ' . $grower->name,
                'Commodities have been assigned to grower "' . $grower->name . '" successfully.'
            );
            DB::commit();
            return redirect()->route('master-data.growers.show', $grower)->with('success', 'Commodities assigned successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error assigning commodities to grower: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while assigning commodities: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Grower $grower)
    {
        // Get the page parameter
        $page = $request->input('page', 1);

        // Delete grower
        $growerName = $grower->name;
        $grower->delete();
        // log activity and create notification
        $this->logUserActivityAndNotification(
            'delete',
            'growers',
            $grower->id,
            'Deleted grower: ' . $growerName,
            'Grower "' . $growerName . '" has been deleted successfully.'
        );

        return redirect()->route('master-data.growers.index', ['page' => $page])->with('success', 'Grower deleted successfully.');
    }

    /**
     * Trashed growers list.
     */
    public function trashed(Request $request)
    {
        // $trashedGrowers = Grower::onlyTrashed()->get();
        $query = Grower::onlyTrashed()->with('commodities', 'fbos')->orderBy('name');

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'fbo_id' => $request->input('fbo_id'),
            'commodity_id' => $request->input('commodity_id'),
            'expiry_status' => $request->input('expiry_status'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_direction' => $request->input('sort_direction', 'asc'),
        ];

        // Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('grower_number', 'like', '%' . $searchTerm . '%');
            });
        }

        // filter by fbo
        if ($request->filled('fbo_id')) {
            $fboId = $request->input('fbo_id');
            $query->whereHas('fbos', function ($q) use ($fboId) {
                $q->where('fbo_id', $fboId);
            });
        }

        // filter by commodity
        if ($request->filled('commodity_id')) {
            $commodityId = $request->input('commodity_id');
            $query->whereHas('commodities', function ($q) use ($commodityId) {
                $q->where('commodity_id', $commodityId);
            });
        }

        $growers = $query->paginate(20)->appends($filters);

        // Get filter options
        $fbos = Fbo::orderBy('code')->get();
        $commodities = Commodity::orderBy('sort_order')->orderBy('name')->get();

        return view('growers.trashed', compact('growers', 'fbos', 'commodities', 'filters'));
    }

    /**
     * Restore a trashed grower.
     */
    public function restore($id)
    {
        \Log::info('GrowerController::restore method called with ID: ' . $id);
        
        try {
            DB::beginTransaction();
            $grower = Grower::onlyTrashed()->findOrFail($id);
            if (!$grower) {
                \Log::warning('Grower not found with ID: ' . $id);
                return redirect()->route('trashed-data.growers.index')->withErrors(['error' => 'Grower not found.']);
            }
            \Log::info('Restoring grower: ' . $grower->name);

            $grower->restore();
            DB::commit();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'growers',
                $grower->id,
                'Restored grower: ' . $grower->name,
                'Grower "' . $grower->name . '" has been restored successfully.'
            );
            return redirect()->route('master-data.growers.index')->with('success', 'Grower restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('trashed-data.growers.index')->withErrors(['error' => 'An error occurred while restoring the grower: ' . $e->getMessage()]);
        }
    }

    /**
     * Permanently delete a trashed grower.
     */
    public function forceDelete($id)
    {
        \Log::info('GrowerController::forceDelete method called with ID: ' . $id);
        
        try {
            DB::beginTransaction();
            $grower = Grower::onlyTrashed()->findOrFail($id);
            if (!$grower) {
                \Log::warning('Grower not found with ID: ' . $id);
                return redirect()->route('trashed-data.growers.index')->withErrors(['error' => 'Grower not found.']);
            }
            $growerName = $grower->name;
            \Log::info('Permanently deleting grower: ' . $grower->name);

            $grower->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'growers',
                $id,
                'Permanently deleted grower: ' . $growerName,
                'Grower "' . $growerName . '" has been permanently deleted.'
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('trashed-data.growers.index')->withErrors(['error' => 'An error occurred while permanently deleting the grower: ' . $e->getMessage()]);
        }
        return redirect()->route('trashed-data.growers.index')->with('success', 'Grower permanently deleted successfully.');
    }
}
