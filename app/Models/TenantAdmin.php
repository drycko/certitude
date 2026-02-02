<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class TenantAdmin extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'can_manage_billing',
        'can_manage_users',
        'can_manage_settings',
        'is_active',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'can_manage_billing' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_manage_settings' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the tenant that owns the admin.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
