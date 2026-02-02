<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'setup_price',
        'monthly_price',
        'yearly_price',
        'features',
        'max_users',
        'support_hours',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'setup_price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get tenants using this plan
     */
    public function tenants()
    {
        return $this->hasMany(Tenant::class, 'plan', 'slug');
    }

    /**
     * Check if plan has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? []);
    }

    /**
     * Scope for active plans only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default plan
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
