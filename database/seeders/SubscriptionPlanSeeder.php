<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter Bundle',
                'slug' => 'starter',
                'description' => 'Best for internal teams, industry bodies, early rollout. Power BI Dashboard Embedding with secure authentication and role-based access.',
                'setup_price' => 52500.00, // Mid-point of R45k-R60k
                'monthly_price' => 2500.00,
                'yearly_price' => 30000.00,
                'features' => json_encode([
                    'powerbi',
                    'user_authentication',
                    'role_based_access',
                    'audit_logs',
                    'subdomain',
                ]),
                'max_users' => null,
                'support_hours' => 1,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth Bundle',
                'slug' => 'growth',
                'description' => 'For exporters, certification-heavy organisations. Includes Document Management, advanced audit trails, and optional grower access.',
                'setup_price' => 97500.00, // Mid-point of R85k-R110k
                'monthly_price' => 4500.00,
                'yearly_price' => 54000.00,
                'features' => json_encode([
                    'powerbi',
                    'document_management',
                    'user_authentication',
                    'role_based_access',
                    'advanced_audit_logs',
                    'grower_access',
                    'subdomain',
                ]),
                'max_users' => null,
                'support_hours' => 2,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Enterprise Bundle',
                'slug' => 'enterprise',
                'description' => 'For large agri groups, exporters, multi-region bodies. Includes AI insights, external stakeholder portals, and SLA-backed support.',
                'setup_price' => 185000.00, // Mid-point of R150k-R220k
                'monthly_price' => 10250.00, // Mid-point of R8.5k-R12k
                'yearly_price' => 123000.00,
                'features' => json_encode([
                    'powerbi',
                    'document_management',
                    'ai_insights',
                    'user_authentication',
                    'role_based_access',
                    'advanced_audit_logs',
                    'external_portals',
                    'grower_access',
                    'dedicated_vps',
                    'sla_support',
                    'subdomain',
                ]),
                'max_users' => null,
                'support_hours' => 4,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
        
        $this->command->info('✓ Subscription plans seeded (Starter, Growth, Enterprise)');
    }

}