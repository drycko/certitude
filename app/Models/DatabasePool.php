<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatabasePool extends Model
{
    protected $table = 'database_pool';

    protected $fillable = [
        'database_name',
        'is_available',
        'assigned_to_tenant',
        'assigned_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the tenant that owns this database.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'assigned_to_tenant');
    }

    /**
     * Scope to get only available databases.
     */
    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->whereNull('assigned_to_tenant');
    }
}
