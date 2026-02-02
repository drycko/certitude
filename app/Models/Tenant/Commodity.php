<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commodity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'color_code',
        'icon_code',
        'metadata',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_commodity');
    }

    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(Document::class, 'document_commodity');
    }

    public function growers(): BelongsToMany
    {   
        // use created_at and updated_at timestamps on pivot table only
        return $this->belongsToMany(Grower::class, 'grower_commodities', 'commodity_id', 'grower_id')->withTimestamps();
    }

    public function countDocuments()
    {
        return $this->documents()->count();
    }
}