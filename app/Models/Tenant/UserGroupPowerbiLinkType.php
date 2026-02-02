<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class UserGroupPowerBiLinkType extends Model
{
    // table
    protected $table = 'user_group_powerbi_link_types';
    // Model for pivot table between UserGroup and PowerBiLinkType
    protected $fillable = [
        'user_group_id',
        'powerbi_link_type_id',
        'is_granted', // Allow explicit denials
        'conditions', // Conditional permissions
    ];

    protected $casts = [
        'is_granted' => 'boolean',
        'conditions' => 'array',
    ];

    public $timestamps = true;

    public function userGroup()
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id');
    }

    public function powerBiLinkType()
    {
        return $this->belongsTo(PowerBiLinkType::class, 'powerbi_link_type_id');
    }
}
