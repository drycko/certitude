<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SummaryLink extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'powerbi_links';

    protected $fillable = [
        'grower_id',
        'name',
        'description',
        'url',
        'is_active',
        'created_by',
        'added_by',
        'powerbi_link_type_id',
        'link_source', // use for either 'powerbi' or 'other'
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function powerbiLinkType(): BelongsTo
    {
        return $this->belongsTo(PowerBiLinkType::class, 'powerbi_link_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function grower(): BelongsTo
    {
        return $this->belongsTo(Grower::class);
    }

    /**
     * Will create pivot tables for user groups and powerbi link types
     */

    public function getObfuscatedIdAttribute(): string
    {
        return base64_encode($this->id . ':' . $this->created_at->timestamp);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class, 'record_id')->where('table_name', 'powerbi_links');
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
}
