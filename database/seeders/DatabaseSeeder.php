<?php
// File: database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\CentralRole;
use App\Models\CentralPermission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's central database.
     */
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
        // Central Permissions (all from CentralPermissionSeeder)
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
        ];
        

        foreach ($permissions as $permissionName) {
            CentralPermission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        // -------------------------------
        // Central Roles
        // -------------------------------
        $supportedRoles = User::SUPPORTED_ROLES;

        foreach ($supportedRoles as $roleName) {
            $role = CentralRole::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            // Assign all permissions to super-admin & super-manager
            if (in_array($roleName, ['super-admin', 'super-manager'])) {
                $role->syncPermissions(CentralPermission::whereIn('name', $permissions)->get());
            }

            // Restrict support role permissions
            if ($roleName === 'support') {
                $supportPermissions = array_diff($permissions, [
                    'manage users',
                    'manage tenants',
                    'manage payments',
                    'manage reports',
                    'manage support',
                    'manage notifications',
                    'manage audits',
                    'manage roles',
                    'manage permissions',
                ]);

                $role->syncPermissions(CentralPermission::whereIn('name', $supportPermissions)->get());
            }
        }

        // -------------------------------
        // Central Users
        // -------------------------------
        $users = [
            [
                'name' => 'Super Admin',
                'email' => 'superadmin@certitude.local',
                'password' => bcrypt('Python@273'),
                'position' => 'Administrator',
                'role' => 'super-admin',
            ],
            [
                'name' => 'Super Manager',
                'email' => 'supermanager@certitude.local',
                'password' => bcrypt('Python@273'),
                'position' => 'Manager',
                'role' => 'super-manager',
            ],
            [
                'name' => 'Support',
                'email' => 'support@certitude.local',
                'password' => bcrypt('Python@273'),
                'position' => 'Support',
                'role' => 'support',
            ],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => $userData['password'],
                    'position' => $userData['position'],
                    'email_verified_at' => now(), // mark as verified
                    'role' => $userData['role'],
                ]
            );

            // Assign central role
            $user->assignRole(CentralRole::where('name', $userData['role'])->first());

            $this->command->info("Created central user: {$userData['email']} with role: {$userData['role']}");
        }

        // -------------------------------
        // Other central seeders
        // -------------------------------
        $this->call([
            CentralSettingSeeder::class,
            SubscriptionPlanSeeder::class,
        ]);

        // -------------------------------
        // Demo tenant with subscription
        // -------------------------------
        $this->call(TenantSeeder::class);

        // -------------------------------
        // Restore original Spatie models (tenant)
        // -------------------------------
        $permissionRegistrar->setRoleClass($originalRoleClass);
        $permissionRegistrar->setPermissionClass($originalPermissionClass);
        $permissionRegistrar->forgetCachedPermissions();

        // run command: php artisan db:seed
    }
}
