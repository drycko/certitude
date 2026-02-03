<?php

namespace App\Http\Controllers\Tenant\FileManager;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Company;
use App\Models\Tenant\DocumentType;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\Fbo;
use App\Models\Tenant\Grower;
use App\Models\Tenant\Variety;
use App\Services\Tenant\FileStorageService;
use App\Services\Tenant\UserAccessService;
use App\Services\Tenant\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
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
     * Display documents list
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Get filter parameters
        $filters = $request->only([
            'page',
            'search',
            'document_type_id',
            'sub_document_type_id',
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
            $filters['per_page'] = Document::count();
            // remove page parameter to avoid issues
            $request->query->remove('page');
        }

        // get commodity_id from request and convert to int
        if (isset($filters['commodity_id'])) {
            $filters['commodity_id'] = (int) $filters['commodity_id'];
        }

        // Get filtered documents
        $documents = $this->userAccessService
            ->getFilteredDocuments($user, $filters)
            ->paginate($filters['per_page']);

        // Get filter options
        // $documentTypes = DocumentType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        // $subDocumentTypes = DocumentType::where('is_active', true)->whereNotNull('parent_id')->orderBy('name')->get();

        // accessible document types
        $documentTypes = $this->userAccessService->getAccessibleDocumentTypes($user, false)->orderBy('name')->get();
        // accessible sub document types
        $subDocumentTypes = $this->userAccessService->getAccessibleDocumentTypes($user, true)->orderBy('name')->get();

        // accessible fbos, comodities and growers
        // $commodities = $this->userAccessService->getAccessibleCommoditiesForUser($user)->orderBy('sort_order')->orderBy('name')->get();
        $commodities = $this->userAccessService->getAccessibleCommodities($user);
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
        $growers = $this->userAccessService->getAccessibleGrowers($user)->orderBy('name')->get();
        $varieties = Variety::where('is_active', true)->orderBy('name')->get();
        
        // vessels from 
        $vessels = Document::getDistinctVesselNames();

        // get commodity name for breadcrumb if filter applied
        if (isset($filters['commodity_id']) && !empty($filters['commodity_id'])) {
            $commodity = Commodity::find($filters['commodity_id']);
            if ($commodity) {
                $filters['commodity_name'] = $commodity->name;
            }
        }

        return view('tenant.documents.index', compact(
            'documents',
            'documentTypes',
            'subDocumentTypes',
            'commodities',
            'fbos',
            'growers',
            'varieties',
            'vessels',
            'filters'
        ));
    }

    /**
     * Show upload form
     */
    public function create()
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canUploadDocuments($user)) {
            abort(403, 'You do not have permission to upload documents.');
        }

        try {
            $maxFileSize = $this->fileStorageService->getMaxFileSize();
            $allowedFileExtensions = $this->fileStorageService->getAllowedFileExtensions();
            // Check if user has commodities assigned
            $documentTypes = DocumentType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
            $subDocumentTypes = DocumentType::where('is_active', true)
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
                'documents',
                null,
                'Accessed document upload form'
            );

            return view('tenant.documents.create', compact('documentTypes', 'subDocumentTypes', 'commodities', 'fbos', 'companies', 'maxFileSize', 'allowedFileExtensions', 'growers', 'defaultGrowerId', 'varieties'));

        } catch (\Exception $e) {
            \Log::error('Error loading document upload form: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to load upload form. Please try again.']);
        }
    }

    /**
     * Store uploaded document
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canUploadDocuments($user)) {
            abort(403, 'You do not have permission to upload documents.');
        }

        $maxFileSize = $this->fileStorageService->getMaxFileSize();

        $request->validate([
            'title' => 'required|string|max:255',
            'document_type_id' => 'required|exists:document_types,id',
            'sub_document_type_id' => 'nullable|exists:document_types,id',
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

        // Validate file types
        $file = $request->file('file');

        if (!$this->fileStorageService->validateFileType($file)) {
            return back()->withErrors(['file' => 'Only PDF, JPG, Excel, and ZIP files are allowed.'])->withInput();
        }

        // Check if file size exceeds maximum limit
        if ($file->getSize() > $maxFileSize) {
            return back()->withErrors(['file' => 'File size exceeds the maximum limit of 15MB.'])->withInput();
        }

        // find the document type
        $documentType = DocumentType::find($request->document_type_id);
        $hasGrowerAttributeType = $documentType && $documentType->attribute_type == 'grower';
        $hasGrower = $request->grower_id ? true : false;

        // If document_type with grower attribute is selected and user is not grower, force private
        if ($hasGrowerAttributeType || $hasGrower) {
            $request->validate([
                'grower_id' => 'nullable|exists:growers,id',
            ]);
            if (!$user->hasRole('grower')) {
                $request->is_public = false;
            }
        }

        // make sure FBOs and grower number is required if document type is grower and private
        if ($hasGrowerAttributeType && !$request->boolean('is_public', false)) {
            if (empty($request->fbos) || count($request->fbos) == 0) {
                return back()->withErrors(['fbos' => 'At least one FBO must be selected for private documents with Grower attribute type.'])->withInput();
            }
            if (empty($request->grower_id)) {
                return back()->withErrors(['grower_id' => 'Grower is required for documents with Grower attribute type.'])->withInput();
            }
        }

        // make sure at least 1 commodity is selected if document type is grower and public
        if ($hasGrowerAttributeType && $request->boolean('is_public', false)) {
            if (empty($request->commodities) || count($request->commodities) == 0) {
                return back()->withErrors(['commodities' => 'At least one commodity must be selected for public documents with Grower attribute type.'])->withInput();
            }
        }

        //If document_type with customer attribute is selected,
        if ($documentType && $documentType->attribute_type == 'customer') {
            // make sure at least one commodity is selected
            if (empty($request->commodities) || count($request->commodities) == 0) {
                return back()->withErrors(['commodities' => 'At least one commodity must be selected for documents with Customer attribute type.'])->withInput();
            }
            // make sure at least one FBO is selected
            if (empty($request->fbos) || count($request->fbos) == 0) {
                return back()->withErrors(['fbos' => 'At least one FBO must be selected for documents with Customer attribute type.'])->withInput();
            }
        }

        DB::beginTransaction();
        try {
            $documents = [];
            $fileData = $this->fileStorageService->upload($file, 'documents');

            $metadata = $hasGrower ? ['grower_id' => $request->grower_id] : [];
            
            // Add vessel_name to metadata
            if ($request->has('vessel_name') && !empty($request->vessel_name)) {
                $metadata['vessel_name'] = $request->vessel_name;
            }

            $document = Document::create([
                'user_id' => $user->id,
                'company_id' => $request->company_id ?? $user->company_id,
                'document_type_id' => $request->document_type_id,
                'sub_document_type_id' => $request->sub_document_type_id,
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
            $document->commodities()->attach($request->commodities);

            // Attach FBOs by code
            if ($request->has('fbos') && !empty($request->fbos)) {
                // Find FBO IDs by code
                $fboIds = Fbo::whereIn('code', $request->fbos)->pluck('id')->toArray();
                if (!empty($fboIds)) {
                    $document->fbos()->attach($fboIds);
                }
            }

            // Attach varieties
            if ($request->has('varieties') && !empty($request->varieties)) {
                $document->varieties()->attach($request->varieties);
            }

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'create',
                'documents',
                $document->id,
                'Uploaded document: ' . $document->title,
                'Document "' . $document->title . '" uploaded successfully.'
            );

            $documents[] = $document;
            DB::commit();

            foreach ($documents as $document) {
                $this->notificationService->sendDocumentUploadNotification($document, $user);
            }

            $message = count($documents) === 1
                ? 'Document uploaded successfully.'
                : count($documents) . ' documents uploaded successfully.';

            return redirect()->route('documents.index')->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error uploading document: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to upload documents. Error: ' . $e->getMessage()])->withInput();
        }
    }

    /**
     * Show document details
     */
    public function show(Request $request, Document $document)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canViewDocument($user, $document)) {
            abort(403, 'You do not have permission to view this document.');
        }

        // log activity
        $this->logUserActivity(
            'view',
            'documents',
            $document->id,
            'Viewed document: ' . $document->title
        );

        // activity stats will be loaded in the view
        $document->views_count = $document->getViewsCount();
        $document->downloads_count = $document->getDownloadsCount();
        $document->updates_count = $document->getUpdatesCount();
        $document->last_accessed_at = $document->getLastAccessedAt();

        // Get filter parameters from request
        $filters = $request->only([
            'search',
            'document_type_id',
            'sub_document_type_id',
            'commodity_id',
            'grower_id',
            'fbo_id',
            'variety_id',
            'vessel_name',
            'container_number'
        ]);

        // Get all accessible documents with filters for navigation
        $query = $this->userAccessService->getFilteredDocuments($user, $filters);
        
        // Get all document IDs in filtered order
        $documentIds = $query->pluck('id')->toArray();
        $currentIndex = array_search($document->id, $documentIds);
        
        // Get previous and next document IDs
        $previousDocumentId = $currentIndex > 0 ? $documentIds[$currentIndex - 1] : null;
        $nextDocumentId = $currentIndex < count($documentIds) - 1 ? $documentIds[$currentIndex + 1] : null;

        $allDocuments = $query->get();

        // Get filter options
        // $documentTypes = DocumentType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        // $subDocumentTypes = DocumentType::where('is_active', true)->whereNotNull('parent_id')->orderBy('name')->get();
        // $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        // $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        // $fbos = $this->userAccessService->getAccessibleFbos(Auth::user())->orderBy('code')->get();
        // $varieties = Variety::where('is_active', true)->orderBy('name')->get();

        // accessible document types
        $documentTypes = $this->userAccessService->getAccessibleDocumentTypes($user, false)->orderBy('name')->get();
        // accessible sub document types
        $subDocumentTypes = $this->userAccessService->getAccessibleDocumentTypes($user, true)->orderBy('name')->get();

        // accessible fbos, comodities and growers
        $commodities = $this->userAccessService->getAccessibleCommoditiesForUser($user)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos(Auth::user())->orderBy('code')->get();
        $growers = $this->userAccessService->getAccessibleGrowers(Auth::user())->orderBy('name')->get();
        $varieties = Variety::where('is_active', true)->orderBy('name')->get();
        // vessels from 
        $vessels = Document::getDistinctVesselNames();

        return view('documents.show', compact(
            'document',
            'filters',
            'documentTypes',
            'subDocumentTypes',
            'commodities',
            'growers',
            'fbos',
            'varieties',
            'vessels',
            'allDocuments',
            'previousDocumentId',
            'nextDocumentId'
        ));
    }

    /**
     * Show edit form
     */
    public function edit(Document $document)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canEditDocument($user, $document)) {
            abort(403, 'You do not have permission to edit this document.');
        }

        $documentTypes = DocumentType::where('is_active', true)->where('parent_id', null)->orderBy('name')->get();
        $subDocumentTypes = DocumentType::where('is_active', true)
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
        $document->grower_id = $document->grower()->id ?? null;

        $documentExpiryDate = $document->expiry_date ? $document->expiry_date->format('Y-m-d') : null;

        $this->logUserActivity(
            'view',
            'documents',
            $document->id,
            'Accessed edit form for document: ' . $document->title
        );

        return view('documents.edit', compact(
            'document', 
            'documentTypes', 
            'subDocumentTypes', 
            'commodities', 
            'fbos',
            'companies',
            'growers',
            'varieties',
            // 'vessels',
            'documentExpiryDate'
        ));
    }

    /**
     * Update document
     */
    public function update(Request $request, Document $document)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canEditDocument($user, $document)) {
            abort(403, 'You do not have permission to edit this document.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'nullable|file|max:51200|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip', // 50MB max
            'document_type_id' => 'required|exists:document_types,id',
            'sub_document_type_id' => 'nullable|exists:document_types,id',
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
                if ($this->fileStorageService->exists($document->file_path)) {
                    $this->fileStorageService->delete($document->file_path);
                }
                
                // Upload new file (same as store method)
                $fileData = $this->fileStorageService->upload($uploadedFile, 'documents');
                
                // Update file-related fields
                $document->filename = $fileData['filename'];
                $document->original_filename = $fileData['original_filename'];
                $document->file_path = $fileData['file_path'];
                $document->file_size = $fileData['file_size'];
                $document->mime_type = $fileData['mime_type'];
                // $document->file_extension = $uploadedFile->getClientOriginalExtension();
                
                // Log file replacement
                $this->logUserActivity(
                    'replace_file',
                    'documents',
                    $document->id,
                    'Replaced file for document: ' . $document->title . ' (New: ' . $fileData['original_filename'] . ')'
                );
            }
            
            $fileMetadata = $document->metadata;
            if ($request->grower_id || $document->grower() ?? null) { // TypeError\n Cannot access offset of type string on string // Why?? 
                $request->validate([
                    'grower_id' => 'nullable|exists:growers,id',
                ]);

                $growerId = $document->grower()->id ?? null;
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
                'document_type_id' => $request->document_type_id,
                'sub_document_type_id' => $request->sub_document_type_id,
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

            // Update document
            $document->update($updateData);

            // Sync commodities
            $document->commodities()->sync($request->commodities);
            
            // sync FBOs by code
            if ($request->has('fbos') && !empty($request->fbos)) {
                // Find FBO IDs by code
                $fboIds = Fbo::whereIn('code', $request->fbos)->pluck('id')->toArray();
                if (!empty($fboIds)) {
                    $document->fbos()->sync($fboIds);
                }
            } else {
                $document->fbos()->sync([]);
            }

            // Sync varieties
            if ($request->has('varieties') && !empty($request->varieties)) {
                $document->varieties()->sync($request->varieties);
            } else {
                $document->varieties()->sync([]);
            }

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'update',
                'documents',
                $document->id,
                'Updated document: ' . $document->title,
                'Document "' . $document->title . '" updated successfully.'
            );
            
            DB::commit();

            // Redirect back to the documents index page with pagination preserved
            $page = $request->input('page', 1);
            return redirect()->route('documents.index', ['page' => $page])
                ->with('success', 'Document updated successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            return back()->withErrors(['error' => 'Failed to update document. Error: ' . $e->getMessage()]);
        }
    }

    /**
     * Download document
     */
    public function download(Document $document)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canViewDocument($user, $document)) {
            abort(403, 'You do not have permission to download this document.');
        }

        if (!$this->fileStorageService->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        $fileContents = $this->fileStorageService->get($document->file_path);

        // Log download activity
        $this->logUserActivity(
            'download',
            'documents',
            $document->id,
            'Downloaded document: ' . $document->title
        );
        
        return response($fileContents)
            ->header('Content-Type', $document->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . $document->original_filename . '"');
    }

    /**
     * Stream PDF for preview (inline)
     */
    public function preview(Document $document)
    {
        $user = Auth::user();
        if (!$this->userAccessService->canViewDocument($user, $document)) {
            abort(403, 'You do not have permission to preview this document.');
        }
        if (!$this->fileStorageService->exists($document->file_path)) {
            abort(404, 'File not found.');
        }
        $fileContents = $this->fileStorageService->get($document->file_path);
        // Log preview activity
        $this->logUserActivity(
            'preview',
            'documents',
            $document->id,
            'Previewed document: ' . $document->title
        );
        return response($fileContents)
            ->header('Content-Type', $document->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . $document->original_filename . '"');
    }

    public function destroy(Document $document)
    {
        $user = Auth::user();
        
        if (!$this->userAccessService->canDeleteDocument($user, $document)) {
            abort(403, 'You do not have permission to delete this document.');
        }

        DB::beginTransaction();
        
        try {
            // Delete file from storage
            $this->fileStorageService->delete($document->file_path);
            
            // Delete database record
            $document->delete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'delete',
                'documents',
                $document->id,
                'Deleted document: ' . $document->title,
                'Document "' . $document->title . '" deleted successfully.'
            );
            
            DB::commit();

            // If AJAX/JSON, return JSON
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'success', 'message' => 'Document deleted successfully.']);
            }

            // Otherwise, classic redirect
            return redirect()->route('documents.index')
                ->with('success', 'Document deleted successfully.');

        } catch (\Exception $e) {
            DB::rollback();

            // If AJAX/JSON, return JSON error
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json(['status' => 'error', 'message' => 'Failed to delete document. Please try again.'], 500);
            }
            
            return back()->withErrors(['error' => 'Failed to delete document. Please try again.']);
        }
    }

    /**
     * display trashed documents listing
     */
    public function trashed()
    {
        $user = Auth::user();
        $request = request();
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to access trashed documents.');
        }

        // Get filter and sort parameters as sent from Blade
        $filters = $request->only([
            'search',
            'document_type_id',
            'sub_document_type_id',
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
            $filters['per_page'] = Document::onlyTrashed()->count();
            $request->query->remove('page');
        }

        // Build trashed documents query
        $query = Document::onlyTrashed()->with([
            'documentType',
            'subDocumentType',
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
        if (!empty($filters['document_type_id'])) {
            $query->where('document_type_id', $filters['document_type_id']);
        }
        if (!empty($filters['sub_document_type_id'])) {
            $query->where('sub_document_type_id', $filters['sub_document_type_id']);
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
        $allowedSortFields = ['title', 'document_type_id', 'deleted_at', 'created_at', 'expiry_date'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('deleted_at', 'desc');
        }

        $documents = $query->paginate($filters['per_page'])->withQueryString();

        // Calculate stats
        $stats = [
            'total' => Document::onlyTrashed()->count(),
        ];

        // Get filter options
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();

        // log activity
        $this->logUserActivity(
            'view',
            'documents',
            null,
            'Accessed trashed documents listing'
        );

        return view('documents.trashed', compact('documents', 'stats', 'documentTypes', 'commodities', 'fbos', 'filters'));
    }

    /**
     * Restore trashed document
     */
    public function restore($id)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to restore trashed documents.');
        }

        $document = Document::onlyTrashed()->find($id);
        if (!$document) {
            return back()->withErrors(['error' => 'Document not found or not in trash.']);
        }

        DB::beginTransaction();
        
        try {
            $document->restore();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'restore',
                'documents',
                $document->id,
                'Restored document: ' . $document->title,
                'Document "' . $document->title . '" restored successfully.'
            );
            
            DB::commit();
            
            return redirect()->route('documents.show', $document)
                ->with('success', 'Document restored successfully.');

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors(['error' => 'Failed to restore document. Please try again.']);
        }
    }

    /**
     * Permanently delete trashed document
     */
    public function forceDelete($id)
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to permanently delete trashed documents.');
        }

        $document = Document::onlyTrashed()->find($id);
        if (!$document) {
            return back()->withErrors(['error' => 'Document not found or not in trash.']);
        }

        DB::beginTransaction();
        
        try {
            // Delete file from storage
            $this->fileStorageService->delete($document->file_path);
            
            // Permanently delete database record
            $document->forceDelete();

            // log activity and create notification
            $this->logUserActivityAndNotification(
                'force_delete',
                'documents',
                $id,
                'Permanently deleted document: ' . $document->title,
                'Document "' . $document->title . '" permanently deleted.'
            );
            
            DB::commit();
            
            return redirect()->route('trashed-data.documents.index')
                ->with('success', 'Document permanently deleted.');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error permanently deleting document ID ' . $id . ': ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to permanently delete document. Please try again.']);
        }
    }

    /**
     * Batch download documents
     */
    public function batchDownload(Request $request)
    {
        $user = Auth::user();
        $documentIds = $request->input('document_ids', []);
        
        if (empty($documentIds)) {
            return back()->withErrors(['error' => 'No documents selected.']);
        }

        // Get documents user can access
        $documents = $this->userAccessService
            ->getAccessibleDocuments($user)
            ->whereIn('id', $documentIds)
            ->get();

        if ($documents->isEmpty()) {
            return back()->withErrors(['error' => 'No accessible documents found.']);
        }

        // Create ZIP file (simplified version - in production, use a proper ZIP library)
        $zipName = 'documents_' . now()->format('Y-m-d_H-i-s') . '.zip';

        // Create ZIP file
        $zip = new \ZipArchive();
        if ($zip->open(public_path($zipName), \ZipArchive::CREATE) === TRUE) {
            foreach ($documents as $document) {
                // log activity for each document
                $this->logUserActivity(
                    'download',
                    'documents',
                    $document->id,
                    'Included in batch download: ' . $document->title
                );
                    // Add each document to the ZIP using the actual file path
                    if ($this->fileStorageService->exists($document->file_path)) {
                        // Get the absolute local path for the file
                        $localPath = Storage::path($document->file_path);
                        $zip->addFile($localPath, $document->original_filename);
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
            'documents',
            null,
            'Downloaded zip of multiple documents. IDs: [' . implode(', ', $documents->pluck('id')->toArray()) . ']'
        );

        // Return ZIP file as download
        return response()->download(public_path($zipName))->deleteFileAfterSend(true);

        // For now, return first document (implement proper ZIP creation later)
        // return $this->download($documents->first());
    }

    /**
     * Bulk restore all trashed documents
     */
    public function bulkRestoreAll()
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to restore trashed documents.');
        }

        $documents = Document::onlyTrashed()->get();
        
        if ($documents->isEmpty()) {
            return back()->withErrors(['error' => 'No trashed documents found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($documents as $document) {
                $document->restore();
                $count++;
                
                // log activity
                $this->logUserActivity(
                    'restore',
                    'documents',
                    $document->id,
                    'Restored document: ' . $document->title
                );
            }
            
            DB::commit();
            
            return redirect()->route('trashed-data.documents.index')
                ->with('success', "{$count} document(s) restored successfully.");

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error bulk restoring documents: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to restore documents. Please try again.']);
        }
    }

    /**
     * Bulk force delete all trashed documents
     */
    public function bulkForceDeleteAll()
    {
        $user = Auth::user();
        
        if (!$user->hasRole(['admin', 'super-user'])) {
            abort(403, 'You do not have permission to permanently delete trashed documents.');
        }

        $documents = Document::onlyTrashed()->get();
        
        if ($documents->isEmpty()) {
            return back()->withErrors(['error' => 'No trashed documents found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($documents as $document) {
                // Delete file from storage
                if ($document->file_path && $this->fileStorageService->exists($document->file_path)) {
                    $this->fileStorageService->delete($document->file_path);
                }
                
                // Permanently delete database record
                $document->forceDelete();
                $count++;
                
                // log activity
                $this->logUserActivity(
                    'force_delete',
                    'documents',
                    $document->id,
                    'Permanently deleted document: ' . $document->title
                );
            }
            
            DB::commit();
            
            // Reset auto-increment
            DB::statement('ALTER TABLE documents AUTO_INCREMENT = 1');
            
            return redirect()->route('trashed-data.documents.index')
                ->with('success', "{$count} document(s) permanently deleted. Auto-increment reset.");

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Error bulk force deleting documents: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Failed to permanently delete documents. Please try again.']);
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

        // Get all documents with relationships
        $documents = Document::with([
            'documentType', 
            'subDocumentType', 
            'company', 
            'fbos', 
            'uploadedBy',
            'commodities'
        ])->orderBy('created_at', 'desc')->paginate(50);

        // Calculate stats
        $stats = [
            'total' => Document::count(),
            'active' => Document::where('is_active', true)->count(),
            'expiring' => Document::whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>', now())
                ->count(),
            'expired' => Document::whereNotNull('expiry_date')
                ->where('expiry_date', '<=', now())
                ->count(),
        ];

        // filter options
        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $commodities = Commodity::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $fbos = $this->userAccessService->getAccessibleFbos($user)->orderBy('code')->get();
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        // log activity
        $this->logUserActivity(
            'view',
            'documents',
            null,
            'Accessed bulk document management page'
        );

        return view('documents.bulk', compact('documents', 'stats', 'documentTypes', 'commodities', 'fbos', 'companies'));
    }

    /**
     * Bulk activate documents
     */
    public function bulkActivate(Request $request)
    {
        return $this->executeBulkAction($request, 'activate');
    }

    /**
     * Bulk deactivate documents
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
     * Bulk delete documents
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

        $documentIds = $request->input('document_ids', []);
        
        if (empty($documentIds)) {
            return back()->withErrors(['error' => 'No documents selected.']);
        }

        $documents = Document::whereIn('id', $documentIds)->get();
        
        if ($documents->isEmpty()) {
            return back()->withErrors(['error' => 'No documents found.']);
        }

        DB::beginTransaction();
        
        try {
            $count = 0;
            
            foreach ($documents as $document) {
                switch ($action) {
                    case 'activate':
                        $document->update(['is_active' => true]);
                        $count++;
                        break;
                        
                    case 'deactivate':
                        $document->update(['is_active' => false]);
                        $count++;
                        break;
                        
                    case 'make_public':
                        $document->update(['is_public' => true]);
                        $count++;
                        break;
                        
                    case 'make_private':
                        $document->update(['is_public' => false]);
                        $count++;
                        break;
                        
                    case 'set_expiry':
                        $document->update(['expiry_date' => $request->expiry_date]);
                        $count++;
                        break;
                        
                    case 'delete':
                        // Delete file from storage
                        if ($document->file_path && $this->fileStorageService->exists($document->file_path)) {
                            $this->fileStorageService->delete($document->file_path);
                        }
                        $document->delete();
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
            
            $message = "{$count} document(s) " . $actionLabels[$action] . " successfully.";

            // log action for each document
            foreach ($documents as $document) {
                $this->logUserActivity(
                    $action,
                    'documents',
                    $document->id,
                    ucfirst($action) . ' document: ' . $document->title
                );
            }
            
            
            return back()->with('success', $message);

        } catch (\Exception $e) {
            DB::rollback();
            
            return back()->withErrors(['error' => 'Failed to perform bulk action. Please try again.']);
        }
    }
}