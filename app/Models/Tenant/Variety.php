<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;


class Variety extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'attributes',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    // belongs to many relationship with files
    public function files()
    {
        return $this->belongsToMany(File::class, 'file_varieties', 'variety_id', 'file_id')
            ->withTimestamps();
    }
}
