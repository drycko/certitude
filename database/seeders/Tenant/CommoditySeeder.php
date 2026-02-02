<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Commodity;
use Illuminate\Database\Seeder;

class CommoditySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $commodities = [
            [
                'name' => 'Table Grapes',
                'code' => 'TG',
                'description' => 'Fresh table grapes for export and local markets',
                'is_active' => true,
            ],
            [
                'name' => 'Stone Fruit',
                'code' => 'SF',
                'description' => 'Peaches, plums, nectarines, and apricots',
                'is_active' => true,
            ],
            [
                'name' => 'Citrus',
                'code' => 'CT',
                'description' => 'Oranges, lemons, grapefruits, and limes',
                'is_active' => true,
            ],
            [
                'name' => 'Sub-Tropical',
                'code' => 'ST',
                'description' => 'Avocados, mangoes, and other sub-tropical fruits',
                'is_active' => true,
            ],
            [
                'name' => 'Berries',
                'code' => 'BR',
                'description' => 'Blueberries, strawberries, and raspberries',
                'is_active' => true,
            ],
            [
                'name' => 'Pome Fruit',
                'code' => 'PF',
                'description' => 'Apples and pears',
                'is_active' => true,
            ]
        ];

        foreach ($commodities as $commodity) {
            Commodity::create($commodity);
        }
    }
}