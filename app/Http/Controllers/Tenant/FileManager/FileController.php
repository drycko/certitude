<?php

namespace App\Http\Controllers\Tenant\FileManager;

use App\Http\Controllers\Controller;
use App\Models\Tenant\File;
use App\Models\Tenant\Company;
use App\Models\Tenant\FileType;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\Fbo;
use App\Models\Tenant\Role;
use App\Models\Tenant\Grower;
use App\Models\Tenant\Variety;
use App\Services\Tenant\FileStorageService;
use App\Services\Tenant\UserAccessService;
use App\Services\Tenant\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    protected FileStorageService $fileStorageService;
    protected UserAccessService $userAccessService;
    protected NotificationService $notificationService;
    // Log user activities and notifications
    use \App\Traits\LogsUserActivity;

    public function __construct(
        FileStorageService $fileStorageService,
        UserAccessService $userAccessService,
        NotificationService $notificationService
    ) {
        $this->fileStorageService = $fileStorageService;
        $this->userAccessService = $userAccessService;
        $this->notificationService = $notificationService;
    }

    /**
     * Display Files list
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters
        $filters = $request->only([
            'page',
            'search',
            'file_type_id',
            'sub_file_type_id',
            'commodity_id',
            'grower_id',
            'fbo_id',
            'variety_id',
            'vessel_name',
            'container_number',
            'expiry_status',
            'sort_by',
            'sort_direction'
        ]);
        // filter to set the number of items per page
        $filters['per_page'] = $request->input('per_page', 15);
        // if all
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = Files::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }

        // get commodity_id from request and convert to int
        if (isset($filters['commodity_id'])) {
            $filters['commodity_id'] = (int) $filters['commodity_id'];
        }

        // Get filtered files
        $files = $this->userAccessService
            ->getFilteredFiles($user, $filters)
            ->paginate($filters['per_page']);

        // Get filter options
        // $fileTypes = FileType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        // $subFileTypes = FileType::where('is_active', true)->whereNotNull('parent_id')->orderBy('name')->get();

        // accessible file types
        $fileTypes = $this->userAccessService->getAccessibleFileTypes($user, false)->orderBy('name')->get();
        // accessible sub file types
        $subFileTypes = $this->userAccessService->getAccessibleFileTypes($user, true)->orderBy('name')->get();

        // accessible fbos, comodities and growers
        // $commodities = $this->userAccessService->getAccessibleCommoditiesForUser($user)->orderBy('sort_order')->orderBy('name')->get();
        $commodities = $this->userAccessService->getAccessibleCommodities($user);
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
        $growers = $this->userAccessService->getAccessibleGrowers($user)->orderBy('name')->get();
        $varieties = Variety::where('is_active', true)->orderBy('name')->get();
        
        // vessels from 
        $vessels = File::getDistinctVesselNames();

        $roles = Role::with('permissions')
        ->withCount('users')
        ->orderBy('name')
        ->get();

        // get commodity name for breadcrumb if filter applied
        if (isset($filters['commodity_id']) && !empty($filters['commodity_id'])) {
            $commodity = Commodity::find($filters['commodity_id']);
            if ($commodity) {
                $filters['commodity_name'] = $commodity->name;
            }
        }

        // Get recent files (last 8 uploaded files)
        $recentFiles = $this->userAccessService
            ->getFilteredFiles($user, [])
            ->latest('created_at')
            ->limit(8)
            ->get();

        return view('tenant.file.index', compact(
            'files',
            'fileTypes',
            'subFileTypes',
            'commodities',
            'fbos',
            'growers',
            'varieties',
            'vessels',
            'filters',
            'roles',
            'recentFiles'
        ));
    }

    /**
     * Show upload form
     */
    public function create()
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canUploadFiles($user)) {
            abort(403, 'You do not have permission to upload files.');
        }

        try {
            $maxFileSize = $this->fileStorageService->getMaxFileSize();
            $allowedFileExtensions = $this->fileStorageService->getAllowedFileExtensions();
            // Check if user has commodities assigned
            $fileTypes = FileType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
            $subFileTypes = FileType::where('is_active', true)
                ->where('parent_id', '!=', null)
                ->orderBy('name')
                ->get();
            
            $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
            $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
            $companies = \App\Models\Tenant\Company::where('is_active', true)->orderBy('name')->get();
            $growers = Grower::where('is_active', true)->orderBy('name')->get();
            $varieties = Variety::where('is_active', true)->orderBy('name')->get();
            
            // get grower number from user table
            $userGrowerNumber = $user->grower_number;
            $defaultGrowerId = null;

            // if user is not an admin, filter the growers to only his/her own record
            if (!$user->hasRole('admin') && !$user->hasRole('super-user') && $userGrowerNumber) {
                // get all growers matching the user grower number (and only send those to the view in place of all growers)
                $growers = $user->growers()->where('is_active', true)->orderBy('name')->get();
                $userDefaultGrower = $growers->firstWhere('grower_number', $userGrowerNumber);
                $defaultGrowerId = $userDefaultGrower ? $userDefaultGrower->id : null;
                // also get the company of this user
                $companies = \App\Models\Tenant\Company::where('is_active', true)
                    ->where('id', $user->company_id)
                    ->orderBy('name')
                    ->get();
            }

            // log activity
            $this->logUserActivity(
                'view',
                'files',
                null,
                'Accessed file upload form'
            );

            return view('tenant.file.create', compact('fileTypes', 'subFileTypes', 'commodities', 'fbos', 'companies', 'maxFileSize', 'allowedFileExtensions', 'growers', 'defaultGrowerId', 'varieties'));

        } catch (\Exception $e) {
            \Log::error('Error loading file upload form: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to load upload form. Please try again.']);
        }
    }

    /**
     * Store uploaded file
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canUploadFiles($user)) {
            abort(403, 'You do not have permission to upload files.');
        }


        $maxFileSize = $this->fileStorageService->getMaxFileSize();
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'file_type_id' => 'required|exists:file_types,id',
                'sub_file_type_id' => 'nullable|exists:file_types,id',
                'company_id' => 'nullable|exists:companies,id',
                'fbos' => 'nullable|array',
                'fbos.*' => 'string', // FBO code
                'commodities' => 'required|array|min:1',
                'commodities.*' => 'exists:commodities,id',
                'file' => 'required|file|max:' . $maxFileSize, // 15MB max
                'is_public' => 'boolean',
                'expiry_date' => 'nullable|date|after:today',
                'season_year' => 'nullable|integer|min:2018|max:2050',
                'description' => 'nullable|string|max:1000',
                'varieties' => 'nullable|array',
                'varieties.*' => 'exists:varieties,id',
                // 'vessels' => 'nullable|array',
                // 'vessels.*' => 'exists:vessels,id',
                'container_number' => 'nullable|string|max:255',
                'quality_ref_number' => 'nullable|string|max:255',
                'quality_rating' => 'nullable|in:Sound,Unsound',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // If file is missing (possibly due to PHP upload_max_filesize/post_max_size), show a friendly error
            if (!$request->hasFile('file') || !$request->file('file')) {
                return back()->withErrors(['file' => 'File is missing or too large. Please select a file smaller than 15MB.'])->withInput();
            }
            throw $e;
        }

        // Validate file types
        $file = $request->file('file');
        if (!$file) {
            return back()->withErrors(['file' => 'File is missing or too large. Please select a file smaller than 15MB.'])->withInput();
        }
        if (!$this->fileStorageService->validateFileType($file)) {
            return back()->withErrors(['file' => 'Only PDF, JPG, Excel, and ZIP files are allowed.'])->withInput();
        }
        // Check if file size exceeds maximum limit (double check)
        if ($file->getSize() > $maxFileSize) {
            return back()->withErrors(['file' => 'File size exceeds the maximum limit of 15MB.'])->withInput();
        }

        // find the file type
        $fileType = FileType::find($request->file_type_id);
        $hasGrowerAttributeType = $fileType && $fileType->attribute_type == 'grower';
        $hasGrower = $request->grower_id ? true : false;

        // If file_type with grower attribute is selected and user is not grower, force private
        if ($hasGrowerAttributeType || $hasGrower) {
            $request->validate([
                'grower_id' => 'nullable|exists:growers,id',
            ]);
            if (!$user->hasRole('grower')) {
                $request->is_public = false;
            }
        }

        // make sure FBOs and grower number is required if file type is grower and private
        if ($hasGrowerAttributeType && !$request->boolean('is_public', false)) {
            if (empty($request->fbos) || count($request->fbos) == 0) {
                return back()->withErrors(['fbos' => 'At least one FBO must be selected for private file with Grower attribute type.'])->withInput();
            }
            if (empty($request->grower_id)) {
                return back()->withErrors(['grower_id' => 'Grower is required for file with Grower attribute type.'])->withInput();
            }
        }

        // make sure at least 1 commodity is selected if file type is grower and public
        if ($hasGrowerAttributeType && $request->boolean('is_public', false)) {
            if (empty($request->commodities) || count($request->commodities) == 0) {
                return back()->withErrors(['commodities' => 'At least one commodity must be selected for public file with Grower attribute type.'])->withInput();
            }
        }

        //If file_type with customer attribute is selected,
        if ($fileType && $fileType->attribute_type == 'customer') {
            // make sure at least one commodity is selected
            if (empty($request->commodities) || count($request->commodities) == 0) {
                return back()->withErrors(['commodities' => 'At least one commodity must be selected for file with Customer attribute type.'])->withInput();
            }
            // make sure at least one FBO is selected
            if (empty($request->fbos) || count($request->fbos) == 0) {
                return back()->withErrors(['fbos' => 'At least one FBO must be selected for file with Customer attribute type.'])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $files = [];
            $directory = 'files';
            $fileData = $this->fileStorageService->upload($file, $directory);

            $metadata = $hasGrower ? ['grower_id' => $request->grower_id] : [];
            
            // Add vessel_name to metadata
            if ($request->has('vessel_name') && !empty($request->vessel_name)) {
                $metadata['vessel_name'] = $request->vessel_name;
            }

            $file = File::create([
                'user_id' => $user->id,
                'company_id' => $request->company_id ?? $user->company_id,
                'file_type_id' => $request->file_type_id,
                'sub_file_type_id' => $request->sub_file_type_id,
                'title' => $request->title,
                'filename' => $fileData['filename'],
                'original_filename' => $fileData['original_filename'],
                'file_path' => $fileData['file_path'],
                'file_size' => $fileData['file_size'],
                'mime_type' => $fileData['mime_type'],
                'is_public' => $request->boolean('is_public', false),
                'expiry_date' => $request->expiry_date,
                'season_year' => $request->season_year,
                'description' => $request->description,
                'uploaded_by' => $user->id,
                'is_active' => true,
                'metadata' => $metadata,
                'container_number' => $request->container_number,
                'quality_ref_number' => $request->quality_ref_number,
                'quality_rating' => $request->quality_rating,
            ]);

            // Attach commodities
            $file->commodities()->attach($request->commodities);

            // Attach FBOs by code
            if ($request->has('fbos') && !empty($request->fbos)) {
                // Find FBO IDs by code
                $fboIds = Fbo::whereIn('code', $request->fbos)->pluck('id')->toArray();
                if (!empty($fboIds)) {
                    $file->fbos()->attach($fboIds);
                }
            }

            // Attach varieties
            if ($request->has('varieties') && !empty($request->varieties)) {
                $file->varieties()->attach($request->varieties);
            }

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'files',
                $file->id,
                'Uploaded file: ' . $file->title,
                'file "' . $file->title . '" uploaded successfully.'
            );

            $files[] = $file;
            DB::commit();

            foreach ($files as $file) {
                $this->notificationService->sendFileUploadNotification($file, $user);
            }

            $message = count($files) === 1
                ? 'file uploaded successfully.'
                : count($files) . ' file uploaded successfully.';

            return redirect()->route('files.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error uploading file: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to upload files. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show file details
     */
    public function show(Request $request, File $file)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canViewFile($user, $file)) {
            abort(403, 'You do not have permission to view this file.');
        }

        // log activity
        $this->logUserActivity(
            'view',
            'files',
            $file->id,
            'Viewed file: ' . $file->title
        );

        // activity stats will be loaded in the view
        $file->views_count = $file->getViewsCount();
        $file->downloads_count = $file->getDownloadsCount();
        $file->updates_count = $file->getUpdatesCount();
        $file->last_accessed_at = $file->getLastAccessedAt();

        // Get filter parameters from request
        $filters = $request->only([
            'search',
            'file_type_id',
            'sub_file_type_id',
            'commodity_id',
            'grower_id',
            'fbo_id',
            'variety_id',
            'vessel_name',
            'container_number'
        ]);

        // Get all accessible file with filters for navigation
        $query = $this->userAccessService->getFilteredFiles($user, $filters);
        
        // Get all file IDs in filtered order
        $fileIds = $query->pluck('id')->toArray();
        $currentIndex = array_search($file->id, $fileIds);
        
        // Get previous and next file IDs
        $previousFileId = $currentIndex > 0 ? $fileIds[$currentIndex - 1] : null;
        $nextFileId = $currentIndex < count($fileIds) - 1 ? $fileIds[$currentIndex + 1] : null;

        $allFiles = $query->get();

        // Get filter options
        // $fileTypes = FileType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        // $subFileTypes = FileType::where('is_active', true)->whereNotNull('parent_id')->orderBy('name')->get();
        // $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        // $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        // $fbos = $this->userAccessService->getAccessibleFbos(Auth::user())->orderBy('code')->get();
        // $varieties = Variety::where('is_active', true)->orderBy('name')->get();

        // accessible file types
        $fileTypes = $this->userAccessService->getAccessibleFileTypes($user, false)->orderBy('name')->get();
        // accessible sub file types
        $subFileTypes = $this->userAccessService->getAccessibleFileTypes($user, true)->orderBy('name')->get();

        // accessible fbos, comodities and growers
        $commodities = $this->userAccessService->getAccessibleCommoditiesForUser($user)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
        $growers = $this->userAccessService->getAccessibleGrowers($user)->orderBy('name')->get();
        $varieties = Variety::where('is_active', true)->orderBy('name')->get();
        // vessels from 
        $vessels = File::getDistinctVesselNames();

        return view('files.show', compact(
            'file',
            'filters',
            'fileTypes',
            'subFileTypes',
            'commodities',
            'growers',
            'fbos',
            'varieties',
            'vessels',
            'allFiles',
            'previousFileId',
            'nextFileId'
        ));
    }

    /**
     * Show edit form
     */
    public function edit(File $file)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canEditFile($user, $file)) {
            abort(403, 'You do not have permission to edit this file.');
        }

        $fileTypes = FileType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        $subFileTypes = FileType::where('is_active', true)
            ->where('parent_id', '!=', null)
            ->orderBy('name')
            ->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = Fbo::where('is_active', true)->orderBy('name')->get();
        $companies = \App\Models\Tenant\Company::where('is_active', true)->orderBy('name')->get();
        $growers = Grower::where('is_active', true)->orderBy('name')->get();
        $varieties = Variety::where('is_active', true)->orderBy('name')->get();
        // $vessels = Vessel::where('is_active', true)->orderBy('name')->get();
        // get grower number from user table
        $userGrowerNumber = $user->grower_number;
        $defaultGrowerId = null;

        // if user is not an admin, filter the growers to only his/her own record
        if (!$user->hasRole('admin') && !$user->hasRole('super-user') && $userGrowerNumber) {
            // get all growers matching the user grower number (and only send those to the view in place of all growers)
            $growers = $user->growers()->where('is_active', true)->orderBy('name')->get();
            $userDefaultGrower = $growers->firstWhere('grower_number', $userGrowerNumber);
            $defaultGrowerId = $userDefaultGrower ? $userDefaultGrower->id : null;
            // also get the company of this user
            $companies = \App\Models\Tenant\Company::where('is_active', true)
                ->where('id', $user->company_id)
                ->orderBy('name')
                ->get();

        }
        $file->grower_id = $file->grower()->id ?? null;

        $fileExpiryDate = $file->expiry_date ? $file->expiry_date->format('Y-m-d') : null;

        $this->logUserActivity(
            'view',
            'files',
            $file->id,
            'Accessed edit form for file: ' . $file->title
        );

        return view('files.edit', compact(
            'file', 
            'fileTypes', 
            'subFileTypes', 
            'commodities', 
            'fbos',
            'companies',
            'growers',
            'varieties',
            // 'vessels',
            'fileExpiryDate'
        ));
    }

    /**
     * Update file
     */
    public function update(Request $request, File $file)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canEditFile($user, $file)) {
            abort(403, 'You do not have permission to edit this file.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'nullable|file|max:51200|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip', // 50MB max
            'file_type_id' => 'required|exists:file_types,id',
            'sub_file_type_id' => 'nullable|exists:file_types,id',
            'company_id' => 'nullable|exists:companies,id',
            'fbos' => 'nullable|array',
            'fbos.*' => 'exists:fbos,code', // FBO code
            'commodities' => 'required|array|min:1',
            'commodities.*' => 'exists:commodities,id',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'expiry_date' => 'nullable|date',
            'season_year' => 'nullable|integer|min:2020|max:2050',
            'description' => 'nullable|string|max:1000',
            'varieties' => 'nullable|array',
            'varieties.*' => 'exists:varieties,id',
            'vessel_name' => 'nullable|string|max:255',
            'container_number' => 'nullable|string|max:255',
            'quality_ref_number' => 'nullable|string|max:255',
            'quality_rating' => 'nullable|in:Sound,Unsound',
        ]);

        DB::beginTransaction();
        
        try {
            // Handle file replacement (Admin only)
            if ($request->hasFile('file') && ($user->hasRole('admin') || $user->hasRole('super-user'))) {
                $uploadedFile = $request->file('file');
                
                // Validate file type
                if (!$this->fileStorageService->validateFileType($uploadedFile)) {
                    return back()->withErrors(['file' => 'Only PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, and ZIP files are allowed.'])->withInput();
                }
                
                // Delete old file from storage
                if ($this->fileStorageService->exists($file->file_path)) {
                    $this->fileStorageService->delete($file->file_path);
                }
                
                // Upload new file (same as store method)
                $fileData = $this->fileStorageService->upload($uploadedFile, 'files');
                
                // Update file-related fields
                $file->filename = $fileData['filename'];
                $file->original_filename = $fileData['original_filename'];
                $file->file_path = $fileData['file_path'];
                $file->file_size = $fileData['file_size'];
                $file->mime_type = $fileData['mime_type'];
                // $file->file_extension = $uploadedFile->getClientOriginalExtension();
                
                // Log file replacement
                $this->logUserActivity(
                    'replace_file',
                    'files',
                    $file->id,
                    'Replaced file for file: ' . $file->title . ' (New: ' . $fileData['original_filename'] . ')'
                );
            }
            
            $fileMetadata = $file->metadata;
            if ($request->grower_id || $file->grower() ?? null) { // TypeError\n Cannot access offset of type string on string // Why?? 
                $request->validate([
                    'grower_id' => 'nullable|exists:growers,id',
                ]);

                $growerId = $file->grower()->id ?? null;
                if ($request->has('grower_id')) {
                    $growerId = $request->grower_id;
                    $fileMetadata['grower_id'] = $growerId;
                }
            }
            
            // Update vessel_name in metadata
            if ($request->has('vessel_name')) {
                if (!empty($request->vessel_name)) {
                    $fileMetadata['vessel_name'] = $request->vessel_name;
                } else {
                    unset($fileMetadata['vessel_name']);
                }
            }

            $updateData = [
                'title' => $request->title,
                'file_type_id' => $request->file_type_id,
                'sub_file_type_id' => $request->sub_file_type_id,
                'company_id' => $request->company_id ?? $user->company_id,
                'is_public' => $request->boolean('is_public', false),
                'is_active' => $request->boolean('is_active', true),
                'expiry_date' => $request->expiry_date,
                'season_year' => $request->season_year,
                'description' => $request->description,
                'metadata' => $fileMetadata,
                'container_number' => $request->container_number,
                'quality_ref_number' => $request->quality_ref_number,
                'quality_rating' => $request->quality_rating,
            ];

            // Update file
            $file->update($updateData);

            // Sync commodities
            $file->commodities()->sync($request->commodities);
            
            // sync FBOs by code
            if ($request->has('fbos') && !empty($request->fbos)) {
                // Find FBO IDs by code
                $fboIds = Fbo::whereIn('code', $request->fbos)->pluck('id')->toArray();
                if (!empty($fboIds)) {
                    $file->fbos()->sync($fboIds);
                }
            } else {
                $file->fbos()->sync([]);
            }

            // Sync varieties
            if ($request->has('varieties') && !empty($request->varieties)) {
                $file->varieties()->sync($request->varieties);
            } else {
                $file->varieties()->sync([]);
            }

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'files',
                $file->id,
                'Updated file: ' . $file->title,
                'file "' . $file->title . '" updated successfully.'
            );
            
            DB::commit();

            // Redirect back to the file index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('files.index', ['page' => $page])
                ->with('success', 'file updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to update file. Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Download file
     */
    public function download(File $file)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canViewFile($user, $file)) {
            abort(403, 'You do not have permission to download this file.');
        }

        if (!$this->fileStorageService->exists($file->file_path)) {
            abort(404, 'File not found.');
        }

        $fileContents = $this->fileStorageService->get($file->file_path);

        // Log download activity
        $this->logUserActivity(
            'download',
            'files',
            $file->id,
            'Downloaded file: ' . $file->title
        );
        
        return response($fileContents)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . $file->original_filename . '"');
    }

    /**
     * Stream PDF for preview (inline)
     */
    public function preview(File $file)
    {
        $user = Auth::user();
        if (!$this->userAccessService->canViewFile($user, $file)) {
            abort(403, 'You do not have permission to preview this file.');
        }
        if (!$this->fileStorageService->exists($file->file_path)) {
            abort(404, 'File not found.');
        }
        $fileContents = $this->fileStorageService->get($file->file_path);
        // Log preview activity
        $this->logUserActivity(
            'preview',
            'files',
            $file->id,
            'Previewed file: ' . $file->title
        );
        return response($fileContents)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . $file->original_filename . '"');
    }

    public function destroy(File $file)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canDeleteFile($user, $file)) {
            abort(403, 'You do not have permission to delete this file.');
        }

        DB::beginTransaction();
        
        try {
            // Delete file from storage
            $this->fileStorageService->delete($file->file_path);
            
            // Delete database record
            $file->delete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'files',
                $file->id,
                'Deleted file: ' . $file->title,
                'file "' . $file->title . '" deleted successfully.'
            );
            
            DB::commit();

            // If AJAX/JSON, return JSON
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'success', 'message' => 'file deleted successfully.']);
            }

            // Otherwise, classic redirect
            return redirect()->route('files.index')
                ->with('success', 'file deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            // If AJAX/JSON, return JSON error
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => 'Failed to delete file. Please try again.'], 500);
            }
            
            return back()->withErrors(['error' => 'Failed to delete file. Please try again.']);
        }
    }

    /**
     * display trashed file listing
     */
    public function trashed()
    {
        $user = Auth::user();
        $request = request();
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to access trashed files.');
        }

        // Get filter and sort parameters as sent from Blade
        $filters = $request->only([
            'search',
            'file_type_id',
            'sub_file_type_id',
            'commodity_id',
            'grower_id',
            'fbo_id',
            'variety_id',
            'vessel_name',
            'container_number',
            'expiry_status',
            'sort_by',
            'sort_order',
        ]);
        $filters['per_page'] = $request->input('per_page', 15);
        if ($filters['per_page'] == -1) {
            $filters['per_page'] = File::onlyTrashed()->count();
            $request->query->remove('page');
        }

        // Build trashed file query
        $query = File::onlyTrashed()->with([
            'fileType',
            'subFileType',
            'company',
            'fbos',
            'uploadedBy',
            'commodities'
        ]);

        // Filtering
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if (!empty($filters['file_type_id'])) {
            $query->where('file_type_id', $filters['file_type_id']);
        }
        if (!empty($filters['sub_file_type_id'])) {
            $query->where('sub_file_type_id', $filters['sub_file_type_id']);
        }
        if (!empty($filters['commodity_id'])) {
            $query->whereHas('commodities', function ($q) use ($filters) {
                $q->where('commodities.id', $filters['commodity_id']);
            });
        }
        if (!empty($filters['grower_id'])) {
            $query->where('grower_id', $filters['grower_id']);
        }
        if (!empty($filters['fbo_id'])) {
            $query->whereHas('fbos', function ($q) use ($filters) {
                $q->where('fbos.id', $filters['fbo_id']);
            });
        }
        if (!empty($filters['variety_id'])) {
            $query->whereHas('varieties', function ($q) use ($filters) {
                $q->where('varieties.id', $filters['variety_id']);
            });
        }
        if (!empty($filters['vessel_name'])) {
            $query->where('metadata->vessel_name', $filters['vessel_name']);
        }
        if (!empty($filters['container_number'])) {
            $query->where('container_number', $filters['container_number']);
        }
        if (!empty($filters['expiry_status'])) {
            if ($filters['expiry_status'] === 'expired') {
                $query->whereNotNull('expiry_date')->where('expiry_date', '<', now());
            } elseif ($filters['expiry_status'] === 'expiring') {
                $query->whereNotNull('expiry_date')->whereBetween('expiry_date', [now(), now()->addDays(30)]);
            } elseif ($filters['expiry_status'] === 'active') {
                $query->where(function ($q) {
                    $q->whereNull('expiry_date')->orWhere('expiry_date', '>', now()->addDays(30));
                });
            }
        }

        // Sorting
        $sortField = $filters['sort_by'] ?? 'deleted_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSortFields = ['title', 'file_type_id', 'deleted_at', 'created_at', 'expiry_date'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('deleted_at', 'desc');
        }

        $files = $query->paginate($filters['per_page'])->withQueryString();

        // Calculate stats
        $stats = [
            'total' => File::onlyTrashed()->count(),
        ];

        // Get filter options
        $fileTypes = FileType::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();

        // log activity
        $this->logUserActivity(
            'view',
            'files',
            null,
            'Accessed trashed file listing'
        );

        return view('files.trashed', compact('files', 'stats', 'fileTypes', 'commodities', 'fbos', 'filters'));
    }

    /**
     * Restore trashed file
     */
    public function restore($id)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to restore trashed files.');
        }

        $file = File::onlyTrashed()->find($id);
        if (!$file) {
            return back()->withErrors(['error' => 'file not found or not in trash.']);
        }

        DB::beginTransaction();
        
        try {
            $file->restore();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'files',
                $file->id,
                'Restored file: ' . $file->title,
                'file "' . $file->title . '" restored successfully.'
            );
            
            DB::commit();
            
            return redirect()->route('files.show', $file)
                ->with('success', 'file restored successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors(['error' => 'Failed to restore file. Please try again.']);
        }
    }

    /**
     * Permanently delete trashed file
     */
    public function forceDelete($id)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to permanently delete trashed files.');
        }

        $file = File::onlyTrashed()->find($id);
        if (!$file) {
            return back()->withErrors(['error' => 'file not found or not in trash.']);
        }

        DB::beginTransaction();
        
        try {
            // Delete file from storage
            $this->fileStorageService->delete($file->file_path);
            
            // Permanently delete database record
            $file->forceDelete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'files',
                $id,
                'Permanently deleted file: ' . $file->title,
                'file "' . $file->title . '" permanently deleted.'
            );
            
            DB::commit();
            
            return redirect()->route('trashed-data.file.index')
                ->with('success', 'file permanently deleted.');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error permanently deleting file ID ' . $id . ': ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to permanently delete file. Please try again.']);
        }
    }

    /**
     * Batch download files
     */
    public function batchDownload(Request $request)
    {
        $user = Auth::user();
        $fileIds = $request->input('file_ids', []);
        
        if (empty($fileIds)) {
            return back()->withErrors(['error' => 'No file selected.']);
        }

        // Get file user can access
        $files = $this->userAccessService
            ->getAccessibleFiles($user)
            ->whereIn('id', $fileIds)
            ->get();

        if ($files->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible file found.']);
        }

        // Create ZIP file (simplified version - in production, use a proper ZIP library)
        $zipName = 'files_' . now()->format('Y-m-d_H-i-s') . '.zip';

        // Create ZIP file
        $zip = new \ZipArchive();
        if ($zip->open(public_path($zipName), \ZipArchive::CREATE) === TRUE) {
            foreach ($files as $file) {
                // log activity for each file
                $this->logUserActivity(
                    'download',
                    'files',
                    $file->id,
                    'Included in batch download: ' . $file->title
                );
                    // Add each file to the ZIP using the actual file path
                    if ($this->fileStorageService->exists($file->file_path)) {
                        // Get the absolute local path for the file
                        $localPath = Storage::path($file->file_path);
                        $zip->addFile($localPath, $file->original_filename);
                    }
            }
            $zip->close();
        }
        else {
            return back()->withErrors(['error' => 'Failed to create ZIP file. Please try again.']);
        }

        // Log download activity
        $this->logUserActivity(
            'download',
            'files',
            null,
            'Downloaded zip of multiple files. IDs: [' . implode(', ', $files->pluck('id')->toArray()) . ']'
        );

        // Return ZIP file as download
        return response()->download(public_path($zipName))->deleteFileAfterSend(true);

        // For now, return first file (implement proper ZIP creation later)
        // return $this->download($files->first());
    }

    /**
     * Bulk restore all trashed files
     */
    public function bulkRestoreAll()
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to restore trashed files.');
        }

        $files = File::onlyTrashed()->get();
        
        if ($files->isEmpty()) {
            return back()->withErrors(['error' => 'No trashed file found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($files as $file) {
                $file->restore();
                $count++;
                
                // log activity
                $this->logUserActivity(
                    'restore',
                    'files',
                    $file->id,
                    'Restored file: ' . $file->title
                );
            }
            
            DB::commit();
            
            return redirect()->route('trashed-data.file.index')
                ->with('success', "{$count} file(s) restored successfully.");

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error bulk restoring files: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to restore files. Please try again.']);
        }
    }

    /**
     * Bulk force delete all trashed files
     */
    public function bulkForceDeleteAll()
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to permanently delete trashed files.');
        }

        $files = File::onlyTrashed()->get();
        
        if ($files->isEmpty()) {
            return back()->withErrors(['error' => 'No trashed file found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($files as $file) {
                // Delete file from storage
                if ($file->file_path && $this->fileStorageService->exists($file->file_path)) {
                    $this->fileStorageService->delete($file->file_path);
                }
                
                // Permanently delete database record
                $file->forceDelete();
                $count++;
                
                // log activity
                $this->logUserActivity(
                    'force_delete',
                    'files',
                    $file->id,
                    'Permanently deleted file: ' . $file->title
                );
            }
            
            DB::commit();
            
            // Reset auto-increment
            DB::statement('ALTER TABLE file AUTO_INCREMENT = 1');
            
            return redirect()->route('trashed-data.file.index')
                ->with('success', "{$count} file(s) permanently deleted. Auto-increment reset.");

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error bulk force deleting files: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to permanently delete files. Please try again.']);
        }
    }

    /**
     * Show bulk management page (admin only)
     */
    public function bulk()
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to access bulk management.');
        }

        // Get all file with relationships
        $files = File::with([
            'fileType', 
            'subFileType', 
            'company', 
            'fbos', 
            'uploadedBy',
            'commodities'
        ])->orderBy('created_at', 'desc')->paginate(50);

        // Calculate stats
        $stats = [
            'total' => File::count(),
            'active' => File::where('is_active', true)->count(),
            'expiring' => File::whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>', now())
                ->count(),
            'expired' => File::whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now())
                ->count(),
        ];

        // filter options
        $fileTypes = FileType::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        // log activity
        $this->logUserActivity(
            'view',
            'files',
            null,
            'Accessed bulk file management page'
        );

        return view('files.bulk', compact('files', 'stats', 'fileTypes', 'commodities', 'fbos', 'companies'));
    }

    /**
     * Bulk activate files
     */
    public function bulkActivate(Request $request)
    {
        return $this->executeBulkAction($request, 'activate');
    }

    /**
     * Bulk deactivate files
     */
    public function bulkDeactivate(Request $request)
    {
        return $this->executeBulkAction($request, 'deactivate');
    }

    /**
     * Bulk make public
     */
    public function bulkMakePublic(Request $request)
    {
        return $this->executeBulkAction($request, 'make_public');
    }

    /**
     * Bulk make private
     */
    public function bulkMakePrivate(Request $request)
    {
        return $this->executeBulkAction($request, 'make_private');
    }

    /**
     * Bulk set expiry date
     */
    public function bulkSetExpiry(Request $request)
    {
        $request->validate([
            'expiry_date' => 'required|date|after:today',
        ]);

        return $this->executeBulkAction($request, 'set_expiry');
    }

    /**
     * Bulk delete files
     */
    public function bulkDelete(Request $request)
    {
        return $this->executeBulkAction($request, 'delete');
    }

    /**
     * Execute bulk action
     */
    private function executeBulkAction(Request $request, string $action)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to perform bulk operations.');
        }

        $fileIds = $request->input('file_ids', []);
        
        if (empty($fileIds)) {
            return back()->withErrors(['error' => 'No file selected.']);
        }

        $files = File::whereIn('id', $fileIds)->get();
        
        if ($files->isEmpty()) {
            return back()->withErrors(['error' => 'No file found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($files as $file) {
                switch ($action) {
                    case 'activate':
                        $file->update(['is_active' => true]);
                        $count++;
                        break;
                        
                    case 'deactivate':
                        $file->update(['is_active' => false]);
                        $count++;
                        break;
                        
                    case 'make_public':
                        $file->update(['is_public' => true]);
                        $count++;
                        break;
                        
                    case 'make_private':
                        $file->update(['is_public' => false]);
                        $count++;
                        break;
                        
                    case 'set_expiry':
                        $file->update(['expiry_date' => $request->expiry_date]);
                        $count++;
                        break;
                        
                    case 'delete':
                        // Delete file from storage
                        if ($file->file_path && $this->fileStorageService->exists($file->file_path)) {
                            $this->fileStorageService->delete($file->file_path);
                        }
                        $file->delete();
                        $count++;
                        break;
                }
            }
            
            DB::commit();
            
            $actionLabels = [
                'activate' => 'activated',
                'deactivate' => 'deactivated',
                'make_public' => 'made public',
                'make_private' => 'made private',
                'set_expiry' => 'expiry date set for',
                'delete' => 'deleted',
            ];
            
            $message = "{$count} file(s) " . $actionLabels[$action] . " successfully.";

            // log action for each file
            foreach ($files as $file) {
                $this->logUserActivity(
                    $action,
                    'files',
                    $file->id,
                    ucfirst($action) . ' file: ' . $file->title
                );
            }
            
            
            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors(['error' => 'Failed to perform bulk action. Please try again.']);
        }
    }
}