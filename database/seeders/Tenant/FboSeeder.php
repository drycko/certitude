<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Fbo;
use Illuminate\Database\Seeder;

class FboSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create FBOs based on legacy data (first 20 active ones)
        // These will be supplemented with more data via import command later
        $fbos = [
            [
                'code' => 'H0012',
                'name' => 'H0012',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 18, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'H0013',
                'name' => 'H0013',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 19, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'X12345',
                'name' => 'X12345',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 20, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'H0501',
                'name' => 'Apiesklip',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'Apiesklip Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 21, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'A0118',
                'name' => 'Die Heuwel',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'Die Heuwel Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 22, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'L0404',
                'name' => 'Klein Dwarsfontein',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'Klein Dwarsfontein Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 23, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'A1030',
                'name' => 'Klipbank',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'Klipbank Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 24, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'A1005',
                'name' => 'AAA',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'AAA Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 25, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'NL0021',
                'name' => 'NL0021',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 26, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'X0010',
                'name' => 'X0010',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 27, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D6774',
                'name' => 'D6774',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 28, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D1617',
                'name' => 'D1617',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 29, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D5606',
                'name' => 'D5606',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 30, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D5182',
                'name' => 'D5182',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 31, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D5239',
                'name' => 'D5239',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 32, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D1055',
                'name' => 'D1055',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 33, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D12877',
                'name' => 'D12877',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 34, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D12897',
                'name' => 'D12897',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 35, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'D13656',
                'name' => 'D13656',
                'type' => 'PUC',
                'ggn' => null,
                'description' => null,
                'is_active' => true,
                'metadata' => ['legacy_id' => 36, 'imported_from' => 'legacy_system']
            ],
            [
                'code' => 'H0059',
                'name' => 'Twin Oaks',
                'type' => 'PUC',
                'ggn' => null,
                'description' => 'Twin Oaks Production Unit',
                'is_active' => true,
                'metadata' => ['legacy_id' => 37, 'imported_from' => 'legacy_system']
            ]
        ];

        foreach ($fbos as $fbo) {
            Fbo::create($fbo);
        }
    }
}