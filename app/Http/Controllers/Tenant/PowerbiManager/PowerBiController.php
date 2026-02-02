<?php

namespace App\Http\Controllers\Tenant\PowerbiManager;

use App\Http\Controllers\Controller;
use App\Models\Tenant\PowerbiLink;
use App\Models\Tenant\PowerbiLinkType;
use App\Models\Tenant\Company;
use App\Services\powerbiService;
use App\Services\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PowerbiController extends Controller
{   
    protected PowerbiService $powerbiService;
    protected UserAccessService $userAccessService;
    // Log user activities and notifications
    use \App\Traits\LogsUserActivity;

    public function __construct(PowerbiService $powerbiService, UserAccessService $userAccessService)
    {
        $this->powerbiService = $powerbiService;
        $this->userAccessService = $userAccessService;

        $this->middleware(['auth', 'permission:view powerbi reports'])->only(['index', 'show', 'iframe', 'getEmbedData']);

        $this->middleware(['auth', 'permission:create powerbi reports'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit powerbi reports'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:delete powerbi reports'])->only(['destroy']);
        $this->middleware(['auth', 'permission:restore trashed items'])->only(['trashed', 'restore']);
        $this->middleware(['auth', 'permission:force delete trashed items'])->only(['forceDelete']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters
        $filters = $request->only([
            'search',
            'powerbi_link_type_id',
            'link_source',
            'sort_by',
            'sort_direction'
        ]);

        // Fetch dashboards with filters and sorting
        $dashboards = $this->powerbiService
            ->getFilteredPowerbiLinks($user, $filters)
            ->paginate(15); // This works ONLY if getFilteredPowerbiLinks returns a Builder

        // Fetch powerbi link types for filter dropdown
        $powerbiLinkTypes = PowerbiLinkType::orderBy('name')->get();
        $linkSources = PowerbiLink::select('link_source')
            ->distinct()
            ->whereNotNull('link_source')
            ->orderBy('link_source')
            ->pluck('link_source');

        // if user is grower, redirect to dashboard view if only one dashboard
        if ($user->isGrowerRole() && $dashboards->total() == 1) {
            $singleDashboard = $dashboards->first();
            return redirect()->route('powerbi-links.view', $singleDashboard->obfuscated_id);
        }

        return view('powerbi.index', compact('dashboards', 'powerbiLinkTypes', 'linkSources', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Check if user has permission to create powerbi links
        // $this->authorize('create', PowerbiLink::class);
        $powerbiLinkTypes = PowerbiLinkType::orderBy('name')->get();
        // $companies = Company::orderBy('name')->get();
        $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        return view('powerbi.create', compact('powerbiLinkTypes', 'growers'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::user();
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'url' => 'required|url|max:1000',
                'grower_id' => 'required|exists:growers,id',
                'powerbi_link_type_id' => 'required|exists:powerbi_link_types,id',
                'link_source' => 'nullable|string|max:255',
                'is_public' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            // start transaction
            DB::beginTransaction();

            // Create powerbi link
            $powerbiLink = PowerbiLink::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'url' => $validated['url'],
                'grower_id' => $validated['grower_id'],
                'powerbi_link_type_id' => $validated['powerbi_link_type_id'],
                'link_source' => $validated['link_source'] ?? null,
                'added_by' => $user->id,
                'is_active' => $request->has('is_active') ? (bool)$validated['is_active'] : true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'created_by' => $user->id,
            ]);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'powerbi_links',
                $powerbiLink->id,
                'Created powerbi link: ' . $powerbiLink->name,
                'Powerbi link "' . $powerbiLink->name . '" created successfully.'
            );

            DB::commit();

            return redirect()->route('powerbi-links.index')
                ->with('success', 'Powerbi link created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating powerbi link: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'An error occurred while creating the powerbi link: ' . $e->getMessage()]);
        }
    }

    /**
     * Show specific powerbi dashboard (without embed, just details)
     */
    public function show($id)
    {
        $user = Auth::user();
        $powerbiLink = PowerbiLink::findOrFail($id);
        // load relationships
        $powerbiLink->load('powerbiLinkType', 'grower', 'creator', 'addedBy', 'powerbiLinkType.userGroups');
        // permission check already done in middleware
        return view('powerbi.show', compact('powerbiLink'));
    }

    /**
     * View specific powerbi dashboard (with embed)
     */
    public function view(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->powerbiService->decodeObfuscatedId($obfuscatedId);
        
        if (!$linkId) {
            abort(404, 'Dashboard not found.');
        }

        $powerbiLink = PowerbiLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->powerbiService->canUserAccessLink($user, $powerbiLink)) {
            abort(403, 'You do not have permission to view this dashboard.');
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->powerbiService->getEmbedUrl($powerbiLink, $user);
        
        // Get dashboard configuration
        $config = $this->powerbiService->getDashboardConfig($powerbiLink);

        // log access activity
        $this->logUserActivity(
            'view',
            'powerbi_links',
            $powerbiLink->id,
            'Viewed powerbi link: ' . $powerbiLink->name
        );

        return view('powerbi.view', compact('powerbiLink', 'embedUrl', 'config'));
    }

    /**
     * Get dashboard embed data (AJAX)
     */
    public function getEmbedData(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->powerbiService->decodeObfuscatedId($obfuscatedId);

        if (!$linkId) {
            return response()->json(['error' => 'Dashboard not found.'], 404);
        }

        $powerbiLink = PowerbiLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->powerbiService->canUserAccessLink($user, $powerbiLink)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->powerbiService->getEmbedUrl($powerbiLink, $user);
        
        // Get dashboard configuration
        $config = $this->powerbiService->getDashboardConfig($powerbiLink);

        return response()->json([
            'embedUrl' => $embedUrl,
            'config' => $config,
            'title' => $powerbiLink->title,
            'description' => $powerbiLink->description,
        ]);
    }

    /**
     * Dashboard iframe endpoint
     */
    public function iframe(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->powerbiService->decodeObfuscatedId($obfuscatedId);
        
        if (!$linkId) {
            abort(404, 'Dashboard not found.');
        }

        $powerbiLink = PowerbiLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->powerbiService->canUserAccessLink($user, $powerbiLink)) {
            abort(403, 'You do not have permission to view this dashboard.');
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->powerbiService->getEmbedUrl($powerbiLink, $user);
        
        // Return minimal HTML for iframe
        return view('powerbi.iframe', compact('embedUrl'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $user = Auth::user();
        $powerbiLink = PowerbiLink::findOrFail($id);

        // permission check already done in middleware
        $powerbiLinkTypes = PowerbiLinkType::orderBy('name')->get();
        $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        return view('powerbi.edit', compact('powerbiLink', 'powerbiLinkTypes', 'growers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PowerbiLink $powerbiLink)
    {
        try {
            $user = Auth::user();
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'url' => 'required|url|max:1000',
                'grower_id' => 'required|exists:growers,id',
                'powerbi_link_type_id' => 'required|exists:powerbi_link_types,id',
                'link_source' => 'nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            DB::beginTransaction();

            $powerbiLink->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'url' => $validated['url'],
                'grower_id' => $validated['grower_id'],
                'powerbi_link_type_id' => $validated['powerbi_link_type_id'],
                'link_source' => $validated['link_source'] ?? null,
                'is_active' => $request->has('is_active') ? (bool)$validated['is_active'] : true,
                'sort_order' => $validated['sort_order'] ?? $powerbiLink->sort_order,
            ]);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'powerbi_links',
                $powerbiLink->id,
                'Updated powerbi link: ' . $powerbiLink->name,
                'Powerbi link "' . $powerbiLink->name . '" updated successfully.'
            );

            DB::commit();

            return redirect()->route('powerbi-links.index')
                ->with('success', 'Powerbi link updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating powerbi link: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'An error occurred while updating the powerbi link: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PowerbiLink $powerbiLink)
    {
        // permission check already done in middleware
        try {
            $user = Auth::user();
            DB::beginTransaction();

            $powerbiLink->delete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'powerbi_links',
                $powerbiLink->id,
                'Deleted powerbi link: ' . $powerbiLink->name,
                'Powerbi link "' . $powerbiLink->name . '" deleted successfully.'
            );
            DB::commit();
            return redirect()->route('powerbi-links.index')
                ->with('success', 'Powerbi link deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting powerbi link: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while deleting the powerbi link: ' . $e->getMessage()]);
        }
    }

    /**
     * Display a listing of trashed resources.
     */
    public function trashed(Request $request)
    {
        $user = Auth::user();
        // Get filter parameters
        $filters = $request->only([
            'search',
            'powerbi_link_type_id',
            'link_source',
            'sort_by',
            'sort_order'
        ]);

        // Fetch trashed links with filters and sorting
        $trashedLinks = $this->powerbiService
            ->getFilteredTrashedPowerbiLinks($filters)
            ->paginate(15); // This works ONLY if getFilteredTrashedPowerbiLinks returns a Builder

        // Fetch powerbi link types for filter dropdown
        $powerbiLinkTypes = PowerbiLinkType::orderBy('name')->get();
        $linkSources = PowerbiLink::select('link_source')
            ->distinct()
            ->orderBy('link_source')
            ->pluck('link_source');

        return view('powerbi-links.trashed', compact('trashedLinks', 'powerbiLinkTypes', 'linkSources', 'filters'));
    }

    /**
     * Restore a trashed resource.
     */
    public function restore($id)
    {
        try {
            $user = Auth::user();
            $powerbiLink = PowerbiLink::onlyTrashed()->findOrFail($id);
            DB::beginTransaction();
            $powerbiLink->restore();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'powerbi_links',
                $powerbiLink->id,
                'Restored powerbi link: ' . $powerbiLink->name,
                'Powerbi link "' . $powerbiLink->name . '" restored successfully.'
            );
            DB::commit();
            return redirect()->route('powerbi-links.show', $powerbiLink->id)
                ->with('success', 'Powerbi link restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error restoring powerbi link: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while restoring the powerbi link: ' . $e->getMessage()]);
        }
    }

    /**
     * Permanently delete a trashed resource.
     */
    public function forceDelete($id)
    {
        try {
            $user = Auth::user();
            $powerbiLink = PowerbiLink::onlyTrashed()->findOrFail($id);
            DB::beginTransaction();
            $powerbiLink->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'powerbi_links',
                $powerbiLink->id,
                'Permanently deleted powerbi link: ' . $powerbiLink->name,
                'Powerbi link "' . $powerbiLink->name . '" permanently deleted.'
            );
            DB::commit();
            return redirect()->route('trashed-data.powerbi-links.index')
                ->with('success', 'Powerbi link permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error permanently deleting powerbi link: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while permanently deleting the powerbi link: ' . $e->getMessage()]);
        }
    }
}
