<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVessel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'vessel_id',
    ];

    // belongs to relationship with document
    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id', 'id');
    }

    // belongs to relationship with vessel
    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id', 'id');
    }
}
