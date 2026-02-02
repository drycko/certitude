<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, softDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'phone_number',
        'website',
        'industry',
        'number_of_employees',
        'created_by',
        'company_logo_url',
        'contact_person',
        'email',
        'phone',
        'is_active',
        'is_system_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system_default' => 'boolean',
        'number_of_employees' => 'integer',
        'created_by' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'company_id');
    }

    public function powerBiLinks(): HasMany
    {
        return $this->hasMany(PowerBiLink::class, 'company_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'companies');
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
     * Generate company code (access staticly if needed)
     */
    public function generateCompanyCode(): string
    {
        $code = strtoupper(substr($this->name, 0, 3)) . str_pad($this->id, 4, '0', STR_PAD_LEFT);
        // check if code is unique
        while ($this->where('code', $code)->exists()) {
            $code = strtoupper(substr($this->name, 0, 3)) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    public static function generateCompanyCodeFromName(string $name): string
    {
        // Remove spaces and special characters, keep only alphanumeric
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $name);
        $code = strtoupper(substr($cleanName, 0, 3)) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        // check if code is unique
        while (self::where('code', $code)->exists()) {
            $code = strtoupper(substr($cleanName, 0, 3)) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        return $code;
    }

    /**
     * Get the default N/A company
     */
    public static function getDefaultCompany()
    {
        return self::where('code', 'NA0000')->first();
    }

    /**
     * Check if this is the system default company
     */
    public function isSystemDefault(): bool
    {
        return $this->is_system_default === true || $this->code === 'NA0000';
    }

    /**
	* Get the default logo image URL if no logo image has been uploaded.
	*/
	protected function defaultLogoImageUrl()
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
	* Get the company's logo image url.
	*/
	// public function getCompanyLogoUrlAttribute(): string
	// {
	// 	$logo = $this->attributes['company_logo_url'] ?? null;
	// 	if (!$logo) {
	// 		return $this->defaultLogoImageUrl();
	// 	}

	// 	if (config('app.env') === 'production') {
	// 		$gcsConfig = config('filesystems.disks.gcs');
	// 		$bucket = $gcsConfig['bucket'] ?? null;
	// 		$path = ltrim($logo, '/');
	// 		return $bucket ? "https://storage.googleapis.com/{$bucket}/{$path}" : asset('storage/' . $logo);
	// 	} else {
	// 		return asset('storage/' . $logo);
	// 	}
	// }
}
