<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    // The table associated with the model.
    protected $table = 'help_articles';

    // The attributes that are mass assignable.
    protected $fillable = [
        'title',
        'slug',
        'content',
        'is_active',
        'created_by',
        'updated_by',
    ];

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    
}
