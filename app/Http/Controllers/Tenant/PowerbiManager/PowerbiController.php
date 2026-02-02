<?php

namespace App\Http\Controllers\SummaryManager;

use App\Http\Controllers\Controller;
use App\Models\SummaryLink;
use App\Models\SummaryLinkType;
use App\Models\Company;
use App\Services\SummaryService;
use App\Services\UserAccessService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class SummaryLinkController extends Controller
{   
    protected SummaryService $summaryService;
    protected UserAccessService $userAccessService;
    // Log user activities and notifications
    use \App\Traits\LogsUserActivity;

    public function __construct(SummaryService $summaryService, UserAccessService $userAccessService)
    {
        $this->summaryService = $summaryService;
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
            'summary_link_type_id',
            'link_source',
            'sort_by',
            'sort_direction'
        ]);

        // Fetch dashboards with filters and sorting
        $dashboards = $this->summaryService
            ->getFilteredSummaryLinks($user, $filters)
            ->paginate(15); // This works ONLY if getFilteredSummaryLinks returns a Builder

        // Fetch summary link types for filter dropdown
        $summaryLinkTypes = SummaryLinkType::orderBy('name')->get();
        $linkSources = SummaryLink::select('link_source')
            ->distinct()
            ->whereNotNull('link_source')
            ->orderBy('link_source')
            ->pluck('link_source');

        // if user is grower, redirect to dashboard view if only one dashboard
        if ($user->isGrowerRole() && $dashboards->total() == 1) {
            $singleDashboard = $dashboards->first();
            return redirect()->route('summary-links.view', $singleDashboard->obfuscated_id);
        }

        return view('summary.index', compact('dashboards', 'summaryLinkTypes', 'linkSources', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Check if user has permission to create summary links
        // $this->authorize('create', SummaryLink::class);
        $summaryLinkTypes = SummaryLinkType::orderBy('name')->get();
        // $companies = Company::orderBy('name')->get();
        $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        return view('summary.create', compact('summaryLinkTypes', 'growers'));
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
                'summary_link_type_id' => 'required|exists:summary_link_types,id',
                'link_source' => 'nullable|string|max:255',
                'is_public' => 'sometimes|boolean',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            // start transaction
            DB::beginTransaction();

            // Create summary link
            $summaryLink = SummaryLink::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'url' => $validated['url'],
                'grower_id' => $validated['grower_id'],
                'summary_link_type_id' => $validated['summary_link_type_id'],
                'link_source' => $validated['link_source'] ?? null,
                'added_by' => $user->id,
                'is_active' => $request->has('is_active') ? (bool)$validated['is_active'] : true,
                'sort_order' => $validated['sort_order'] ?? 0,
                'created_by' => $user->id,
            ]);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'summary_links',
                $summaryLink->id,
                'Created summary link: ' . $summaryLink->name,
                'Summary link "' . $summaryLink->name . '" created successfully.'
            );

            DB::commit();

            return redirect()->route('summary-links.index')
                ->with('success', 'Summary report created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating summary report: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'An error occurred while creating the summary report: ' . $e->getMessage()]);
        }
    }

    /**
     * Show specific summary dashboard (without embed, just details)
     */
    public function show($id)
    {
        $user = Auth::user();
        $summaryLink = SummaryLink::findOrFail($id);
        // load relationships
        $summaryLink->load('summaryLinkType', 'grower', 'creator', 'addedBy', 'summaryLinkType.userGroups');
        // permission check already done in middleware
        return view('summary.show', compact('summaryLink'));
    }

    /**
     * View specific summary dashboard (with embed)
     */
    public function view(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->summaryService->decodeObfuscatedId($obfuscatedId);
        
        if (!$linkId) {
            abort(404, 'Dashboard not found.');
        }

        $summaryLink = SummaryLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->summaryService->canUserAccessLink($user, $summaryLink)) {
            abort(403, 'You do not have permission to view this dashboard.');
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->summaryService->getEmbedUrl($summaryLink, $user);
        
        // Get dashboard configuration
        $config = $this->summaryService->getDashboardConfig($summaryLink);

        // log access activity
        $this->logUserActivity(
            'view',
            'summary_links',
            $summaryLink->id,
            'Viewed summary link: ' . $summaryLink->name
        );

        return view('summary.view', compact('summaryLink', 'embedUrl', 'config'));
    }

    /**
     * Get dashboard embed data (AJAX)
     */
    public function getEmbedData(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->summaryService->decodeObfuscatedId($obfuscatedId);

        if (!$linkId) {
            return response()->json(['error' => 'Dashboard not found.'], 404);
        }

        $summaryLink = SummaryLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->summaryService->canUserAccessLink($user, $summaryLink)) {
            return response()->json(['error' => 'Access denied.'], 403);
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->summaryService->getEmbedUrl($summaryLink, $user);
        
        // Get dashboard configuration
        $config = $this->summaryService->getDashboardConfig($summaryLink);

        return response()->json([
            'embedUrl' => $embedUrl,
            'config' => $config,
            'title' => $summaryLink->title,
            'description' => $summaryLink->description,
        ]);
    }

    /**
     * Dashboard iframe endpoint
     */
    public function iframe(Request $request, string $obfuscatedId)
    {
        $user = Auth::user();
        
        // Decode the obfuscated ID
        $linkId = $this->summaryService->decodeObfuscatedId($obfuscatedId);
        
        if (!$linkId) {
            abort(404, 'Dashboard not found.');
        }

        $summaryLink = SummaryLink::findOrFail($linkId);
        
        // Check if user can access this dashboard
        if (!$this->summaryService->canUserAccessLink($user, $summaryLink)) {
            abort(403, 'You do not have permission to view this dashboard.');
        }

        // Get embed URL with user-specific filters
        $embedUrl = $this->summaryService->getEmbedUrl($summaryLink, $user);
        
        // Return minimal HTML for iframe
        return view('powerbi.iframe', compact('embedUrl'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $user = Auth::user();
        $summaryLink = SummaryLink::findOrFail($id);

        // permission check already done in middleware
        $summaryLinkTypes = SummaryLinkType::orderBy('name')->get();
        $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        return view('summary.edit', compact('summaryLink', 'summaryLinkTypes', 'growers'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SummaryLink $summaryLink)
    {
        try {
            $user = Auth::user();
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'url' => 'required|url|max:1000',
                'grower_id' => 'required|exists:growers,id',
                'summary_link_type_id' => 'required|exists:summary_link_types,id',
                'link_source' => 'nullable|string|max:255',
                'is_active' => 'sometimes|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            DB::beginTransaction();

            $summaryLink->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'url' => $validated['url'],
                'grower_id' => $validated['grower_id'],
                'summary_link_type_id' => $validated['summary_link_type_id'],
                'link_source' => $validated['link_source'] ?? null,
                'is_active' => $request->has('is_active') ? (bool)$validated['is_active'] : true,
                'sort_order' => $validated['sort_order'] ?? $summaryLink->sort_order,
            ]);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'summary_links',
                $summaryLink->id,
                'Updated summary link: ' . $summaryLink->name,
                'Summary link "' . $summaryLink->name . '" updated successfully.'
            );

            DB::commit();

            return redirect()->route('summary-links.index')
                ->with('success', 'Summary report updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating summary report: ' . $e->getMessage());
            return back()->withInput()->withErrors(['error' => 'An error occurred while updating the summary report: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SummaryLink $summaryLink)
    {
        // permission check already done in middleware
        try {
            $user = Auth::user();
            DB::beginTransaction();

            $summaryLink->delete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'summary_links',
                $summaryLink->id,
                'Deleted summary link: ' . $summaryLink->name,
                'Summary link "' . $summaryLink->name . '" deleted successfully.'
            );
            DB::commit();
            return redirect()->route('summary-links.index')
                ->with('success', 'Summary report deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting summary report: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while deleting the summary report: ' . $e->getMessage()]);
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
            'summary_link_type_id',
            'link_source',
            'sort_by',
            'sort_order'
        ]);

        // Fetch trashed links with filters and sorting
        $trashedLinks = $this->summaryService
            ->getFilteredTrashedSummaryLinks($filters)
            ->paginate(15); // This works ONLY if getFilteredTrashedSummaryLinks returns a Builder

        // Fetch summary link types for filter dropdown
        $summaryLinkTypes = SummaryLinkType::orderBy('name')->get();
        $linkSources = SummaryLink::select('link_source')
            ->distinct()
            ->orderBy('link_source')
            ->pluck('link_source');

        return view('summary.trashed', compact('trashedLinks', 'summaryLinkTypes', 'linkSources', 'filters'));
    }

    /**
     * Restore a trashed resource.
     */
    public function restore($id)
    {
        try {
            $user = Auth::user();
            $summaryLink = SummaryLink::onlyTrashed()->findOrFail($id);
            DB::beginTransaction();
            $summaryLink->restore();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'summary_links',
                $summaryLink->id,
                'Restored summary link: ' . $summaryLink->name,
                'Summary link "' . $summaryLink->name . '" restored successfully.'
            );
            DB::commit();
            return redirect()->route('summary-links.show', $summaryLink->id)
                ->with('success', 'Summary report restored successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error restoring summary report: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while restoring the summary report: ' . $e->getMessage()]);
        }
    }

    /**
     * Permanently delete a trashed resource.
     */
    public function forceDelete($id)
    {
        try {
            $user = Auth::user();
            $summaryLink = SummaryLink::onlyTrashed()->findOrFail($id);
            DB::beginTransaction();
            $summaryLink->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'summary_links',
                $summaryLink->id,
                'Permanently deleted summary link: ' . $summaryLink->name,
                'Summary link "' . $summaryLink->name . '" permanently deleted.'
            );
            DB::commit();
            return redirect()->route('trashed-data.summary-links.index')
                ->with('success', 'Summary report permanently deleted.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error permanently deleting summary report: ' . $e->getMessage());
            return back()->withErrors(['error' => 'An error occurred while permanently deleting the summary report: ' . $e->getMessage()]);
        }
    }
}
