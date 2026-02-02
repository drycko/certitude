<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CentralRole;
use App\Models\CentralPermission;

class CentralPermissionSeeder extends Seeder
{
    public function run(): void
    {
      // Temporarily override Spatie models to use central 
        $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalRoleClass = $permissionRegistrar->getRoleClass();
        $originalPermissionClass = $permissionRegistrar->getPermissionClass();

        $permissionRegistrar->setRoleClass(CentralRole::class);
        $permissionRegistrar->setPermissionClass(CentralPermission::class);

        // Reset cached roles and permissions
        $permissionRegistrar->forgetCachedPermissions();

        // -------------------------------
        // Central Permissions (that did not get seeded in the DatabaseSeeder)
        // -------------------------------  
      $permissions = [
            'view dashboard', 'view analytics',
            'manage tenants', 'view tenants',
            'manage bookings', 'view bookings',
            'manage rooms', 'view rooms',
            'manage users', 'view users',
            'manage settings', 'view settings',
            'manage subscriptions', 'view subscriptions',
            'manage subscription plans', 'view subscription plans',
            'manage payments', 'view payments',
            'manage reports', 'view reports',
            'manage support', 'view support',
            'manage notifications', 'view notifications',
            'manage audits', 'view audits',
            'manage roles', 'view roles',
            'manage permissions', 'view permissions',
            'manage subscription invoices', 'view subscription invoices', 'download subscription invoices',
            'manage central users', 'view central users',
            'manage central roles', 'view central roles',
            'manage central permissions', 'view central permissions',
            'manage central settings', 'view central settings',
            'manage taxes', 'view taxes',
            'manage coupons', 'view coupons',
            'manage integrations', 'view integrations',
            'manage api keys', 'view api keys',
            'manage audit logs', 'view audit logs',
            'manage system health', 'view system health',
            'manage backups', 'view backups',
            'manage email templates', 'view email templates',
            'manage notifications templates', 'view notifications templates',
            'manage activity logs', 'view activity logs',
            'manage central knowledge base', 'view central knowledge base',
            // trashed
            'view trashed data', 'restore trashed data', 'force delete trashed data',

            // Add any new permissions here(to run seeder: php artisan db:seed --class=CentralPermissionSeeder)
        ];

        foreach ($permissions as $permissionName) {
            CentralPermission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // Create an admin role and assign all permissions to it
        $adminRole = CentralRole::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        // Assign all permissions to the admin role
        $adminRole->syncPermissions(CentralPermission::all());

    }
}