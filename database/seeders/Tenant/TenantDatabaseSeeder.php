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
            UserSeeder::class,                  // Creates users (depends on companies & roles)
            CommoditySeeder::class,             // Creates commodities
            FboSeeder::class,                   // Creates FBOs
            DocumentTypeSeeder::class,          // Creates document types
            UserGroupSeeder::class,             // Creates user groups
            PowerbiTypeSeeder::class,           // Creates PowerBI link types
        ]);

        $this->command->info('Tenant database seeded successfully!');
    }
}
