<?php
// database/seeders/tenant/RolesAndPermissionsSeeder.php
namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
// use Spatie\Permission\Models\Role;
// use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Config;
use App\Models\Tenant\Role;
use App\Models\Tenant\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure the models are using the tenant connection
        $permissionRegistrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $originalRoleClass = $permissionRegistrar->getRoleClass();
        $originalPermissionClass = $permissionRegistrar->getPermissionClass();

        $permissionRegistrar->setRoleClass(Role::class);
        $permissionRegistrar->setPermissionClass(Permission::class);

        // Reset cached roles/permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Wipe old tenant data to avoid FK mismatch
        // \DB::connection('tenant')->table('role_has_permissions')->truncate();
        // \DB::connection('tenant')->table('permissions')->truncate();
        // \DB::connection('tenant')->table('roles')->truncate();

        // Create permissions
        $permissions = [
            // file permissions
            'view files',
            'edit files',
            'upload files',
            'download files',
            'delete files',
            'manage all files',
            'bulk delete files',
            'view public files',
            'view private files',
            'view files by commodity',
            'view files by grower',
            
            // User management permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            'impersonate users',
            'stop impersonation',
            'resend user verification',
            'resend user details',
            'manage user files',
            'toggle user groups',
            'toggle user commodities',
            'view all users',
            
            // Master data permissions
            // file types
            'create file types',
            'edit file types',
            'view file types',
            'delete file types',
            // summary type
            'create summary types',
            'edit summary types',
            'view summary types',
            'delete summary types',
            
            // Admin permissions
            'access admin panel',
            'view admin dashboard',
            'manage permissions',
            'assign permissions',
            'view permission list',
            
            // File management permissions
            'view file index',
            'upload files',
            'save files',
            'delete files',
            'bulk delete files',
            'toggle file commodities',
            'view file details',
            'manage file sub types',
            'file upload to admin',
            'file upload to user',
            
            // User interface permissions
            'view user dashboard',
            'change password',
            
            // Role-specific permissions
            'upload to private space',
            'view by attribute type',
            'filter by file type',
            'manage user provinces',
            'manage user countries',
            'manage user tags',
            
            // Group management permissions
            'view user groups',
            'create user groups',
            'edit user groups',
            'delete user groups',
            'assign user groups',
            'manage group permissions',
            'view group members',
            
            // Enhanced permission tracking
            'delete own files',
            'view permission history',
            'export user data',
            'import user data',

            // roles management permissions
            'view roles',
            'create roles',
            'edit roles',
            'delete roles',
            'assign roles',

            // permissions management permissions
            'view permissions',
            'create permissions',
            'edit permissions',
            'delete permissions',

            // grower management permissions
            'view growers',
            'create growers',
            'edit growers',
            'delete growers',
            'assign growers',
            'manage all growers',

            // activity log permissions
            'view activity logs',
            'clear activity logs',
            'export activity logs',
            'import activity logs',

            // fbo management permissions
            'view fbos',
            'create fbos',
            'edit fbos',
            'delete fbos',
            'assign fbos',
            'manage all fbos',

            // commodity management permissions
            'view commodities',
            'create commodities',
            'edit commodities',
            'delete commodities',
            'assign commodities',

            // variety management permissions
            'view varieties',
            'create varieties',
            'edit varieties',
            'delete varieties',
            'assign varieties',

            // vessel management permissions
            'view vessels',
            'create vessels',
            'edit vessels',
            'delete vessels',
            'assign vessels',

            // PowerBI Reports permissions
            'view powerbi reports',
            'create powerbi reports',
            'edit powerbi reports',
            'delete powerbi reports',
            'manage powerbi reports',
            'manage all powerbi reports',
            'view powerbi reports by grower',
            'filter powerbi reports by group',

            // company management permissions
            'view companies',
            'create companies',
            'edit companies',
            'delete companies',
            'assign companies',

            // trashed items permissions
            'view trashed items',
            'restore trashed items',
            'force delete trashed items',

        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'tenant'
            ]);
        }

        // Create roles and assign permissions based on business requirements
        // Super user role with all permissions
        $superRole = Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'tenant']);
        $superRole->syncPermissions(Permission::all());
        // give super-user role to the first user (assumed to be admin)
        // $adminUser = User::first();
        // $adminUser->assignRole($superRole);

        // Admin Role
        // - Main users: Upload/delete file for either a customer or grower
        // - Maintain master data
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'tenant']);
        $adminRole->syncPermissions(Permission::all());
               
        // Grower Role
        // - Grower number part of registration
        // - Can see PBI via unique identifier link (hide URL)
        // - DMS: Check assigned commodity types
        // - Show all public docs for those commodity types
        // - Show all private docs based on assigned growers
        // - Only access grower portal with limited rights
        $growerRole = Role::firstOrCreate(['name' => 'grower', 'guard_name' => 'tenant']);
        $growerRole->syncPermissions([
            'view files',
            'view public files',
            'view private files',
            'view files by commodity',
            'view files by grower',
            'download files',
            'view powerbi reports',
            // 'filter powerbi reports by grower',
            'view user dashboard',
            'change password',
            'save files',
            'view file index',
            'view file details',
        ]);

        // Customer Role
        // - Cannot upload files
        // - Must have one or more Commodity Type
        // - Can see any file uploaded as a Customer based on Commodity types assigned
        // - Can belong to multiple user groups for enhanced access
        // - Only access customer portal with limited rights
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'tenant']);
        $customerRole->syncPermissions([
            // 'view files',
            'view public files',
            'view files by commodity',
            'view by attribute type',
            'download files',
            'view powerbi reports',
            'view user dashboard',
            'change password',
            'view file index',
            'view file details',
        ]);
    }
}