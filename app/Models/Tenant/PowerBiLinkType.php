<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PowerbiLinkType extends Model
{
    use HasFactory, SoftDeletes;
    // table
    protected $table = 'powerbi_link_types';
    // allowed attributes to be mass assigned
    const POWER_BI_LINK_TYPE_CUSTOMER = 'customer';
    const POWER_BI_LINK_TYPE_GROWER = 'grower';
    const POWER_BI_LINK_TYPE_NONE = 'none';
    const ATTRIBUTE_TYPES = [
        self::POWER_BI_LINK_TYPE_CUSTOMER,
        self::POWER_BI_LINK_TYPE_GROWER,
        self::POWER_BI_LINK_TYPE_NONE,
    ];
    // Model for Power BI types
    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'attribute_type', // 'customer', 'grower', or 'none'
        'is_active',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function links(): HasMany
    {
        return $this->hasMany(PowerBiLink::class, 'powerbilink_type_id');
    }

    /**
     * Get the user groups associated with this document type.
     */
    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_powerbi_link_types', 'powerbi_link_type_id', 'user_group_id');
    }

    /**
     * Created by relationship
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'powerbi_link_types');
    }

    // delete activities
    public function deleteActivities()
    {
        return $this->activities()->delete();
    }

    // get delete activities
    public function getDeleteActivities()
    {
        return $this->activities()->where('activity_type', 'delete')->get();
    }
    
    // last deleted by user from UserActivity logs
    public function lastDeletedBy()
    {
        $lastActivity = $this->getDeleteActivities()->sortByDesc('created_at')->first();
        return $lastActivity ? User::find($lastActivity->user_id) : null;
    }

    /**
     * Parent powerbi link type relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(PowerBiLinkType::class, 'parent_id');
    }

    /**
     * Child powerbi link types relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(PowerBiLinkType::class, 'parent_id');
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Check if this is a root (parent) powerbi link type
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this powerbi link type has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get the root parent of this powerbi link type
     */
    public function getRoot(): PowerbiLinkType
    {
        if ($this->isRoot()) {
            return $this;
        }
        
        return $this->parent->getRoot();
    }

    /**
     * get the metadata value by key
     */
    public function getMetadataValue(string $key)
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * Scope to get only root powerbi link types
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only child powerbi link types
     */
    public function scopeChildren($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Scope by attribute type
     */
    public function scopeByAttributeType($query, $type)
    {
        return $query->where('attribute_type', $type);
    }

    /**
     * Scope active powerbi link types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

}
