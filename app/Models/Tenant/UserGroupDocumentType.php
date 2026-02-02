<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class UserGroupDocumentType extends Model
{
    use HasFactory, SoftDeletes;
    // table name
    protected $table = 'user_group_document_types';
    // Define fillable attributes if needed
    protected $fillable = [
        'user_group_id',
        'document_type_id',
    ];

    // Define relationships if needed
    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class);
    }

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }
}
