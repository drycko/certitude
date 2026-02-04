<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Tenant\Dashboard\DashboardController;
use App\Http\Controllers\Tenant\FileManager\FileController;
use App\Http\Controllers\PowerbiManager\PowerbiController;
use App\Http\Controllers\Tenant\UserManagement\UserController;
use App\Http\Controllers\Tenant\UserManagement\RoleController;
use App\Http\Controllers\Tenant\UserManagement\RoleAssignmentController;
use App\Http\Controllers\Tenant\UserManagement\PermissionController;
use App\Http\Controllers\Tenant\UserManagement\AccessRequestController;
use App\Http\Controllers\Tenant\MasterData\GrowerController;
use App\Http\Controllers\Tenant\MasterData\GrowerAssignUserController;
use App\Http\Controllers\Tenant\MasterData\GrowerAssignFboController;
use App\Http\Controllers\Tenant\MasterData\CommodityController;
use App\Http\Controllers\Tenant\MasterData\VarietyController;
use App\Http\Controllers\Tenant\MasterData\VesselController;
use App\Http\Controllers\Tenant\MasterData\FboController;
use App\Http\Controllers\Tenant\MasterData\UserGroupController;
use App\Http\Controllers\Tenant\MasterData\CompanyController;
use App\Http\Controllers\Tenant\MasterData\FileTypeController;
use App\Http\Controllers\Tenant\MasterData\PowerbiLinkTypeController;
use App\Http\Controllers\Tenant\HelpController;
use Illuminate\Support\Facades\Auth;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Tenant storage route - serve tenant files
    Route::get('/storage/{path}', function ($path) {
        $tenantId = tenant('id');
        $filePath = storage_path("app/public/{$path}");
        
        // Debug: Log the paths for troubleshooting
        // \Log::info('Tenant Storage Route Debug', [
        //     'tenant_id' => $tenantId,
        //     'requested_path' => $path,
        //     'full_file_path' => $filePath,
        //     'file_exists' => file_exists($filePath)
        // ]);
        
        if (!file_exists($filePath)) {
            abort(404, "File not found: {$filePath}");
        }
        
        $mimeType = mime_content_type($filePath);
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    })->where('path', '.*')->name('tenant.storage');

    // Tenant authentication routes
    require __DIR__.'/tenant-auth.php';

    // Public routes (only on tenant domains, not central domains)
    if (!app(\Stancl\Tenancy\Tenancy::class)->initialized) {
        // Skip tenant root route if tenancy not initialized (means we're on central domain)
    } else {
        Route::get('/', function () {
            return redirect()->route('tenant.guest-portal.index');
        })->name('tenant.guest-portal.home');
    }

    // prefix tenant routes with /t

    // settings route group
    Route::prefix('/t/settings')->middleware('auth:tenant')->name('tenant.settings.')->group(function () {
        Route::get('/', [TenantSettingController::class, 'index'])->name('index');
        // General settings routes
        Route::get('/general', [TenantSettingController::class, 'general'])->name('general');
        Route::put('/general', [TenantSettingController::class, 'updateGeneral'])->name('general.update');
        // Preferences routes
        Route::get('/preferences', [TenantSettingController::class, 'preferences'])->name('preferences');
        Route::put('/preferences', [TenantSettingController::class, 'updatePreferences'])->name('preferences.update');
    });

    // Protected routes with property context (prefixed 't' for tenant)
    Route::prefix('/t')->middleware(['auth:tenant', 'must.change.password', 'subscription.check'])->group(function () {
        // redirect to dashboard
        Route::get('/', function () {
            return redirect()->route('tenant.dashboard');
        });
        // can we redirect page not found to 404 error page to custom
        Route::fallback(function () {
            return redirect()->route('tenant.error')->with('error', 'The page you are looking for does not exist.');
        });
        // Dashboard - accessible to all authenticated users
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('tenant.dashboard');
        Route::get('/knowledge-base', [DashboardController::class, 'knowledgeBase'])->name('tenant.knowledge-base');

        // Dashboard routes
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/home', [DashboardController::class, 'index'])->name('home'); // Keep for compatibility
        Route::get('/profile', [DashboardController::class, 'profile'])->name('dashboard.profile');
        Route::post('/profile', [DashboardController::class, 'updateProfile'])->name('dashboard.profile.update');

        Route::get('/knowledge-base', [DashboardController::class, 'knowledgeBase'])->name('knowledge-base');
        Route::get('/help', [DashboardController::class, 'help'])->name('help');

        // notifications
        Route::get('/notifications', [DashboardController::class, 'notifications'])->name('notifications.index');
        Route::post('/notifications/mark-as-read', [DashboardController::class, 'notificationsMarkAsRead'])->name('notifications.mark-as-read');
        // mark all as read
        Route::post('/notifications/mark-all-as-read', [DashboardController::class, 'notificationsMarkAllAsRead'])->name('notifications.mark-all-as-read');

        // File routes
        Route::resource('files', FileController::class)->names([
            'index' => 'files.index',
            'create' => 'files.create',
            'store' => 'files.store',
            'show' => 'files.show',
            'edit' => 'files.edit',
            'update' => 'files.update',
            // 'destroy' => 'files.destroy',
        ]);
        Route::delete('/files/{file}', [FileController::class, 'destroy'])->name('files.destroy');
        Route::get('/files/management/bulk', [FileController::class, 'bulk'])->name('files.bulk');
        Route::post('/files/batch-download', [FileController::class, 'batchDownload'])->name('files.batch-download');
        Route::get('/files/{file}/download', [FileController::class, 'download'])->name('files.download');
        Route::get('/files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
        
        // Bulk operations (admin only)
        Route::middleware(['role:admin|super-user'])->group(function () {
            Route::post('/files/bulk-activate', [FileController::class, 'bulkActivate'])->name('files.bulk-activate');
            Route::post('/files/bulk-deactivate', [FileController::class, 'bulkDeactivate'])->name('files.bulk-deactivate');
            Route::post('/files/bulk-make-public', [FileController::class, 'bulkMakePublic'])->name('files.bulk-make-public');
            Route::post('/files/bulk-make-private', [FileController::class, 'bulkMakePrivate'])->name('files.bulk-make-private');
            Route::post('/files/bulk-set-expiry', [FileController::class, 'bulkSetExpiry'])->name('files.bulk-set-expiry');
            Route::post('/files/bulk-delete', [FileController::class, 'bulkDelete'])->name('files.bulk-delete');
        });

        // Power BI Links routes
        Route::resource('powerbi-links', PowerbiLinkController::class)->names([
            'index' => 'powerbi-links.index',
            'create' => 'powerbi-links.create',
            'store' => 'powerbi-links.store',
            'show' => 'powerbi-links.show',
            'edit' => 'powerbi-links.edit',
            'update' => 'powerbi-links.update',
            'destroy' => 'powerbi-links.destroy',
        ]);
        Route::get('/powerbi-links/{id}/view', [PowerbiLinkController::class, 'view'])->name('powerbi-links.view');
        Route::get('/powerbi-links/{id}/iframe', [PowerbiLinkController::class, 'iframe'])->name('powerbi-links.iframe');
        Route::get('/powerbi-links/{id}/get-embed-data', [PowerbiLinkController::class, 'getEmbedData'])->name('powerbi-links.get-embed-data');
        Route::get('/powerbi-links/management/bulk', [PowerbiLinkController::class, 'bulk'])->name('powerbi-links.bulk');
        // stop impersonation route
        Route::post('/users/stop-impersonation', [UserController::class, 'stopImpersonation'])->name('users.stop-impersonation');

        // Admin routes
        Route::middleware(['role:admin|super-user'])->group(function () {
            // search suggestion (with query param)
            Route::get('/users/search', [UserController::class, 'searchSuggestions'])->name('users.search');

            // Access requests admin UI (under users prefix) - must be declared before the users resource
            Route::get('/users/access-requests', [AccessRequestController::class, 'index'])->name('users.access-requests.index');
            Route::put('/users/access-requests/{accessRequest}/status', [AccessRequestController::class, 'updateStatus'])->name('users.access-requests.update-status');

            Route::resource('users', UserController::class);
            Route::post('/users/resend-welcome-email/{user}', [UserController::class, 'resendWelcomeEmail'])->name('users.resend-welcome-email');
            Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
            Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::put('/users/{user}/update-password', [UserController::class, 'updatePassword'])->name('users.update-password');
            // impersonate user
            Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
            // Route::post('/users/bulk-reset-password', [UserController::class, 'bulkResetPassword'])->name('users.bulk-reset-password');
            // Route::post('/users/bulk-activate', [UserController::class, 'bulkActivate'])->name('users.bulk-activate');
            // Route::post('/users/bulk-deactivate', [UserController::class, 'bulkDeactivate'])->name('users.bulk-deactivate');
            // Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');
            // Route::post('/users/bulk-change-role', [UserController::class, 'bulkChangeRole'])->name('users.bulk-change-role');

            // Roles management - dedicated controller with proper permissions
            Route::resource('roles', RoleController::class)->names([
                'index' => 'roles.index',
                'create' => 'roles.create',
                'store' => 'roles.store',
                'show' => 'roles.show',
                'edit' => 'roles.edit',
                'update' => 'roles.update',
                'destroy' => 'roles.destroy',
            ]);
            Route::post('roles/{role}/sync-permissions', [RoleController::class, 'syncPermissions'])->name('roles.sync-permissions');

            // Permissions management - dedicated controller with proper permissions
            Route::resource('permissions', PermissionController::class)->names([
                'index' => 'permissions.index',
                'create' => 'permissions.create',
                'store' => 'permissions.store',
                'show' => 'permissions.show',
                'edit' => 'permissions.edit',
                'update' => 'permissions.update',
                'destroy' => 'permissions.destroy',
            ]);
            Route::post('permissions/bulk-create', [PermissionController::class, 'bulkCreate'])->name('permissions.bulk-create');

            // Role assignments - managing user roles
            Route::get('role-assignments', [RoleAssignmentController::class, 'index'])->name('role-assignments.index');
            Route::get('role-assignments/{user}/edit', [RoleAssignmentController::class, 'edit'])->name('role-assignments.edit');
            Route::put('role-assignments/{user}', [RoleAssignmentController::class, 'update'])->name('role-assignments.update');
            Route::post('role-assignments/bulk-assign', [RoleAssignmentController::class, 'bulkAssign'])->name('role-assignments.bulk-assign');
            
            // Admin system and storage monitoring
            Route::prefix('admin')->name('admin.')->group(function () {
                Route::get('/system', [DashboardController::class, 'systemOverview'])->name('system.overview');
                Route::post('/system/refresh', [DashboardController::class, 'refreshSystemOverview'])->name('system.refresh');
                Route::post('/storage/refresh', [DashboardController::class, 'refreshStorage'])->name('storage.refresh');
                Route::post('/storage/cleanup', [DashboardController::class, 'cleanupStorage'])->name('storage.cleanup');
                Route::get('/storage/analyze', [DashboardController::class, 'analyzeStorage'])->name('storage.analyze');
                Route::get('/storage/export', [DashboardController::class, 'exportStorageReport'])->name('storage.export');
                Route::get('/system/settings', [DashboardController::class, 'systemSettings'])->name('system.settings');
                Route::post('/system/maintenance', [DashboardController::class, 'systemMaintenance'])->name('system.maintenance');
                Route::get('/system/logs', [DashboardController::class, 'systemLogs'])->name('system.logs');
            
            });
            // master data management routes with prefix
            Route::prefix('master-data')->name('master-data.')->group(function () {

                // Growers management - dedicated controller with proper permissions
                Route::resource('growers', GrowerController::class)->names([
                    'index' => 'growers.index',
                    'create' => 'growers.create',
                    'store' => 'growers.store',
                    'show' => 'growers.show',
                    'edit' => 'growers.edit',
                    'update' => 'growers.update',
                    'destroy' => 'growers.destroy',
                ]);
                Route::post('growers/bulk', [GrowerController::class, 'bulk'])->name('growers.bulk');

                // assign users to growers
                Route::post('grower-assign-users/{grower}', [GrowerController::class, 'assignUsers'])->name('grower-assign-users.update');
                // Assign FBOs to growers
                Route::post('grower-assign-fbos/{grower}', [GrowerController::class, 'assignFbos'])->name('grower-assign-fbos.assign');
                Route::post('grower-assign-commodities/{grower}', [GrowerController::class, 'assignCommodities'])->name('grower-assign-commodities.assign');

                // FBOs management - dedicated controller with proper permissions
                Route::resource('fbos', FboController::class)->names([
                    'index' => 'fbos.index',
                    'create' => 'fbos.create',
                    'store' => 'fbos.store',
                    'show' => 'fbos.show',
                    'edit' => 'fbos.edit',
                    'update' => 'fbos.update',
                    'destroy' => 'fbos.destroy',
                ]);
                // Commodities management - dedicated controller with proper permissions
                Route::resource('commodities', CommodityController::class)->names([
                    'index' => 'commodities.index',
                    'create' => 'commodities.create',
                    'store' => 'commodities.store',
                    'show' => 'commodities.show',
                    'edit' => 'commodities.edit',
                    'update' => 'commodities.update',
                    'destroy' => 'commodities.destroy',
                ]);

                // Varieties management - dedicated controller with proper permissions
                Route::get('varieties/export-csv', [VarietyController::class, 'exportCsv'])->name('varieties.export-csv');
                Route::resource('varieties', VarietyController::class)->names([
                    'index' => 'varieties.index',
                    'create' => 'varieties.create',
                    'store' => 'varieties.store',
                    'show' => 'varieties.show',
                    'edit' => 'varieties.edit',
                    'update' => 'varieties.update',
                    'destroy' => 'varieties.destroy',
                ]);

                // Vessels management - dedicated controller with proper permissions
                Route::resource('vessels', VesselController::class)->names([
                    'index' => 'vessels.index',
                    'create' => 'vessels.create',
                    'store' => 'vessels.store',
                    'show' => 'vessels.show',
                    'edit' => 'vessels.edit',
                    'update' => 'vessels.update',
                    'destroy' => 'vessels.destroy',
                ]);

                // Groups management - dedicated controller with proper permissions
                Route::resource('groups', UserGroupController::class)->names([
                    'index' => 'groups.index',
                    'create' => 'groups.create',
                    'store' => 'groups.store',
                    'show' => 'groups.show',
                    'edit' => 'groups.edit',
                    'update' => 'groups.update',
                    'destroy' => 'groups.destroy',
                ]);
                
                // assign users to group
                Route::post('group-assign-users/{group}', [UserGroupController::class, 'assignUsers'])->name('group-assign-users.update');
                // Assign file types to groups
                Route::post('group-assign-file-types/{group}', [UserGroupController::class, 'assignFileTypes'])->name('group-assign-file-types.update');
                // assign powerbi link types to groups
                Route::post('group-assign-powerbi-link-types/{group}', [UserGroupController::class, 'assignPowerbiLinkTypes'])->name('group-assign-powerbi-link-types.update');

                // Companies management - dedicated controller with proper permissions
                Route::resource('companies', CompanyController::class)->names([
                    'index' => 'companies.index',
                    'create' => 'companies.create',
                    'store' => 'companies.store',
                    'show' => 'companies.show',
                    'edit' => 'companies.edit',
                    'update' => 'companies.update',
                    'destroy' => 'companies.destroy',
                ]);

                // File Types management - dedicated controller with proper permissions
                Route::resource('file-types', FileTypeController::class)->names([
                    'index' => 'file-types.index',
                    'create' => 'file-types.create',
                    'store' => 'file-types.store',
                    'show' => 'file-types.show',
                    'edit' => 'file-types.edit',
                    'update' => 'file-types.update',
                    'destroy' => 'file-types.destroy',
                ]);

                // powerbi link Types management - dedicated controller with proper permissions
                Route::resource('powerbi-link-types', PowerbiLinkTypeController::class)->names([
                    'index' => 'powerbi-link-types.index',
                    'create' => 'powerbi-link-types.create',
                    'store' => 'powerbi-link-types.store',
                    'show' => 'powerbi-link-types.show',
                    'edit' => 'powerbi-link-types.edit',
                    'update' => 'powerbi-link-types.update',
                    'destroy' => 'powerbi-link-types.destroy',
                ]);
                Route::post('powerbi-link-types/{type}/update-groups', [PowerbiLinkTypeController::class, 'updateGroups'])->name('powerbi-link-types.update-groups');
                // Help Articles management - dedicated controller with proper permissions
                Route::resource('help', HelpController::class)->names([
                    'index' => 'help.index',
                    'create' => 'help.create',
                    'store' => 'help.store',
                    'show' => 'help.show',
                    'edit' => 'help.edit',
                    'update' => 'help.update',
                    'destroy' => 'help.destroy',
                ]);

                // activity logs (show, index and export)
                Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
                Route::get('activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
                Route::get('activity-logs/{id}', [ActivityLogController::class, 'show'])->name('activity-logs.show');

            });

            Route::get('/company-logos/{filename}', [CompanyController::class, 'downloadLogo'])->name('company.logo.download');

            Route::prefix('trashed-data')->name('trashed-data.')->group(function () {
                // Trashed files management
                Route::get('files', [FileController::class, 'trashed'])->name('files.index');
                Route::post('files/{id}/restore', [FileController::class, 'restore'])->name('files.restore');
                Route::post('files/{id}/force-delete', [FileController::class, 'forceDelete'])->name('files.force-delete');
                Route::post('files/bulk-restore', [FileController::class, 'bulkRestore'])->name('files.bulk-restore');
                Route::post('files/bulk-force-delete', [FileController::class, 'bulkForceDelete'])->name('files.bulk-force-delete');
                Route::post('files/bulk-restore-all', [FileController::class, 'bulkRestoreAll'])->name('files.bulk-restore-all');
                Route::post('files/bulk-force-delete-all', [FileController::class, 'bulkForceDeleteAll'])->name('files.bulk-force-delete-all');

                // Trashed user management
                Route::get('users', [UserController::class, 'trashed'])->name('users.index');
                Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
                Route::post('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
                Route::post('users/bulk-restore', [UserController::class, 'bulkRestore'])->name('users.bulk-restore');
                Route::post('users/bulk-force-delete', [UserController::class, 'bulkForceDelete'])->name('users.bulk-force-delete');

                // trashed Powerbi link management
                Route::get('powerbi-links', [PowerbiLinkController::class, 'trashed'])->name('powerbi-links.index');
                Route::post('powerbi-links/{id}/restore', [PowerbiLinkController::class, 'restore'])->name('powerbi-links.restore');
                Route::post('powerbi-links/{id}/force-delete', [PowerbiLinkController::class, 'forceDelete'])->name('powerbi-links.force-delete');
                Route::post('powerbi-links/bulk-restore', [PowerbiLinkController::class, 'bulkRestore'])->name('powerbi-links.bulk-restore');
                Route::post('powerbi-links/bulk-force-delete', [PowerbiLinkController::class, 'bulkForceDelete'])->name('powerbi-links.bulk-force-delete');

                // Trashed grower management
                Route::get('growers', [GrowerController::class, 'trashed'])->name('growers.index');
                Route::post('growers/{id}/restore', [GrowerController::class, 'restore'])->name('growers.restore');
                Route::delete('growers/{id}/force-delete', [GrowerController::class, 'forceDelete'])->name('growers.force-delete');
                Route::post('growers/bulk-restore', [GrowerController::class, 'bulkRestore'])->name('growers.bulk-restore');
                Route::delete('growers/bulk-force-delete', [GrowerController::class, 'bulkForceDelete'])->name('growers.bulk-force-delete');

                // Trashed FBO management
                Route::get('fbos', [FboController::class, 'trashed'])->name('fbos.index');
                Route::post('fbos/{id}/restore', [FboController::class, 'restore'])->name('fbos.restore');
                Route::post('fbos/{id}/force-delete', [FboController::class, 'forceDelete'])->name('fbos.force-delete');
                Route::post('fbos/bulk-restore', [FboController::class, 'bulkRestore'])->name('fbos.bulk-restore');
                Route::post('fbos/bulk-force-delete', [FboController::class, 'bulkForceDelete'])->name('fbos.bulk-force-delete');

                // Trashed commodity management
                Route::get('commodities', [CommodityController::class, 'trashed'])->name('commodities.index');
                Route::post('commodities/{id}/restore', [CommodityController::class, 'restore'])->name('commodities.restore');
                Route::post('commodities/{id}/force-delete', [CommodityController::class, 'forceDelete'])->name('commodities.force-delete');
                Route::post('commodities/bulk-restore', [CommodityController::class, 'bulkRestore'])->name('commodities.bulk-restore');
                Route::post('commodities/bulk-force-delete', [CommodityController::class, 'bulkForceDelete'])->name('commodities.bulk-force-delete');

                // Trashed variety management
                Route::get('varieties', [VarietyController::class, 'trashed'])->name('varieties.index');
                Route::post('varieties/{id}/restore', [VarietyController::class, 'restore'])->name('varieties.restore');
                Route::post('varieties/{id}/force-delete', [VarietyController::class, 'forceDelete'])->name('varieties.force-delete');
                Route::post('varieties/bulk-restore', [VarietyController::class, 'bulkRestore'])->name('varieties.bulk-restore');
                Route::post('varieties/bulk-force-delete', [VarietyController::class, 'bulkForceDelete'])->name('varieties.bulk-force-delete');

                // Trashed vessel management
                Route::get('vessels', [VesselController::class, 'trashed'])->name('vessels.index');
                Route::post('vessels/{id}/restore', [VesselController::class, 'restore'])->name('vessels.restore');
                Route::post('vessels/{id}/force-delete', [VesselController::class, 'forceDelete'])->name('vessels.force-delete');
                Route::post('vessels/bulk-restore', [VesselController::class, 'bulkRestore'])->name('vessels.bulk-restore');
                Route::post('vessels/bulk-force-delete', [VesselController::class, 'bulkForceDelete'])->name('vessels.bulk-force-delete');

                // Trashed group management
                Route::get('groups', [UserGroupController::class, 'trashed'])->name('groups.index');
                Route::post('groups/{id}/restore', [UserGroupController::class, 'restore'])->name('groups.restore');
                Route::post('groups/{id}/force-delete', [UserGroupController::class, 'forceDelete'])->name('groups.force-delete');
                Route::post('groups/bulk-restore', [UserGroupController::class, 'bulkRestore'])->name('groups.bulk-restore');
                Route::post('groups/bulk-force-delete', [UserGroupController::class, 'bulkForceDelete'])->name('groups.bulk-force-delete');

                // Trashed company management
                Route::get('companies', [CompanyController::class, 'trashed'])->name('companies.index');
                Route::post('companies/{id}/restore', [CompanyController::class, 'restore'])->name('companies.restore');
                Route::post('companies/{id}/force-delete', [CompanyController::class, 'forceDelete'])->name('companies.force-delete');
                Route::post('companies/bulk-restore', [CompanyController::class, 'bulkRestore'])->name('companies.bulk-restore');
                Route::post('companies/bulk-force-delete', [CompanyController::class, 'bulkForceDelete'])->name('companies.bulk-force-delete');

                // Trashed file type management
                Route::get('file-types', [FileTypeController::class, 'trashed'])->name('file-types.index');
                Route::post('file-types/{id}/restore', [FileTypeController::class, 'restore'])->name('file-types.restore');
                Route::post('file-types/{id}/force-delete', [FileTypeController::class, 'forceDelete'])->name('file-types.force-delete');
                Route::post('file-types/bulk-restore', [FileTypeController::class, 'bulkRestore'])->name('file-types.bulk-restore');
                Route::post('file-types/bulk-force-delete', [FileTypeController::class, 'bulkForceDelete'])->name('file-types.bulk-force-delete');

                // Trashed Powerbi link type management
                Route::get('powerbi-link-types', [PowerbiLinkTypeController::class, 'trashed'])->name('powerbi-link-types.index');
                Route::post('powerbi-link-types/{id}/restore', [PowerbiLinkTypeController::class, 'restore'])->name('powerbi-link-types.restore');
                Route::post('powerbi-link-types/{id}/force-delete', [PowerbiLinkTypeController::class, 'forceDelete'])->name('powerbi-link-types.force-delete');
                Route::post('powerbi-link-types/bulk-restore', [PowerbiLinkTypeController::class, 'bulkRestore'])->name('powerbi-link-types.bulk-restore');
                Route::post('powerbi-link-types/bulk-force-delete', [PowerbiLinkTypeController::class, 'bulkForceDelete'])->name('powerbi-link-types.bulk-force-delete');
            });

        });

    });

    // // future guest portal will use magic logins and public access routes
    // Route::prefix('/guest')->group(function () {
    //     require __DIR__.'/tenant-guest-portal.php';
    // });


});