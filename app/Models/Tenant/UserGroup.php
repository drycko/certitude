<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission;

class UserGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'metadata',
        'is_active',
        'sort_order',
        'created_by',
        'legacy_group_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the document types associated with the user group.
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class, 'user_group_document_types', 'user_group_id', 'document_type_id')
            ->withTimestamps();
    }

    public function powerbiLinkTypes(): BelongsToMany
    {
        return $this->belongsToMany(PowerbiLinkType::class, 'user_group_powerbi_link_types', 'user_group_id', 'powerbi_link_type_id')
            ->withTimestamps();
    }

    /**
     * Get users in this group
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user')
            ->withPivot([
                'group_specific_permissions',
                'group_specific_restrictions',
                'is_primary_group',
                'assigned_at',
                'expires_at'
            ])
            ->withTimestamps()
            ->wherePivot('deleted_at', null);
    }

    /**
     * Get users count for this group
     * @return int
     */
    public function getUsersCountAttribute(): int
    {
        return $this->users()->count();
        // how do show this in the index view with eager loading? -- withCount('users') in the query
    }

    /**
     * Get permissions for this group
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_group_permissions')
            ->withPivot(['is_granted', 'conditions'])
            ->withTimestamps();
    }

    /**
     * Get granted permissions only
     */
    public function grantedPermissions(): BelongsToMany
    {
        return $this->permissions()->wherePivot('is_granted', true);
    }

    /**
     * Get denied permissions
     */
    public function deniedPermissions(): BelongsToMany
    {
        return $this->permissions()->wherePivot('is_granted', false);
    }

    /**
     * Get active groups
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get groups ordered by sort order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Grant permission to this group
     */
    public function givePermissionTo($permission)
    {
        $permissionModel = Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $this->permissions()->syncWithoutDetaching([
                $permissionModel->id => ['is_granted' => true]
            ]);
        }
        return $this;
    }

    /**
     * Revoke permission from this group
     */
    public function revokePermissionTo($permission)
    {
        $permissionModel = Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $this->permissions()->detach($permissionModel->id);
        }
        return $this;
    }

    /**
     * Deny permission for this group (explicit denial)
     */
    public function denyPermissionTo($permission)
    {
        $permissionModel = Permission::where('name', $permission)->first();
        if ($permissionModel) {
            $this->permissions()->syncWithoutDetaching([
                $permissionModel->id => ['is_granted' => false]
            ]);
        }
        return $this;
    }

    /**
     * Check if group has permission
     */
    public function hasPermissionTo($permission)
    {
        return $this->grantedPermissions()
                    ->where('name', $permission)
                    ->exists();
    }

    /**
     * Check if permission is explicitly denied
     */
    public function hasPermissionDenied($permission)
    {
        return $this->deniedPermissions()
                    ->where('name', $permission)
                    ->exists();
    }

    /**
     * Sync permissions to this group
     */
    public function syncPermissions(array $permissions)
    {
        $permissionIds = Permission::whereIn('name', $permissions)->pluck('id', 'name');
        
        $syncData = [];
        foreach ($permissions as $permission) {
            if (isset($permissionIds[$permission])) {
                $syncData[$permissionIds[$permission]] = ['is_granted' => true];
            }
        }
        
        $this->permissions()->sync($syncData);
        return $this;
    }

    /**
     * Get all permissions as array for this group
     */
    public function getPermissionNames()
    {
        return $this->grantedPermissions()->pluck('name')->toArray();
    }

    /**
     * Get all metadata
     */
    public function getMetadata()
    {
        return $this->metadata ?? [];
    }

    /**
     * Get legacy metadata
     */
    public function getLegacyMetadata($key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set legacy metadata
     */
    public function setLegacyMetadata($key, $value)
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Get creator (users who created this group)
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * get is active
     */
    public function isActive()
    {
        return $this->is_active;
    }

    /**
     * Find group by legacy ID
     */
    public static function findByLegacyId($legacyId)
    {
        return static::where('legacy_group_id', $legacyId)->first();
    }
}