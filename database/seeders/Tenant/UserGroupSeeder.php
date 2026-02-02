<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\UserGroup;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class UserGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create user groups that represent common legacy system groups
        $userGroups = [
            [
                'name' => 'admin',
                'display_name' => 'System Administrators',
                'description' => 'Mainain users.\n
                Upload/delete document for either a customer or grower.\n
                Maintain master data',
                'metadata' => [
                    'document_access' => [
                        'document_types' => ['all'],
                        'visibility' => [true, false] // all
                    ],
                    'powerbi_link_access' => true,
                    'restrictions' => [
                        'download_limit' => 10000 // MB per month
                    ]
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view documents by commodity',
                    'download documents',
                    'view powerbi reports',
                    'view by attribute type',
                    'manage users',
                    'manage permissions',
                    'manage master data',
                ]
            ],
            [
                'name' => 'quality_control',
                'display_name' => 'Quality Control',
                'description' => 'Everything that a Admin can do except maintain, summaries, users and roles/permissions.\n
                Can maintain documents, document types and commodities.',
                'metadata' => [
                    'document_access' => [
                        'commodities' => ['all'],
                        'document_types' => ['Quality Reports', 'Certificates', 'Residue COA'],
                        'visibility' => [true, false]
                    ],
                    'powerbi_link_access' => true,
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view private documents',
                    'view documents by commodity',
                    'upload documents',
                    'upload to private space',
                    'download documents',
                ]
            ],
            [
                'name' => 'due_diligence',
                'display_name' => 'Due Diligence',
                'description' => 'Everything that a Admin can do except maintain summaries, users and roles/permissions.\n
                Can maintain documents, document types and commodities.',
                'metadata' => [
                    'document_access' => [
                        'commodities' => ['all'],
                        'document_types' => ['Certificates', 'Residue COA'],
                        'visibility' => [true, false] // Both public and private
                    ],
                    'powerbi_link_access' => true,
                    'restrictions' => [
                        'upload_limit' => 1000, // MB
                        'download_limit' => 5000 // MB per month
                    ]
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view private documents',
                    'view documents by commodity',
                    'upload documents',
                    'upload to private space',
                    'download documents',
                ]
            ],
            [
                'name' => 'summaries',
                'display_name' => 'PowerBI Users',
                'description' => 'Cannot upload documents.\n
                When login, can only see summaries/reports based on assigned commodity types and/or document types.',
                'metadata' => [
                    'document_access' => [
                        'commodities' => ['all'],
                        'document_types' => ['none'],
                        'visibility' => [true, false]
                    ],
                    'powerbi_link_access' => true,
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view private documents',
                    'view documents by commodity',
                    'upload to private space',
                    'download documents',
                    'view powerbi reports',
                    'filter summaries by grower',
                ]
            ],
            [
                'name' => 'auditors',
                'display_name' => 'Auditors',
                'description' => 'External auditors with read-only access',
                'metadata' => [
                    'document_access' => [
                        'document_types' => ['Certificates', 'Quality Reports', 'Environmental COA', 'Residue COA'],
                        'visibility' => [true]
                    ],
                    'powerbi_link_access' => true,
                    'restrictions' => [
                        'read_only' => true
                    ]
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view documents by commodity',
                    'view documents by grower number',
                    'view powerbi reports',
                    'view powerbi reports reports',
                ]
            ]
        ];

        foreach ($userGroups as $groupData) {
            // Create the group
            $group = UserGroup::firstOrCreate([
                'name' => $groupData['name'],
            ], [
                'display_name' => $groupData['display_name'],
                'description' => $groupData['description'],
                'metadata' => $groupData['metadata'],
                'is_active' => true,
                'sort_order' => 0,
            ]);

            // Assign permissions to the group
            if (isset($groupData['permissions'])) {
                $permissions = [];
                foreach ($groupData['permissions'] as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission) {
                        $permissions[] = $permission->name;
                    }
                }
                if (!empty($permissions)) {
                    $group->syncPermissions($permissions);
                }
            }
        }

        // Create legacy system mapping groups (for migration purposes)
        $legacyGroups = [
            [
                'name' => 'legacy_admin_group',
                'display_name' => 'Legacy Admin Group',
                'description' => 'Legacy system admin users',
                'legacy_group_id' => 'admin_legacy',
                'metadata' => [
                    'legacy_mapping' => true,
                    'legacy_permissions' => ['all'],
                ],
                'permissions' => [
                    'manage all documents',
                    'view users',
                    'create users',
                    'edit users',
                    'delete users',
                    'access admin panel',
                    'view admin dashboard',
                    'manage permissions',
                ]
            ],
            [
                'name' => 'legacy_user_group_1',
                'display_name' => 'Legacy User Group 1',
                'description' => 'Legacy system user group 1',
                'legacy_group_id' => 'user_group_1',
                'metadata' => [
                    'legacy_mapping' => true,
                    'document_access' => [
                        'visibility' => [true] // Public only
                    ],
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'download documents',
                ]
            ],
            [
                'name' => 'legacy_user_group_2',
                'display_name' => 'Legacy User Group 2',
                'description' => 'Legacy system user group 2',
                'legacy_group_id' => 'user_group_2',
                'metadata' => [
                    'legacy_mapping' => true,
                    'document_access' => [
                        'visibility' => [true, false] // Both public and private
                    ],
                ],
                'permissions' => [
                    'view documents',
                    'view public documents',
                    'view private documents',
                    'upload documents',
                    'download documents',
                ]
            ]
        ];

        foreach ($legacyGroups as $groupData) {
            $group = UserGroup::firstOrCreate([
                'name' => $groupData['name'],
            ], [
                'display_name' => $groupData['display_name'],
                'description' => $groupData['description'],
                'metadata' => $groupData['metadata'],
                'legacy_group_id' => $groupData['legacy_group_id'],
                'is_active' => true,
                'sort_order' => 999, // Put legacy groups at the end
            ]);

            // Assign permissions to the group - FIXED THIS PART
            if (isset($groupData['permissions'])) {
                $permissions = [];
                foreach ($groupData['permissions'] as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission) {
                        $permissions[] = $permission->name; // Changed from $permission to $permission->name
                    }
                }
                if (!empty($permissions)) {
                    $group->syncPermissions($permissions);
                }
            }
        }
    }
}