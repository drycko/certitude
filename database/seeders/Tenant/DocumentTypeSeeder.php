<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create root document types first
        $rootTypes = [
            [
                'name' => 'Certificates',
                'description' => 'Various certification documents',
                'attribute_type' => 'none',
                'is_active' => true,
                'metadata' => ['category' => 'root', 'icon' => 'certificate']
            ],
            [
                'name' => 'Quality Documents',
                'description' => 'Quality assessment and inspection documents',
                'attribute_type' => 'none',
                'is_active' => true,
                'metadata' => ['category' => 'root', 'icon' => 'quality']
            ],
            [
                'name' => 'Compliance Documents',
                'description' => 'Regulatory and compliance documentation',
                'attribute_type' => 'none',
                'is_active' => true,
                'metadata' => ['category' => 'root', 'icon' => 'compliance']
            ],
        ];

        $createdRoots = [];
        foreach ($rootTypes as $rootType) {
            $createdRoots[$rootType['name']] = DocumentType::create($rootType);
        }

        // Create child document types
        $childTypes = [
            // Certificate children
            [
                'name' => 'Residue COA',
                'description' => 'Certificate of Analysis for residue testing',
                'parent_id' => $createdRoots['Certificates']->id,
                'attribute_type' => 'customer',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'coa_residue', 'requires_approval' => true]
            ],
            [
                'name' => 'Environmental COA',
                'description' => 'Environmental Certificate of Analysis',
                'parent_id' => $createdRoots['Certificates']->id,
                'attribute_type' => 'customer',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'coa_environmental', 'requires_approval' => true]
            ],
            [
                'name' => 'GlobalGAP',
                'description' => 'GlobalGAP certification documents',
                'parent_id' => $createdRoots['Certificates']->id,
                'attribute_type' => 'customer',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'globalgap', 'validity_period' => '1 year']
            ],
            [
                'name' => 'Phytosanitary',
                'description' => 'Phytosanitary certificates',
                'parent_id' => $createdRoots['Certificates']->id,
                'attribute_type' => 'customer',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'phytosanitary', 'export_required' => true]
            ],
            
            // Quality Document children
            [
                'name' => 'Quality Reports',
                'description' => 'Quality assessment and inspection reports',
                'parent_id' => $createdRoots['Quality Documents']->id,
                'attribute_type' => 'grower',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'quality_report', 'frequency' => 'weekly']
            ],
            [
                'name' => 'Packaging Specifications',
                'description' => 'Packaging specifications and certificates',
                'parent_id' => $createdRoots['Quality Documents']->id,
                'attribute_type' => 'customer',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'packaging', 'specification_type' => 'packaging']
            ],
            
            // Compliance Document children
            [
                'name' => 'Audit Reports',
                'description' => 'Internal and external audit reports',
                'parent_id' => $createdRoots['Compliance Documents']->id,
                'attribute_type' => 'none',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'audit', 'confidential' => true]
            ],
            [
                'name' => 'Legal Documents',
                'description' => 'Legal and regulatory compliance documents',
                'parent_id' => $createdRoots['Compliance Documents']->id,
                'attribute_type' => 'none',
                'is_active' => true,
                'metadata' => ['legacy_type' => 'legal', 'confidential' => true]
            ],
        ];

        foreach ($childTypes as $childType) {
            DocumentType::create($childType);
        }
    }
}