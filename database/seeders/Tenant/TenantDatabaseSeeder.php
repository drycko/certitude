<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\User;
use App\Models\Tenant\Company;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding tenant database...');

        // Seed in order: foundational data first, then dependent data
        $this->call([
            RolesAndPermissionsSeeder::class,  // Must be first - creates roles & permissions
            CompanySeeder::class,               // Creates companies
            CommoditySeeder::class,             // Creates commodities (needed by users)
            FboSeeder::class,                   // Creates FBOs
            UserSeeder::class,                  // Creates users (depends on companies, roles & commodities)
            FileTypeSeeder::class,          // Creates file types
            UserGroupSeeder::class,             // Creates user groups
            PowerbiLinkTypeSeeder::class,           // Creates PowerBI link types
        ]);

        $this->command->info('Tenant database seeded successfully!');
    }
}
