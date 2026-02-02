<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccessRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'message',
        'ip',
        'user_agent',
        'status',
        'additional_data',
    ];

    protected $casts = [
        'additional_data' => 'array',
    ];
}
