<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\PowerbiType;
use Illuminate\Database\Seeder;

class PowerbiTypeSeeder extends Seeder
{
  /**
   * Run the database seeds.
   */
  public function run(): void
  {
    // Create root Power BI types first
    $rootTypes = [
      [
        'name' => 'Sales',
        'description' => 'Sales data and reports',
        'attribute_type' => 'customer',
        'is_active' => true,
        'metadata' => [],
      ],
      [
        'name' => 'Marketing',
        'description' => 'Marketing data and reports',
        'attribute_type' => 'grower',
        'is_active' => true,
        'metadata' => [],
      ],
    ];

    foreach ($rootTypes as $typeData) {
      PowerbiType::create($typeData);
    }
  }
}
