<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileType extends Model
{
    use HasFactory, SoftDeletes;

    const ATTRIBUTE_TYPE_NONE = 'none';
    const ATTRIBUTE_TYPE_CUSTOMER = 'customer';
    const ATTRIBUTE_TYPE_GROWER = 'grower';
    const ATTRIBUTE_TYPE_ADMIN = 'admin';
    const ATTRIBUTE_TYPE_SUPER_USER = 'super-user';

    const ATTRIBUTE_TYPES = [
        self::ATTRIBUTE_TYPE_NONE,
        self::ATTRIBUTE_TYPE_CUSTOMER,
        self::ATTRIBUTE_TYPE_GROWER,
        self::ATTRIBUTE_TYPE_ADMIN,
        self::ATTRIBUTE_TYPE_SUPER_USER,
    ];

    protected $fillable = [
        'name',
        'description',
        'parent_id',
        'attribute_type', // 'customer', 'grower', 'admin', 'super-user', or 'none'
        'is_active',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    /*
    * count files relationship
    * access via files_count attribute
    */
    public function filesCount(): HasMany
    {
        return $this->hasMany(File::class)->selectRaw('file_type_id, count(*) as aggregate')->groupBy('file_type_id');
    }

    /**
     * Get the user groups associated with this file type.
     */
    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_file_types', 'file_type_id', 'user_group_id');
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
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'file_types');
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
     * Parent file type relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(FileType::class, 'parent_id');
    }

    /**
     * Child file types relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(FileType::class, 'parent_id');
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Check if this is a root (parent) file type
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this file type has children
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get the root parent of this file type
     */
    public function getRoot(): FileType
    {
        if ($this->isRoot()) {
            return $this;
        }
        
        return $this->parent->getRoot();
    }

    public function isCustomerType(): bool
    {
        return $this->attribute_type === 'customer';
    }

    public function isGrowerType(): bool
    {
        return $this->attribute_type === 'grower';
    }

    public function isNoneType(): bool
    {
        return $this->attribute_type === 'none';
    }

    /**
     * Scope to get only root file types
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only child file types
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
     * Scope active file types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}