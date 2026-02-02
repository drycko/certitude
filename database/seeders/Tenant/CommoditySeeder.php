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
                'color_code' => '#6F2DA8',
                'icon_code' => 'fa fa-solid fa-wine-glass',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Stone Fruit',
                'code' => 'SF',
                'description' => 'Peaches, plums, nectarines, and apricots',
                'color_code' => '#FFA07A',
                'icon_code' => 'fa fa-solid fa-cherry',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Citrus',
                'code' => 'CT',
                'description' => 'Oranges, lemons, grapefruits, and limes',
                'color_code' => '#FFD700',
                'icon_code' => 'fa fa-solid fa-lemon',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Sub-Tropical',
                'code' => 'ST',
                'description' => 'Avocados, mangoes, and other sub-tropical fruits',
                'color_code' => '#228B22',
                'icon_code' => 'fa fa-solid fa-apple-whole',
                'is_active' => true,
                'sort_order' => 4,

            ],
            [
                'name' => 'Berries',
                'code' => 'BR',
                'description' => 'Blueberries, strawberries, and raspberries',
                'color_code' => '#FF69B4',
                'icon_code' => 'fa fa-solid fa-berry',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Pome Fruit',
                'code' => 'PF',
                'description' => 'Apples and pears',
                'color_code' => '#FF4500',
                'icon_code' => 'fa fa-solid fa-apple-alt',
                'is_active' => true,
                'sort_order' => 6,
            ]
        ];

        foreach ($commodities as $commodity) {
            Commodity::create($commodity);
        }
    }
}