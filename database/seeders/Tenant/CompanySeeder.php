<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'N/A',
                'code' => 'NA0000',
                'address' => 'N/A',
                'contact_person' => 'System',
                'email' => 'system@ukuyila.com',
                'phone' => 'N/A',
                'is_active' => true,
                'is_system_default' => true,
            ],
            [
                'name' => 'Ukuyila Consult',
                'address' => 'Mossel Bay, South Africa',
                'contact_person' => 'System Developer',
                'email' => 'consult@ukuyila.com',
                'phone' => '+27 21 765 4321',
                'is_active' => true,
                'is_system_default' => false,
            ],
            [
                'name' => 'Demo Exporter',
                'address' => 'Cape Town, South Africa',
                'contact_person' => 'System Administrator',
                'email' => 'admin@demoexporter.com',
                'phone' => '+27 21 123 4567',
                'is_active' => true,
                'is_system_default' => false,
            ],
            [
                'name' => 'Jan VD Merwe Farms',
                'address' => 'Western Cape, South Africa',
                'contact_person' => 'Grower Manager',
                'email' => 'grower@vandemerwefarms.com',
                'phone' => '+27 21 987 6543',
                'is_active' => true,
                'is_system_default' => false,
            ],
            [
                'name' => 'Sample Customer Company',
                'address' => 'Johannesburg, South Africa',
                'contact_person' => 'Customer Manager',
                'email' => 'customer@example.com',
                'phone' => '+27 11 123 4567',
                'is_active' => true,
                'is_system_default' => false,
            ],
        ];

        foreach ($companies as $company) {
            if (!isset($company['code'])) {
                $company['code'] = Company::generateCompanyCodeFromName($company['name']);
            }
            Company::firstOrCreate(['name' => $company['name']], $company);
        }
    }
}