<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\User;
use App\Models\Tenant\Company;
use App\Models\Tenant\Commodity;
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
        $doleCompany = Company::where('name', 'like', 'Dole%')->first();
        $growerCompany = Company::where('name', 'like', '%Grower%')->first();
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

        // Create Dole Admin User
        $admin = User::firstOrCreate([
            'email' => 'admin@dolesa.co.za',
        ], [
            'name' => 'System Administrator',
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@dolesa.co.za',
            'password' => Hash::make('Admin123!'),
            'company_id' => $doleCompany->id,
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

        // Create Grower User
        $grower = User::firstOrCreate([
            'email' => 'grower@example.com',
        ], [
            'name' => 'John Grower',
            'first_name' => 'John',
            'last_name' => 'Grower',
            'password' => Hash::make('Grower123!'),
            'company_id' => $growerCompany->id,
            'grower_number' => 'GRW001',
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'metadata' => [
                'farm_size' => '150 hectares',
                'primary_crop' => 'table_grapes',
                'certification' => 'GlobalGAP'
            ]
        ]);
        $grower->assignRole('grower');
        $grower->commodities()->sync([1, 2]); // Table Grapes and Stone Fruit

        // Create Customer User
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

        // Create Dole User
        $dole = User::firstOrCreate([
            'email' => 'dole@dolesa.co.za',
        ], [
            'name' => 'Dole Manager',
            'first_name' => 'Dole',
            'last_name' => 'Manager',
            'password' => Hash::make('Dole123!'),
            'company_id' => $doleCompany->id,
            'is_active' => true,
            'email_verified_at' => now(),
            'must_change_password' => true,
            'metadata' => [
                'department' => 'Operations',
                'region' => 'Western Cape',
                'responsibilities' => ['quality_control', 'compliance']
            ]
        ]);
        $dole->assignRole('admin');
        $dole->commodities()->sync(Commodity::all());
    }
}