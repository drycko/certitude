<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grower extends Model
{
    use HasFactory, SoftDeletes;
    // Fillable attributes
    protected $fillable = [
        'name',
        'grower_number',
        'address',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
        'created_by',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fbos()
    {
        return $this->belongsToMany(Fbo::class, 'grower_fbos', 'grower_id', 'fbo_id')->withTimestamps();
    }

    public function commodities()
    {
        return $this->belongsToMany(Commodity::class, 'grower_commodities', 'grower_id', 'commodity_id')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'grower_users', 'grower_id', 'user_id')->withTimestamps();
    }

    // Additional methods or scopes can be added here

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
