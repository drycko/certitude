<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class GrowerCommodity extends Model
{
    // The table associated with the model.
    protected $table = 'grower_commodities';
    protected $fillable = ['grower_id', 'commodity_id'];

    // Relationships
    public function grower()
    {
        return $this->belongsTo(Grower::class);
    }

    public function commodity()
    {
        return $this->belongsTo(Commodity::class);
    }
}
