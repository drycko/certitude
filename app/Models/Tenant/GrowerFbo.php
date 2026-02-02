<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GrowerFbo extends Model
{
    use HasFactory, SoftDeletes;
    // table 
    protected $table = 'grower_fbos';
    // Fillable attributes
    protected $fillable = [
        'grower_id',
        'fbo_id',
    ];

    // Relationships can be defined here if needed
    public function grower(): BelongsTo
    {
        return $this->belongsTo(Grower::class, 'grower_id');
    }

    public function fbo(): BelongsTo
    {
        return $this->belongsTo(Fbo::class, 'fbo_id');
    }

    // Additional methods or scopes can be added here
}
