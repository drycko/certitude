<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class GrowerUser extends Model
{
    // Define the table name if it doesn't follow Laravel's naming convention
    protected $table = 'grower_users';
    // Fillable attributes
    protected $fillable = [
        'grower_id',
        'user_id',
    ];

    // Relationships can be defined here if needed
    public function grower()
    {
        return $this->belongsTo(Grower::class, 'grower_id');
}

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    // Additional methods or scopes can be added here
}