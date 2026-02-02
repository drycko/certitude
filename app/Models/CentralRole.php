<?php

// File: app/Models/CentralRole.php
namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class CentralRole extends SpatieRole
{
    protected $connection = 'mysql'; // central DB
}

