<?php

namespace App\Http\Controllers\Tenant\MasterData;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Company;
use App\Services\NotificationService;
use App\Services\UserAccessService;
use App\Services\FileStorageService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    protected FileStorageService $fileStorageService;
    protected NotificationService $notificationService;
    protected UserAccessService $userAccessService;
    // Use trait to handle
    use \App\Traits\LogsUserActivity;

    public function __construct(
        FileStorageService $fileStorageService,
        NotificationService $notificationService, UserAccessService $userAccessService)
    {
        
        $this->middleware(['auth', 'permission:view companies'])->only(['index', 'show']);
        $this->middleware(['auth', 'permission:create companies'])->only(['create', 'store']);
        $this->middleware(['auth', 'permission:edit companies'])->only(['edit', 'update']);
        $this->middleware(['auth', 'permission:assign companies'])->only(['assignUsers', 'assignFbos', 'assignCommodities']);
        $this->middleware(['auth', 'permission:delete companies'])->only(['destroy']);
        // Additional permissions for trashed items
        $this->middleware(['auth', 'permission:restore trashed items'])->only(['restore', 'bulkRestore']);
        $this->middleware(['auth', 'permission:view trashed items'])->only(['trashed']);
        $this->middleware(['auth', 'permission:force delete trashed items'])->only(['forceDelete', 'bulkForceDelete']);

        $this->notificationService = $notificationService;
        $this->userAccessService = $userAccessService;
        $this->fileStorageService = $fileStorageService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // List all companies (exclude N/A system default)
        $query = Company::where('is_system_default', false);

        // Build filters array
        $filters = [
            'search' => $request->input('search'),
            'country' => $request->input('country'),
            'is_active' => $request->input('is_active'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_order' => $request->input('sort_order', 'asc'),
        ];

        // Search functionality (not case-sensitive)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                ->orWhere('code', 'like', '%' . $searchTerm . '%')
                ->orWhere('contact_person', 'like', '%' . $searchTerm . '%')
                ->orWhere('email', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country', $request->country);
        }

        // Filter by active status
        if ($request->filled('is_active')) {
            $query->where('is_active', (int)$request->is_active);
        }

        // Sorting
        $sortField = $filters['sort_by'];
        $sortOrder = $filters['sort_order'];
        $allowedSortFields = ['name', 'code', 'contact_person', 'email', 'phone', 'is_active', 'created_at', 'users_count', 'documents_count'];
        
        if (in_array($sortField, $allowedSortFields) && in_array($sortOrder, ['asc', 'desc'])) {
            $query->with('users', 'documents', 'creator')
                ->withCount('users', 'documents')
                ->orderBy($sortField, $sortOrder);
        } else {
            $query->with('users', 'documents', 'creator')
                ->withCount('users', 'documents')
                ->orderBy('name', 'asc');
        }

        // Handle pagination
        $perPage = $request->input('per_page', 15);
        if ($perPage == -1) {
            $companies = $query->paginate($query->count())->withQueryString();
        } else {
            $companies = $query->paginate($perPage)->withQueryString();
        }

        // Filter options
        $countries = Company::select('country')->distinct()->pluck('country')->filter()->values();

        return view('companies.index', compact('companies', 'countries', 'filters'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Show create company form
        // from helper get countries
        $countries = get_countries();
        return view('companies.create', compact('countries'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate and store new company
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name',
                'address' => 'nullable|string|max:1000',
                'city' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'phone_number' => 'nullable|string|max:50',
                'website' => 'nullable|url|max:255',
                'industry' => 'nullable|string|max:255',
                'number_of_employees' => 'nullable|integer|min:0',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'is_active' => 'required|boolean',
            ]);

            // start transaction
            \DB::beginTransaction();

            // logo image upload if exists
            if ($request->hasFile('company_logo')) {
                $maxFileSize = $this->fileStorageService->getMaxFileSize();
                $request->validate([
                    'company_logo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:' . $maxFileSize,
                ]);
                $filePath = $this->fileStorageService->upload($request->file('company_logo'), 'company_logos')['file_path'];
                $validated['company_logo_url'] = $filePath;
            }

            // generate company code
            $validated['code'] = Company::generateCompanyCodeFromName($validated['name']);

            $validated['created_by'] = auth()->id();
            $company = Company::create($validated);

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'companies',
                $company->id,
                'Created Company: ' . $company->name,
                'Company "' . $company->name . '" has been created successfully.'
            );

            // commit transaction
            \DB::commit();

            return redirect()->route('master-data.companies.show', $company->id)->with('success', 'Company created successfully.');

        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            \Log::error('Error creating company: ' . $e->getMessage());
            return back()->withErrors('An error occurred while creating the company. Please try again.')->withInput();
        }


    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        // Show company details
        $company->load('users', 'documents', 'creator');
        // $company->logo_url = $company->company_logo_url ? $this->fileStorageService->getUrl($company->company_logo_url) : null;
        \Log::info('Company logo URL: ' . $company->company_logo_url);
        return view('companies.show', compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        // Show edit company form
        $company->load('creator');
        // from helper get countries
        $countries = get_countries();
        return view('companies.edit', compact('company', 'countries'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        // Prevent updating system default company
        if ($company->isSystemDefault()) {
            return back()->withErrors('The N/A system default company cannot be edited.');
        }

        // Validate and update company
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:companies,name,' . $company->id,
                'address' => 'nullable|string|max:1000',
                'city' => 'nullable|string|max:255',
                'state' => 'nullable|string|max:255',
                'country' => 'nullable|string|max:255',
                'postal_code' => 'nullable|string|max:20',
                'phone_number' => 'nullable|string|max:50',
                'website' => 'nullable|url|max:255',
                'industry' => 'nullable|string|max:255',
                'number_of_employees' => 'nullable|integer|min:0',
                'contact_person' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:50',
                'is_active' => 'required|boolean',
            ]);
            // start transaction
            \DB::beginTransaction();
            // logo image upload if exists
            if ($request->hasFile('company_logo')) {
                $maxFileSize = $this->fileStorageService->getMaxFileSize();
                $request->validate([
                    'company_logo' => 'image|mimes:jpeg,png,jpg,gif,svg|max:' . $maxFileSize,
                ]);
                // delete old logo if exists
                if ($company->company_logo_url) {
                    $this->fileStorageService->delete($company->company_logo_url);
                }
                $filePath = $this->fileStorageService->upload($request->file('company_logo'), 'company_logos')['file_path'];
                $validated['company_logo_url'] = $filePath;
            }
            $company->update($validated);
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'companies',
                $company->id,
                'Updated Company: ' . $company->name,
                'Company "' . $company->name . '" has been updated successfully.'
            );
            // commit transaction
            \DB::commit();
            
            // Redirect back to the companies index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.companies.index', ['page' => $page])->with('success', 'Company updated successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            \Log::error('Error updating company: ' . $e->getMessage());
            return back()->withErrors('An error occurred while updating the company. Please try again.')->withInput();
        }
    }

    public function downloadLogo($filename)
    {
        $disk = config('filesystems.default', 'local');
        $filePath = 'company_logos/' . $filename;
        \Log::info('Getting file from storage: ' . $filePath);
        if (!Storage::disk($disk)->exists($filePath)) {
            abort(404);
        }
        \Log::info('Company logo URL: ' . $filePath);
        return response()->file(storage_path('app/private/' . $filePath));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Company $company)
    {
        // Prevent deleting system default company
        if ($company->isSystemDefault()) {
            return back()->withErrors('The N/A system default company cannot be deleted.');
        }

        // Soft delete company
        try {
            // start transaction
            \DB::beginTransaction();
            $company->delete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'companies',
                $company->id,
                'Deleted Company: ' . $company->name,
                'Company "' . $company->name . '" has been deleted successfully.'
            );
            // commit transaction
            \DB::commit();
            
            // Redirect back to the companies index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('master-data.companies.index', ['page' => $page])->with('success', 'Company deleted successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            \Log::error('Error deleting company: ' . $e->getMessage());
            return back()->withErrors('An error occurred while deleting the company. Please try again.');
        }
    }

    /**
     * Force delete the specified resource from storage.
     */
    public function forceDelete(int $id)
    {
        // Delete company
        try {
            // make sure we only delete soft-deleted companies
            $company = Company::onlyTrashed()->findOrFail($id);
            // start transaction
            \DB::beginTransaction();
            // delete logo if exists
            if ($company->company_logo_url) {
                $this->fileStorageService->delete($company->company_logo_url);
            }
            $company_id = $company->id;
            $company_name = $company->name;
            // to vaoid cascade delete issues, first dissociate related users
            $company->users()->update(['company_id' => null]);
            // then dissociate related documents
            $company->documents()->update(['company_id' => null]);
            // then dissociate related activities
            $company->deleteActivities();
            // permanently delete company
            $company->forceDelete();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'companies',
                $company_id,
                'Deleted Company: ' . $company_name,
                'Company "' . $company_name . '" has been deleted successfully.'
            );
            // commit transaction
            \DB::commit();
            return redirect()->route('trashed-data.companies.index')->with('success', 'Company deleted successfully.');
        } catch (\Exception $e) {
            // rollback transaction
            \DB::rollBack();
            \Log::error('Error deleting company: ' . $e->getMessage());
            return back()->withErrors('An error occurred while deleting the company. Please try again.');
        }
    }

    /**
     * Listing of soft-deleted companies.
     */
    public function trashed()
    {
        // List trashed companies only
        $query = Company::onlyTrashed();

        // Search functionality (not case-sensitive)
        $searchTerm = trim(request('search', ''));
        if ($searchTerm !== '') {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                ->orWhere('code', 'like', '%' . $searchTerm . '%')
                ->orWhere('contact_person', 'like', '%' . $searchTerm . '%')
                ->orWhere('email', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter by country
        $country = request('country');
        if ($country !== null && $country !== '') {
            $query->where('country', $country);
        }

        // Filter by active status
        $isActive = request('is_active');
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', (int)$isActive);
        }

        // Sorting
        $sortField = request('sort_by');
        $sortOrder = request('sort_direction');
        $allowedSortFields = ['name', 'code', 'contact_person', 'email', 'phone', 'is_active', 'created_at', 'users_count', 'documents_count'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'asc';
        }
        $query->with('users', 'documents', 'creator')
            ->withCount('users', 'documents')->orderBy($sortField, $sortOrder);

        // \Log::info($query->toSql());
        // \Log::info($query->getBindings());
        // Pagination
        $companies = $query->paginate(15)->withQueryString();

        // filter options
        $countries = Company::select('country')->distinct()->pluck('country')->filter()->values();
        return view('companies.trashed', compact('companies', 'countries'));
    }

    /**
     * Restore a soft-deleted company.
     */
    public function restore($id)
    {
        try {
            $company = Company::onlyTrashed()->findOrFail($id);
            $company->restore();
            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'companies',
                $company->id,
                'Restored Company: ' . $company->name,
                'Company "' . $company->name . '" has been restored successfully.'
            );
            return redirect()->route('trashed-data.companies.index')->with('success', 'Company restored successfully.');
        } catch (\Exception $e) {
            \Log::error('Error restoring company: ' . $e->getMessage());
            return back()->withErrors('An error occurred while restoring the company. Please try again.');
        }
    }

    /**
     * Generate company code (access staticly if needed)
     */
    public static function generateCompanyCodeFromName($name): string
    {
        $baseCode = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(substr($name, 0, 3)));
        $code = $baseCode . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        // check if code is unique
        while (Company::where('code', $code)->exists()) {
            $code = $baseCode . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }
}

// zip -r primex-markets.zip primex-markets
// unzip primex-markets.zip -d tradera
