<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileVessel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'file_id',
        'vessel_id',
    ];

    // belongs to relationship with file
    public function file()
    {
        return $this->belongsTo(File::class, 'file_id', 'id');
    }

    // belongs to relationship with vessel
    public function vessel()
    {
        return $this->belongsTo(Vessel::class, 'vessel_id', 'id');
    }
}
