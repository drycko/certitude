<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Company;
use App\Models\Tenant\Commodity;
use App\Models\Tenant\Grower;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Runt this seeder after running CompanySeeder: php artisan db:seed --class=CompanySeeder
     */
    public function run(): void
    {
        $devCompany = Company::where('name', 'like', 'Ukuyila%')->first();
        $demoExporterCompany = Company::where('name', 'like', 'Demo Exporter%')->first();
        $growerCompany = Company::where('name', 'like', 'Jan VD Merwe Farms%')->first();
        $customerCompany = Company::where('name', 'like', '%Customer%')->first();

        // Create Super Admin User(me)
        $admin = User::firstOrCreate([
            'email' => 'tino@ukuyila.com',
            'name' => 'System Developer',
        ], [
            'first_name' => 'System',
            'last_name' => 'Developer',
            'password' => Hash::make('Python@273!'),
            'company_id' => $devCompany->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'metadata' => [
                'created_by' => 'system',
                'department' => 'IT',
                'access_level' => 'full'
            ]
        ]);
        $admin->assignRole('super-user');
        $admin->commodities()->sync(Commodity::all());

        // Create Demo Exporter Admin User
        $admin = User::firstOrCreate([
            'email' => 'admin@demoexporter.com',
        ], [
            'name' => 'System Administrator',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@demoexporter.com',
            'password' => Hash::make('Admin123!'),
            'company_id' => $demoExporterCompany->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'metadata' => [
                'created_by' => 'system',
                'department' => 'IT',
                'access_level' => 'full'
            ]
        ]);
        $admin->assignRole('admin');
        $admin->commodities()->sync(Commodity::all());

        // create grower
        $grower = Grower::firstOrCreate([
            'name' => 'Jan VD Merwe Farms',
            'grower_number' => 'GRW001',
            'address' => '123 Farm Lane',
            'contact_person' => 'Jan Van De Merwe',
            'contact_email' => 'jan.vandemerwe@example.com',
            'contact_phone' => '123-456-7890',
            'notes' => 'Sample notes for the grower',
            'created_by' => 1,
        ]);

        // Create Grower User
        $growerUser = User::firstOrCreate([
            'email' => 'grower@vandemerwefarms.com',
        ], [
            'name' => 'Jan Van De Merwe',
            'first_name' => 'Jan',
            'last_name' => 'Van De Merwe',
            'password' => Hash::make('Grower123!'),
            'company_id' => $growerCompany->id,
            // 'grower_number' => 'GRW001',
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'metadata' => [
                'farm_size' => '150 hectares',
                'primary_crop' => 'table_grapes',
                'certification' => 'GlobalGAP'
            ]
        ]);
        $growerUser->assignRole('grower');
        $growerUser->commodities()->sync([1, 2]); // Table Grapes and Stone Fruit
        // insert into pivot table user_grower
        $growerUser->growers()->sync([$grower->id]);

        // Create Customer User (outside access to demo exporter guest portal)
        $customer = User::firstOrCreate([
            'email' => 'customer@example.com',
        ], [
            'name' => 'Jane Customer',
            'first_name' => 'Jane',
            'last_name' => 'Customer',
            'password' => Hash::make('Customer123!'),
            'company_id' => $customerCompany->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'metadata' => [
                'business_type' => 'retailer',
                'annual_volume' => '500 tons',
                'markets' => ['domestic', 'export']
            ]
        ]);
        $customer->assignRole('customer');
        $customer->commodities()->sync([1, 3]); // Table Grapes and Citrus

        // Create exporter guest User
        $demoExporter = User::firstOrCreate([
            'email' => 'user@guest.com',
        ], [
            'name' => 'Guest User',
            'first_name' => 'Guest',
            'last_name' => 'User',
            'password' => Hash::make('DemoExporter123!'),
            'company_id' => $demoExporterCompany->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'metadata' => [
                'department' => 'Operations',
                'region' => 'Western Cape',
                'responsibilities' => ['quality_control', 'compliance']
            ]
        ]);
        $demoExporter->assignRole('admin');
        $demoExporter->commodities()->sync(Commodity::all());
    }
}