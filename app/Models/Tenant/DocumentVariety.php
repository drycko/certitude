<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentVariety extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'document_id',
        'variety_id',
    ];

    // belongs to relationship with document
    public function document()
    {
        return $this->belongsTo(Document::class, 'document_id', 'id');
    }

    // belongs to relationship with variety
    public function variety()
    {
        return $this->belongsTo(Variety::class, 'variety_id', 'id');
    }

}
