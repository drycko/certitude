<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileVariety extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'file_id',
        'variety_id',
    ];

    // belongs to relationship with file
    public function file()
    {
        return $this->belongsTo(File::class, 'file_id', 'id');
    }

    // belongs to relationship with variety
    public function variety()
    {
        return $this->belongsTo(Variety::class, 'variety_id', 'id');
    }

}
