<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'file_type_id',
        'fbo_id',
        'title',
        'filename',
        'original_filename',
        'file_path',
        'file_size',
        'mime_type',
        'is_public',
        'expiry_date',
        'description',
        'uploaded_by',
        'is_active',
        'metadata',
        'legacy_file_id',
        'legacy_path',
        'season_year',
        'sub_file_type_id',
        'container_number',
        'quality_rating', // 'Sound' or 'Unsound'
        'quality_ref_number',
        // 'grower_id', // for now stored in metadata
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'metadata' => 'array',
        'season_year' => 'integer',
        'sub_file_type_id' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fileType(): BelongsTo
    {
        return $this->belongsTo(FileType::class);
    }

    public function fbo(): BelongsTo
    {
        return $this->belongsTo(Fbo::class);
    }

    public function fbos(): BelongsToMany
    {
        return $this->belongsToMany(Fbo::class, 'file_fbo');
    }

    public function subFileType(): BelongsTo
    {
        return $this->belongsTo(FileType::class, 'sub_file_type_id');
    }

    public function commodities(): BelongsToMany
    {
        return $this->belongsToMany(Commodity::class, 'file_commodity');
    }

    public function varieties(): BelongsToMany
    {
        return $this->belongsToMany(Variety::class, 'file_varieties');
    }

    // get grower from metadata grower_id
    public function grower(): ?Grower
    {
        $growerId = $this->metadata['grower_id'] ?? null;
        return $growerId ? Grower::find($growerId) : null;
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function isExpiringSoon($days = 14): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        $diff = $this->expiry_date->diffInDays(now(), false);
        // \Log::info('Checking if file is expiring soon', [
        //     'file_id' => $this->id,
        //     'expiry_date' => $this->expiry_date,
        //     'days' => $diff
        // ]);
        return $this->expiry_date && $diff <= $days && $diff >= 0;
    }

    /**
     * Check if this file was imported from legacy system
     */
    public function isLegacyFile(): bool
    {
        return !is_null($this->legacy_file_id);
    }

    /**
     * Get legacy metadata value by key
     */
    public function getLegacyMetadata($key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Set legacy metadata value
     */
    public function setLegacyMetadata($key, $value): void
    {
        $metadata = $this->metadata ?? [];
        $metadata[$key] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Scope to filter by season year
     */
    public function scopeBySeason($query, $year)
    {
        return $query->where('season_year', $year);
    }

    /**
     * Scope to filter legacy files
     */
    public function scopeLegacy($query)
    {
        return $query->whereNotNull('legacy_file_id');
    }

    /**
     * Scope to filter by sub file type
     */
    public function scopeBySubType($query, $subTypeId)
    {
        return $query->where('sub_file_type_id', $subTypeId);
    }

    /**
     * File activity log (get activity that has table_name 'files' and record_id = this file's id)
     */
    public function userActivities()
    {
        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * File has user activities from users who are not admins and associated with grower(many growers relationship)
     * grower relationship is defined in User model, and we have to track users who are associated with grower_id in file metadata
     */
    public function userActivitiesByGrowerUsers()
    {
        return $this->userActivities()->filter(function ($activity) {
            $user = User::find($activity->user_id);
            if ($user && !$user->hasRole('admin')) {
                $growerId = $this->getLegacyMetadata('grower_id');
                return $user->growers->contains('id', $growerId);
            }
            return false;
        });
    }

    public function userActivityCount(): int
    {
        return $this->activities()->count();
    }

    public function userGroupActivityCount(): int
    {
        return $this->userActivitiesByGrowerUsers()->count();
    }


    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'files');
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
     * File has no activity logs except for creation
     */
    public function hasNoUserActivities(): bool
    {
        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->where('activity_type', '!=', 'create')
            ->doesntExist();
    }

    /**
     * Get files view users
     * access as $file->viewers
     */
    public function getViewersAttribute()
    {
        $userIds = UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->where('activity_type', 'view')
            ->distinct()
            ->pluck('user_id');

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Get files views count
     * access as $file->views_count
     */
    public function getViewsCount(): int
    {
        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->where('activity_type', 'view')
            ->count();
    }

    /**
     * Get files updates count
     */
    public function getUpdatesCount(): int
    {
        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->where('activity_type', 'update')
            ->count();
    }

    /**
     * Get file downloads count
     */
    public function getDownloadsCount(): int
    {
        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->where('activity_type', 'download')
            ->count();
    }

    /**
     * Get last accessed date
     */
    public function getLastAccessedAt(): ?\Illuminate\Support\Carbon
    {
        $lastLog = UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastLog ? $lastLog->created_at : null;
    }

    /**
     * Check if file has been viewed by any grower user
     */
    public function hasGrowerViews(): bool
    {
        $growerUserIds = User::whereHas('roles', function($query) {
            $query->where('name', 'grower');
        })->pluck('id');

        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->whereIn('activity_type', ['view', 'download'])
            ->whereIn('user_id', $growerUserIds)
            ->exists();
    }

    /**
     * Get count of grower views
     */
    public function getGrowerViewsCount(): int
    {
        $growerUserIds = User::whereHas('roles', function($query) {
            $query->where('name', 'grower');
        })->pluck('id');

        return UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->whereIn('activity_type', ['view', 'download'])
            ->whereIn('user_id', $growerUserIds)
            ->count();
    }

    /**
     * Get grower users who have viewed this file
     */
    public function getGrowerViewers()
    {
        $growerUserIds = User::whereHas('roles', function($query) {
            $query->where('name', 'grower');
        })->pluck('id');

        $viewerIds = UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->whereIn('activity_type', ['view', 'download'])
            ->whereIn('user_id', $growerUserIds)
            ->distinct()
            ->pluck('user_id');

        return User::whereIn('id', $viewerIds)->get();
    }

    /**
     * Get last grower view date
     */
    public function getLastGrowerViewAt(): ?\Illuminate\Support\Carbon
    {
        $growerUserIds = User::whereHas('roles', function($query) {
            $query->where('name', 'grower');
        })->pluck('id');

        $lastLog = UserActivity::where('table_name', 'files')
            ->where('record_id', $this->id)
            ->whereIn('activity_type', ['view', 'download'])
            ->whereIn('user_id', $growerUserIds)
            ->orderBy('created_at', 'desc')
            ->first();

        return $lastLog ? $lastLog->created_at : null;
    }

    /**
     * Get distinct vessel names from metadata of all files
     * @return array
     */

    public static function getDistinctVesselNames(): array
    {
        return self::whereNotNull('metadata->vessel_name')
            ->get()
            ->pluck('metadata.vessel_name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }
}
