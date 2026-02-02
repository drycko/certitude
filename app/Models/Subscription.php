<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'tenant_id',
        'subscription_plan_id',
        'price',
        'billing_cycle',
        'start_date',
        'end_date',
        'trial_ends_at',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'trial_ends_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns the subscription.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Get the subscription plan.
     */
    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Get invoices for this subscription.
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive()
    {
        return $this->status === 'active' && $this->end_date > now();
    }

    /**
     * Check if subscription is on trial.
     */
    public function isOnTrial()
    {
        return $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at > now();
    }
}
