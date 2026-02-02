<?php

// File: app/Models/CentralPermission.php
namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class CentralPermission extends SpatiePermission
{
    protected $connection = 'mysql'; // central DB
}
