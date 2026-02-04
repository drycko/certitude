<?php

namespace App\Models\Tenant;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
	/** @use HasFactory<\Database\Factories\UserFactory> */
	use HasFactory, Notifiable, HasRoles, SoftDeletes;
	
	/**
	* The attributes that are mass assignable.
	*
	* @var list<string>
	*/
	protected $fillable = [
		'name',
		'email',
		'password',
		'company_id',
		'grower_number',
		'first_name',
		'last_name',
		'phone',
		'is_active',
		'password_changed_at',
		'must_change_password',
		'metadata',
		'profile_photo_url',
		'legacy_user_id',
		'created_by',
	];
	
	/**
	* The attributes that should be hidden for serialization.
	*
	* @var list<string>
	*/
	protected $hidden = [
		'password',
		'remember_token',
	];
	
	/**
	* Get the attributes that should be cast.
	*
	* @return array<string, string>
	*/
	protected function casts(): array
	{
		return [
			'email_verified_at' => 'datetime',
			'password_changed_at' => 'datetime',
			'password' => 'hashed',
			'is_active' => 'boolean',
			'must_change_password' => 'boolean',
			'metadata' => 'array',
		];
	}

	// created by user
	public function creator(): BelongsTo
	{
		return $this->belongsTo(User::class, 'created_by');
	}

	// company this user belongs to
	public function company(): BelongsTo
	{
		return $this->belongsTo(Company::class);
	}
	// files uploaded by this user (uploaded_by column)
	public function files(): HasMany
	{
		return $this->hasMany(File::class, 'uploaded_by');
	}
	
	public function commodities(): BelongsToMany
	{
		return $this->belongsToMany(Commodity::class, 'user_commodity')
		->withPivot([
			'legacy_commodity_type_id',
			'legacy_status_id', 
			'legacy_created_date',
			'legacy_last_updated'
		])
		->withTimestamps();
	}
	
	public function fbos(): BelongsToMany
	{
		return $this->belongsToMany(Fbo::class, 'user_fbo');
	}

	public function getFbosList()
	{
		return $this->fbos()->pluck('name', 'id')->toArray();
	}

	public function growers(): BelongsToMany
	{
		return $this->belongsToMany(Grower::class, 'grower_users', 'user_id', 'grower_id')
		->withTimestamps();
	}
	
	public function userGroups(): BelongsToMany
	{
		return $this->belongsToMany(UserGroup::class, 'user_group_user')
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
	
	public function activeUserGroups(): BelongsToMany
	{
		return $this->userGroups()
		->where('user_groups.is_active', true)
		->where(function($query) {
			$query->whereNull('user_group_user.expires_at')
			->orWhere('user_group_user.expires_at', '>', now());
		});
	}
	
	public function primaryUserGroup(): ?UserGroup
	{
		return $this->userGroups()
		->wherePivot('is_primary_group', true)
		->first();
	}
	
	public function getFullNameAttribute(): string
	{
		return trim($this->first_name . ' ' . $this->last_name) ?: $this->name;
	}
	
	/**
	* Get the user's profile photo url.
	*/
	public function getProfilePhotoUrlAttribute(): string
	{
		$photo = $this->attributes['profile_photo_url'] ?? null;
		if (!$photo) {
			return $this->defaultProfilePhotoUrl();
		}
		
		if (config('app.env') === 'production') {
			$gcsConfig = config('filesystems.disks.gcs');
			$bucket = $gcsConfig['bucket'] ?? null;
			$path = ltrim($photo, '/');
			return $bucket ? "https://storage.googleapis.com/{$bucket}/{$path}" : asset('storage/' . $photo);
		} else {
			return asset('storage/' . $photo);
		}
	}
	// public function getProfilePhotoUrlAttribute(): string
	// {
	//     if (!$this->profile_photo_url) {
	//         return $this->defaultProfilePhotoUrl();
	//     }
	
	//     // Handle different storage configurations
	//     if (config('app.env') === 'production') {
	//         // For production with GCS or other cloud storage
	//         $gcsConfig = config('filesystems.disks.gcs');
	//         $bucket = $gcsConfig['bucket'] ?? null;
	//         $path = ltrim($this->profile_photo_url, '/');
	//         return $bucket ? "https://storage.googleapis.com/{$bucket}/{$path}" : asset('storage/' . $this->profile_photo_url);
	//     } else {
	//         // For local development - just use asset helper
	//         return asset('storage/' . $this->profile_photo_url);
	//     }
	// }
	
	// public function role(): ?string
	// {
	//     return $this->roles->first()?->name ?? null;
	// }
	
	public function isSuperUserRole(): bool
	{
		return $this->hasRole('super-user');
	}
	
	public function isAdminRole(): bool
	{
		return $this->hasRole('admin');
	}
	
	public function isSupervisorRole(): bool
	{
		return $this->hasRole('supervisor');
	}
	
	public function isDoleRole(): bool
	{
		return $this->hasRole('dole');
	}
	
	public function isGrowerRole(): bool
	{
		return $this->hasRole('grower');
	}
	/**
	 *  check if user is customer role
	 * Usa
	 */
	public function isCustomerRole(): bool
	{
		return $this->hasRole('customer');
	}

	public function markEmailAsVerified(): void
	{
		$this->email_verified_at = now();
		$this->save();
	}

	public function hasVerifiedEmail(): bool
	{
		return !is_null($this->email_verified_at);
	}
	
	/**
	* Check if this user was imported from legacy system
	*/
	public function isLegacyUser(): bool
	{
		return !is_null($this->legacy_user_id);
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
	* Scope to filter legacy users
	*/
	public function scopeLegacy($query)
	{
		return $query->whereNotNull('legacy_user_id');
	}
	
	/**
	* Find user by legacy ID
	*/
	public static function findByLegacyId($legacyId)
	{
		return static::where('legacy_user_id', $legacyId)->first();
	}
	
	/**
	* Assign user to a group
	*/
	// public function assignToGroup(UserGroup $group, bool $isPrimary = false, array $specificPermissions = null, \DateTime $expiresAt = null)
	public function assignToGroup(UserGroup $group, bool $isPrimary = false, ?array $specificPermissions = null, ?\DateTime $expiresAt = null)
	{
		$this->userGroups()->syncWithoutDetaching([
			$group->id => [
				'group_specific_permissions' => $specificPermissions ? json_encode($specificPermissions) : null,
				'is_primary_group' => $isPrimary,
				'assigned_at' => now(),
				'expires_at' => $expiresAt,
			]
		]);
		
			// If this is being set as primary, remove primary flag from others
		if ($isPrimary) {
			$this->userGroups()
			->wherePivot('user_group_id', '!=', $group->id)
			->updateExistingPivot($this->userGroups()->wherePivot('user_group_id', '!=', $group->id)->get(), [
				'is_primary_group' => false
			]);
		}
		
		return $this;
	}

	/**
	* Remove user from group
	*/
	public function removeFromGroup(UserGroup $group)
	{
		$this->userGroups()->detach($group->id);
		return $this;
	}
	
	/**
	* Check if user belongs to group
	*/
	public function belongsToGroup($groupName)
	{
		if ($groupName instanceof UserGroup) {
			$groupName = $groupName->name;
		}
		
		return $this->activeUserGroups()
		->where('user_groups.name', $groupName)
		->exists();
	}
	
	/**
	* Get all permissions from roles and groups combined
	*/
	public function getAllPermissions()
	{
		// Get permissions from Spatie roles
		$rolePermissions = $this->getPermissionsViaRoles();
		
		// Get permissions from user groups
		$groupPermissions = collect();
		foreach ($this->activeUserGroups as $group) {
			$groupPermissions = $groupPermissions->merge($group->grantedPermissions);
		}
		
		// Get direct permissions
		$directPermissions = $this->getDirectPermissions();
		
		// Combine and remove duplicates
		return $rolePermissions->merge($groupPermissions)->merge($directPermissions)->unique('id');
	}
	
	/**
	* Check if user has permission through any source (role, group, or direct)
	*/
	public function hasAnyPermission($permission)
	{
		// Check if permission is explicitly denied by any group
		foreach ($this->activeUserGroups as $group) {
			if ($group->hasPermissionDenied($permission)) {
				return false; // Explicit denial overrides everything
			}
		}
		
		// Check through Spatie's normal permission system (roles + direct)
		if ($this->hasPermissionTo($permission)) {
			return true;
		}
		
		// Check through user groups
		foreach ($this->activeUserGroups as $group) {
			if ($group->hasPermissionTo($permission)) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	* Get permissions that are explicitly denied
	*/
	public function getDeniedPermissions()
	{
		$deniedPermissions = collect();
		
		foreach ($this->activeUserGroups as $group) {
			$deniedPermissions = $deniedPermissions->merge($group->deniedPermissions);
		}
		
		return $deniedPermissions->unique('id');
	}
	
	/**
	* Get user's group names
	*/
	public function getGroupNames()
	{
		return $this->activeUserGroups->pluck('name')->toArray();
	}
	
	/**
	* Sync user groups
	*/
	// public function syncGroups(array $groupIds, array $primaryGroupId = null)
	public function syncGroups(array $groupIds, ?array $primaryGroupId = null)
	{
		$syncData = [];
		foreach ($groupIds as $groupId) {
			$syncData[$groupId] = [
				'is_primary_group' => $primaryGroupId && in_array($groupId, $primaryGroupId),
				'assigned_at' => now(),
			];
		}
		
		$this->userGroups()->sync($syncData);
		return $this;
	}
	
	/**
	* Get all UserActivity logs made by this user.
	*/
	public function activityLogs()
	{
		return $this->hasMany(UserActivity::class);
	}

	/**
	* Get all notifications for the user
	*/
	public function notifications(): HasMany
	{
		return $this->hasMany(UserNotification::class);
	}

	/**
	* Get a specific notification by ID
	*/
	public function notification($id): HasOne
	{
		return $this->hasOne(UserNotification::class)->where('id', $id);
	}

	/**
	 * Unread notifications
	 */
	public function unreadNotifications(): HasMany
	{
		return $this->hasMany(UserNotification::class)->where('is_read', false);
	}

	/**
	* Get last login time from activity logs
	*/
	public function lastLogin()
	{
		return $this->activityLogs()
		->where('activity_type', 'login')
		->latest()
		->value('created_at');
	}

	public function getLastLoginAtAttribute()
	{
		return $this->lastLogin();
	}
	
	/**
	* Get the default profile photo URL if no profile photo has been uploaded.
	*/
	protected function defaultProfilePhotoUrl()
	{
		return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
	}

	/**
	 * Activities by admin users (for audit logs)
	 */
	public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'users');
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
     * file has no activity logs except for creation
     */
    public function hasNoUserActivities(): bool
    {
        return UserActivity::where('table_name', 'users')
            ->where('record_id', $this->id)
            ->where('activity_type', '!=', 'create')
            ->doesntExist();
    }

    /**
     * Get file views count
     */
    public function getViewsCount(): int
    {
        return UserActivity::where('table_name', 'users')
            ->where('record_id', $this->id)
            ->where('activity_type', 'view')
            ->count();
    }
}
