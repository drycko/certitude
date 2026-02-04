<?php

namespace App\Services\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Grower;
use App\Models\Tenant\File;
use App\Models\Tenant\FileType;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\Fbo;
use App\Models\Tenant\UserGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UserAccessService
{
    /**
     * Get files accessible by user based on roles, groups, and permissions
     * To-do: We will need to implement more granular and dynamic access controls.
     */
    public function getAccessibleFiles(User $user): Builder
    {
        
        // temporary: check  if the file_type_id is a top-level if not we change it to parent id and move the current type id to sub_file_type_id
        // $allFiles = File::query();
        // // loop to make the change
        // $allFiles->get()->each(function ($file) {
        //     $fileType = $file->fileType;
        //     if ($fileType && $fileType->parent_id !== null && $file->sub_file_type_id === null) {
        //         $file->file_type_id = $fileType->parent_id;
        //         $file->sub_file_type_id = $fileType->id;
        //         $file->save();
        //     }
        //     // check if the sub_file_type_id parent_id is not null, then we need to set the file_type_id to that parent id
        //     if ($fileType && $fileType->parent_id !== null && $file->sub_file_type_id !== null) {
        //         $subFileType = $fileType;
        //         if ($subFileType && $subFileType->parent_id !== null) {
        //             $file->file_type_id = $subFileType->parent_id;
        //             $file->save();
        //         }
        //     }
        // });

        $query = File::with(['company', 'fileType', 'fbos', 'commodities', 'varieties', 'uploadedBy']);

        // If user has 'manage all files' permission, return all
        if ($user->hasAnyPermission('manage all files')) {
            \Log::info("User {$user->id} has 'manage all files' permission to file.");
            // with no active filter;
            return $query;
        }

        // Build access conditions based on user's roles and groups
        $query->where(function (Builder $q) use ($user) {
            \Log::info("Building accessible files for User {$user->id}");

            // Growers (Role = grower) should only see Quality Files for their assigned grower(s)
            if ($user->hasAnyPermission('view files by grower') && $user->hasRole('grower')) {
                $userGrowerIds = $user->growers->pluck('id')->toArray();
                \Log::info("User {$user->id} grower IDs: " . implode(',', $userGrowerIds));
                
                if (!empty($userGrowerIds)) {
                    $restrictedConfig = config('app.grower_restricted_file_types', []);
                    
                    $q->orWhere(function (Builder $sq) use ($userGrowerIds, $restrictedConfig) {
                        $sq->where(function ($metaQuery) use ($userGrowerIds) {
                            foreach ($userGrowerIds as $growerId) {
                                // Cast to string since metadata stores it as string
                                $metaQuery->orWhereJsonContains('metadata->grower_id', (string)$growerId);
                            }
                        })
                        ->whereHas('fileType', function (Builder $dtq) use ($restrictedConfig) {
                            // Primary: Use attribute_type field (most reliable)
                            if (isset($restrictedConfig['attribute_type'])) {
                                $dtq->where('attribute_type', $restrictedConfig['attribute_type']);
                            } else {
                                // Fallback: Use name matching (less reliable but backwards compatible)
                                $dtq->whereIn('name', $restrictedConfig['names'] ?? []);
                            }
                        });
                    });
                }
            }

            // Customers (Role = customer) can see all files for a specific commodity except Quality Files and then Quality reports.
            if ($user->hasRole('customer')) {
                $commodityIds = $user->commodities->pluck('id')->toArray();
                
                if (!empty($commodityIds)) {
                    $excludedConfig = config('app.customer_excluded_file_types', []);
                    
                    $q->orWhere(function (Builder $sq) use ($commodityIds, $excludedConfig) {
                        $sq->where('is_public', false)
                           ->whereHas('commodities', function (Builder $cq) use ($commodityIds) {
                               $cq->whereIn('commodity_id', $commodityIds);
                           })
                           ->whereDoesntHave('fileType', function (Builder $dtq) use ($excludedConfig) {
                               // Primary: Use attribute_type field (most reliable)
                               if (isset($excludedConfig['attribute_type'])) {
                                   $dtq->where('attribute_type', $excludedConfig['attribute_type']);
                               } else {
                                   // Fallback: Use name matching (less reliable but backwards compatible)
                                   $dtq->whereIn('name', $excludedConfig['names'] ?? []);
                               }
                           });
                    });
                }
            }

            
            // Public files accessible by commodity
            if ($user->hasAnyPermission('view public files')) {
                $commodityIds = $user->commodities->pluck('id')->toArray();
                if (!empty($commodityIds)) {
                    $q->orWhere(function (Builder $sq) use ($commodityIds) {
                        $sq->where('is_public', true)
                           ->whereHas('commodities', function (Builder $cq) use ($commodityIds) {
                               $cq->whereIn('commodity_id', $commodityIds);
                           });
                    });
                }
            }

            // Files by user groups
            // $this->addGroupBasedAccess($q, $user);

            // Files uploaded by user
            if ($user->hasAnyPermission('view files')) {
                // \Log::info("User {$user->id} has 'view files' permission to view this: {$q->toSql()}");
                $q->orWhere('uploaded_by', $user->id);
            }

        });

        // if user does not have any access, return empty
        if (!$query->exists()) {
            \Log::info("User {$user->id} has no file access.");
            $query->whereRaw('1 = 0'); // no access
        }
        return $query->where('is_active', true);
    }

    /**
     * Add group-based file access to query
     */
    private function addGroupBasedAccess(Builder $query, User $user): void
    {
        foreach ($user->activeUserGroups as $group) {
            // Check if group has specific file access rules
            $groupMetadata = $group->metadata ?? [];
            
            if (isset($groupMetadata['file_access'])) {
                $accessRules = $groupMetadata['file_access'];
                
                // Apply group-specific access rules
                $query->orWhere(function (Builder $sq) use ($accessRules, $user) {
                    if (isset($accessRules['file_types'])) {
                        $sq->whereHas('fileType', function (Builder $dtq) use ($accessRules) {
                            $dtq->whereIn('name', $accessRules['file_types']);
                        });
                    }
                    
                    if (isset($accessRules['commodities'])) {
                        $sq->whereHas('commodities', function (Builder $cq) use ($accessRules) {
                            $cq->whereIn('name', $accessRules['commodities']);
                        });
                    }
                    
                    if (isset($accessRules['visibility'])) {
                        $sq->whereIn('is_public', $accessRules['visibility']);
                    }
                });
            }
        }
    }

    /**
     * Check if user can view specific file
     */
    public function canViewFile(User $user, File $file): bool
    {
        if (!$file->is_active) {
            return false;
        }

        // Admin/Super roles can view all
        if ($user->hasAnyPermission('manage all files')) {
            return true;
        }

        // File owner can always view
        if ($file->user_id === $user->id) {
            return true;
        }

        // Check if file is in user's accessible files
        return $this->getAccessibleFiles($user)
                    ->where('files.id', $file->id)
                    ->exists();
    }

    /**
     * Check if user can upload files
     */
    public function canUploadFiles(User $user): bool
    {
        return $user->hasAnyPermission('upload files');
    }

    /**
     * Check if user can upload to private space
     */
    public function canUploadToPrivateSpace(User $user): bool
    {
        return $user->hasAnyPermission('upload to private space');
    }

    /**
     * Check if user can delete file
     */
    public function canDeleteFile(User $user, File $file): bool
    {
        // Admin roles with delete permission
        if ($user->hasAnyPermission('delete files')) {
            return true;
        }

        // File owner with delete permission
        if ($file->user_id === $user->id && $user->hasAnyPermission('delete own files')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can edit file
     */
    public function canEditFile(User $user, File $file): bool
    {
        // Admin roles with edit permission and is super-user or admin
        if ($user->hasAnyPermission('edit files') && ($user->hasRole('super-user') || $user->hasRole('admin'))) {
            return true;
        }

        // File owner with edit permission
        if ($file->uploaded_by === $user->id && $user->hasAnyPermission('edit own files')) {
            return true;
        }

        // User can edit files for their assigned grower(s)
        if ($user->hasAnyPermission('edit files by grower')) {
            $userGrowerIds = $user->growers->pluck('id')->toArray();
            $fileGrowerId = $file->metadata['grower_id'] ?? null;
            if ($fileGrowerId && in_array($fileGrowerId, $userGrowerIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get filtered files based on search criteria
     */
    public function getFilteredFiles(User $user, array $filters = []): Builder
    {
        $query = $this->getAccessibleFiles($user);
        $expiryDays = config('app.file_days_until_expiry_warning');

        // Apply search filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('original_filename', 'like', "%{$search}%")
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
            // Only apply commodity filter if user has access to that commodity
            $userCommodityIds = $this->getUserCommodityIds($user);
            
            // Check if filtered commodity is in user's accessible commodities
            if (in_array($filters['commodity_id'], $userCommodityIds)) {
                $query->whereHas('commodities', function ($q) use ($filters) {
                    $q->where('commodity_id', $filters['commodity_id']);
                });
            } else {
                // User doesn't have access to this commodity, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($filters['fbo_id'])) {
            $query->whereHas('fbos', function ($q) use ($filters) {
                $q->where('fbo_id', $filters['fbo_id']);
            });
        }

        if (!empty($filters['grower_id'])) {
            // Cast to string since metadata stores it as string
            $query->whereJsonContains('metadata->grower_id', (string)$filters['grower_id']);
        }

        if (!empty($filters['variety_id'])) {
            $query->whereHas('varieties', function ($q) use ($filters) {
                $q->where('variety_id', $filters['variety_id']);
            });
        }

        if (!empty($filters['vessel_name'])) {
            $query->whereJsonContains('metadata->vessel_name', $filters['vessel_name']);
            // $query->whereHas('vessels', function ($q) use ($filters) {
            //     $q->where('vessel_id', $filters['vessel_id']);
            // });
        }

        if (!empty($filters['container_number'])) {
            $query->where('container_number', 'like', "%{$filters['container_number']}%");
        }

        if (!empty($filters['expiry_status'])) {
            switch ($filters['expiry_status']) {
                case 'expired':
                    $query->whereNotNull('expiry_date')
                          ->where('expiry_date', '<', now());
                    break;
                case 'expiring_soon':
                    // $expiryDays = $filters['expiry_days'] ?? $expiryDays;
                    $query->whereNotNull('expiry_date')
                          ->where('expiry_date', '>=', now())
                          ->where('expiry_date', '<=', now()->addDays($expiryDays));
                    break;
                case 'valid':
                    $query->where(function ($q) {
                        $q->whereNull('expiry_date')
                          ->orWhere('expiry_date', '>', now());
                    });
                    break;
            }
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        return $query;
    }

    /**
     * Get files accessible by user for specific commodity
     */
    public function getAccessibleFilesByCommodity($commodity, User $user): Builder
    {
        $query = $this->getAccessibleFiles($user)
                      ->whereHas('commodities', function (Builder $cq) use ($commodity) {
                          $cq->where('commodity_id', $commodity->id);
                      });

        return $query;
    }

    /**
     * Get user's accessible commodity IDs
     */
    public function getUserCommodityIds(User $user): array
    {
        if ($user->hasAnyPermission('manage all files')) {
            // Admin, Super, and other management users can access all commodities
            return Commodity::pluck('id')->toArray();
        }

        return $user->commodities->pluck('id')->toArray();
    }

    /**
     * Get commodities accessible by user
     */
    public function getAccessibleCommodities(User $user)
    {
        if ($user->hasAnyPermission('manage all files')) {
            // Admin, Super, and other management users can access all commodities
            return Commodity::where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        // Return only user's assigned commodities
        return $user->commodities()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if user belongs to specific legacy group
     */
    public function belongsToLegacyGroup(User $user, string $legacyGroupName): bool
    {
        return $user->activeUserGroups()
                    ->where('legacy_group_id', $legacyGroupName)
                    ->exists();
    }

    /**
     * Get user's effective permissions (from roles and groups)
     */
    public function getEffectivePermissions(User $user): Collection
    {
        return $user->getAllPermissions();
    }

    /**
     * Check if user has permission through any source with group context
     */
    public function hasPermissionInContext(User $user, string $permission, array $context = []): bool
    {
        // Check basic permission first
        if (!$user->hasAnyPermission($permission)) {
            return false;
        }

        // Apply context-based restrictions
        if (!empty($context)) {
            return $this->checkContextualPermissions($user, $permission, $context);
        }

        return true;
    }

    /**
     * Check contextual permissions based on group metadata
     */
    private function checkContextualPermissions(User $user, string $permission, array $context): bool
    {
        foreach ($user->activeUserGroups as $group) {
            $groupMetadata = $group->metadata ?? [];
            
            // Check if group has contextual restrictions
            if (isset($groupMetadata['restrictions'])) {
                $restrictions = $groupMetadata['restrictions'];
                
                // Apply commodity restrictions
                if (isset($context['commodity']) && isset($restrictions['commodities'])) {
                    if (!in_array($context['commodity'], $restrictions['commodities'])) {
                        return false;
                    }
                }
                
                // Apply file type restrictions
                if (isset($context['file_type']) && isset($restrictions['file_types'])) {
                    if (!in_array($context['file_type'], $restrictions['file_types'])) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Migrate legacy user group permissions
     */
    public function migrateLegacyGroupPermissions(User $user, array $legacyGroups): void
    {
        foreach ($legacyGroups as $legacyGroupData) {
            // Find or create user group
            $userGroup = UserGroup::firstOrCreate([
                'name' => $legacyGroupData['name'],
            ], [
                'display_name' => $legacyGroupData['display_name'] ?? $legacyGroupData['name'],
                'description' => $legacyGroupData['description'] ?? '',
                'legacy_group_id' => $legacyGroupData['legacy_group_id'] ?? null,
                'metadata' => $legacyGroupData['metadata'] ?? [],
            ]);

            // Convert legacy permissions to current permission names
            $permissions = $this->mapLegacyPermissions(
                $legacyGroupData['group_permissions'] ?? [],
                $legacyGroupData['specific_permissions'] ?? []
            );

            // Assign permissions to group if not already done
            if (!empty($permissions) && !$userGroup->permissions()->exists()) {
                $userGroup->syncPermissions($permissions);
            }

            // Parse expiry date if present
            $expiresAt = null;
            if (isset($legacyGroupData['expires_at'])) {
                try {
                    $expiresAt = new \DateTime($legacyGroupData['expires_at']);
                } catch (\Exception $e) {
                    // Invalid date, ignore
                }
            }

            // Assign user to group
            $specificPermissions = $legacyGroupData['specific_permissions'] ?? [];
            $user->assignToGroup(
                $userGroup,
                $legacyGroupData['is_primary'] ?? false,
                is_array($specificPermissions) ? $specificPermissions : [],
                $expiresAt
            );
        }
    }

    /**
     * Map legacy permission names to current permission names
     */
    private function mapLegacyPermissions(array $groupPermissions, array $specificPermissions): array
    {
        $permissionMapping = [
            // Legacy -> Current permission mapping
            'view_documents' => 'view files',
            'upload_documents' => 'upload files',
            'download_documents' => 'download files',
            'delete_documents' => 'delete files',
            'view_summary' => 'view summary',
            'view_users' => 'view users',
            'manage_users' => 'create users',
            'edit_users' => 'edit users',
            'delete_users' => 'delete users',
            'view_by_attribute_type' => 'view by attribute type',
            'manage_documents' => 'manage all files',
            'view_private_documents' => 'view private files',
            'view_public_documents' => 'view public files',
            'view_private_citrus_docs' => 'view private files', // Custom mapping
            'filter_summary_by_grower' => 'filter summary by grower',
        ];

        $mappedPermissions = [];

        // Map group permissions
        foreach ($groupPermissions as $legacyPermission) {
            if (isset($permissionMapping[$legacyPermission])) {
                $mappedPermissions[] = $permissionMapping[$legacyPermission];
            } else {
                // Try to use the permission as-is (in case it's already correct)
                $mappedPermissions[] = $legacyPermission;
            }
        }

        // Map specific permissions
        foreach ($specificPermissions as $legacyPermission) {
            if (isset($permissionMapping[$legacyPermission])) {
                $mappedPermissions[] = $permissionMapping[$legacyPermission];
            } else {
                $mappedPermissions[] = $legacyPermission;
            }
        }

        return array_unique($mappedPermissions);
    }

    /**
     * Get growers accessible by user based on roles, groups, and permissions (if not we return the growers they are assigned to)
     */
    public function getAccessibleGrowers(User $user): Builder
    {
        $query = Grower::query()->where('is_active', true);

        // If user has 'manage all growers' permission, return all
        if ($user->hasAnyPermission('manage all growers')) {
            \Log::info("User {$user->id} has 'manage all growers' permission to file.");
            return $query;
        }

        if ($user->hasAnyPermission('view growers')) {
                \Log::info("User {$user->id} has 'view growers' permission to file.");

            // Build access conditions based on user's roles and groups
            $query->where(function (Builder $q) use ($user) {
                // Growers assigned to user
                if ($user->hasAnyPermission('view growers')) {
                    $userGrowerIds = $user->growers->pluck('id')->toArray();
                    if (!empty($userGrowerIds)) {
                        $q->orWhereIn('id', $userGrowerIds);
                    }
                }
            });

            return $query;
        }
        // No access
        return $query->whereRaw('1 = 0');
    }

    /**
     * Check if user can create a new grower
     */
    public function canCreateGrower(User $user): bool
    {
        return $user->hasAnyPermission('create growers');
    }

    /**
     * Check if user can edit a grower
     */
    public function canEditGrower(User $user, Grower $grower): bool
    {
        // Admin/Super roles can edit all growers
        if ($user->hasAnyPermission('edit growers')) {
            return true;
        }

        // Grower creator with edit permission
        if ($grower->created_by === $user->id && $user->hasAnyPermission('edit own growers')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can delete a grower
     */
    public function canDeleteGrower(User $user, Grower $grower): bool
    {
        // Admin/Super roles can delete all growers
        if ($user->hasAnyPermission('delete growers')) {
            return true;
        }

        // Grower creator with delete permission
        if ($grower->created_by === $user->id && $user->hasAnyPermission('delete own growers')) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can assign growers to FBOs or Commodities
     */
    public function canAssignGrower(User $user): bool
    {
        return $user->hasPermission('assign growers');
    }

    /**
     * Get filtered growers based on search criteria and user permissions
     */
    public function getFilteredGrowers(User $user, array $filters = []): Builder
    {
        $query = Grower::query();

        // Apply search filters
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('grower_number', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%")
                  ->orWhere('region', 'like', "%{$search}%")
                  ->orWhere('sub_region', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        
        $query->orderBy($sortBy, $sortDirection);

        return $query;
    }

    // Something I also noted is that all FBO codes are available to growers - but they should only see their own PUC/PHC codes linked to them by Admin. admin and super users can see all FBOs.
    /**
     * Get FBOs accessible by user based on roles, groups, and permissions
     */
    public function getAccessibleFbos(User $user): Builder
    {
        $query = Fbo::query();

        // If user has 'manage all fbos' permission, return all
        if ($user->hasAnyPermission('manage all fbos')) {
            \Log::info("User {$user->id} has 'manage all fbos' permission to file.");
            return $query;
        }

        // Build access conditions based on user's roles and groups
        $query->where(function (Builder $q) use ($user) {
            // FBOs assigned to user's growers
            if ($user->hasAnyPermission('view fbos') && $user->hasRole('grower')) {
                $userGrowerIds = $user->growers->pluck('id')->toArray();
                if (!empty($userGrowerIds)) {
                    $q->orWhereHas('growers', function (Builder $gq) use ($userGrowerIds) {
                        $gq->whereIn('grower_id', $userGrowerIds);
                    });
                }
            }
        });

        // if user does not have any access, return empty
        if (!$user->hasAnyPermission('view fbos') || !$query->exists()) {
            \Log::info("User {$user->id} has no FBO access.");
            $query->whereRaw('1 = 0'); // no access
        }

        return $query->where('is_active', true);
    }

    /**
     * Get commodities accessible by user based on user's assigned commodities
     */
    public function getAccessibleCommoditiesForUser(User $user): Builder
    {
        $query = Commodity::query()->where('is_active', true);

        // If user has 'manage all commodities' permission, return all
        if ($user->hasAnyPermission('edit commodities')) {
            \Log::info("User {$user->id} has 'edit commodities' permission to file.");
            return $query;
        }

        // Build access conditions based on user's assigned commodities
        $userCommodityIds = $user->commodities->pluck('id')->toArray();
        if (!empty($userCommodityIds) && $user->hasAnyPermission('view commodities')) {
            $query->whereIn('id', $userCommodityIds);
        } else {
            // No access
            \Log::info("User {$user->id} has no Commodity access.");
            $query->whereRaw('1 = 0'); // no access
        }

        return $query;
    }

    /**
     * Get fileTypes accessible by user based on attribute_type in type model
     * File types have role access too, but the attribute_type supersedes.
     * For example, if a user has access to 'Quality Files' via role, but the attribute_type is 'grower' and the user does not have the role 'grower', they should not see the file type. this applies ton non-admin users.
     * Attribute_type values can be: grower, customer, admin, super-user, none, etc.
     * Attrribute none means everyone with view files permission can see it.
     */
    public function getAccessibleFileTypes(User $user, bool $isSubFileType): Builder
    {
        $query = FileType::query()->where('is_active', true);
        if ($isSubFileType) {
            $query->whereNotNull('parent_id');
        } else {
            $query->whereNull('parent_id');
        }

        // If user has 'manage all files' permission, return all
        if ($user->hasAnyPermission('manage all files')) {
            \Log::info("User {$user->id} has 'manage all files' permission to file.");
            return $query;
        }

        // Build access conditions based on user's roles
        $query->where(function (Builder $q) use ($user) {
            // File types for growers
            if ($user->hasRole('grower')) {
                $q->orWhere('attribute_type', 'grower');
            }

            // File types for customers
            if ($user->hasRole('customer')) {
                $q->orWhere('attribute_type', 'customer');
            }

            // File types for admin
            if ($user->hasRole('admin')) {
                $q->orWhere('attribute_type', 'admin');
            }

            // File types for super-user
            if ($user->hasRole('super-user')) {
                $q->orWhere('attribute_type', 'super-user');
            }

            // File types with no attribute_type (public)
            $q->orWhereNull('attribute_type')
              ->orWhere('attribute_type', 'none');
        });

        return $query;
    }

    public function getAccessibleSubFileTypes(User $user): Builder
    {
        $query = FileType::query()->where('is_active', true)->whereNotNull('parent_id');

        // If user has 'manage all files' permission, return all
        if ($user->hasAnyPermission('manage all files')) {
            \Log::info("User {$user->id} has 'manage all files' permission to file.");
            return $query;
        }

        // Build access conditions based on user's roles
        $query->where(function (Builder $q) use ($user) {
            // File types for growers
            if ($user->hasRole('grower')) {
                $q->orWhere('attribute_type', 'grower');
            }

            // File types for customers
            if ($user->hasRole('customer')) {
                $q->orWhere('attribute_type', 'customer');
            }

            // File types for admin
            if ($user->hasRole('admin')) {
                $q->orWhere('attribute_type', 'admin');
            }

            // File types for super-user
            if ($user->hasRole('super-user')) {
                $q->orWhere('attribute_type', 'super-user');
            }

            // File types with no attribute_type (public)
            $q->orWhereNull('attribute_type')
              ->orWhere('attribute_type', 'none');
        });

        return $query;
    }

}