<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Vessel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    // belongs to many relationship with documents
    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_vessels', 'vessel_id', 'document_id')
            ->withTimestamps();
    }
}
