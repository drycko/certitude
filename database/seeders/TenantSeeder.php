<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CentralSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
// use tenancy from stancl/tenancy
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
		// seed database pool first?
		$this->seedDatabasePool();

		// get first subscription plan
		$defaultPlan = DB::table('subscription_plans')->where('is_default', true)->first();
		if ($defaultPlan) {
			// Update central setting for default subscription plan
			CentralSetting::updateOrCreate(
				['key' => 'default_subscription_plan'],
				['value' => $defaultPlan->id]
			);
		}

		if (!app()->environment('local')) {
			$availableDb = Tenant::getNextAvailableDatabase() ?? null;
			$this->command->info('Assigning database: ' . ($availableDb ?? 'none') . ' to default tenant');
			if (!$availableDb) {
				$this->command->error('No available pre-created databases in the pool. Please seed the database_pool table with pre-created databases before running this seeder.');
				return;
			}
		}
		else {
			// allow tenant to create a database
			$availableDb = null;
		}
		// Create a default tenant
		$tenant = Tenant::create([
			'name' => 'Demo Exporter',
			'tenancy_db_name' => $availableDb, // assign next available pre-created database (production only)
			'email' => 'demo@demoexporter.com',
			'phone' => '1234567890',
			'logo' => null,
			'address' => null,
			'timezone' => 'UTC',
			'currency' => 'ZAR',
			'locale' => 'en_ZA',
			'plan' => $defaultPlan ? $defaultPlan->name : 'starter',
			'subscription_plan_id' => $defaultPlan->id,
			'billing_cycle' => 'monthly',
			'trial_ends_at' => date('Y-m-d H:i:s', strtotime(now()->addDays(14))), // 14 days trial
			'properties_count' => 0,
			'is_active' => true,
			'data' => null

		]);

		if ($tenant->plan) {
			// check if the plan exists (this is the central connection so we will have to do this outside the tenant context)
			// incase we are in a tenant context already
			// switch to central connection
			config(['database.connections.tenant' => config('database.connections.central')]);
			$defaultPlan = SubscriptionPlan::where('name', $tenant->plan)->first();
			if ($defaultPlan) {
				$trial_ends_at_formatted = $tenant->trial_ends_at ?? null;
				// if this is a trial tenant set the end date to 14 days from now
				if ($trial_ends_at_formatted && $trial_ends_at_formatted > now()) {
					$endDate = $trial_ends_at_formatted;
				} else {
					$endDate = now()->addMonth();
				}
				$planPrice = $tenant->billing_cycle === 'yearly' ? $defaultPlan->yearly_price : $defaultPlan->monthly_price;
				$tenant->subscriptions()->create([
						'tenant_id' => $tenant->id,
						'subscription_plan_id' => $defaultPlan->id,
						'price' => $planPrice,
						'billing_cycle' => $tenant->billing_cycle,
						'start_date' => now(),
						'end_date' => $endDate,
						'status' => $trial_ends_at_formatted && $trial_ends_at_formatted > now() ? 'trial' : 'active',
						'trial_ends_at' => $trial_ends_at_formatted && $trial_ends_at_formatted > now() ? $trial_ends_at_formatted : null,
				]);
			}
		}

		$this->command->info('Default tenant created successfully with plan: ' . $defaultPlan->name);
	}

	// seed the database pool with some pre-created databases if not already seeded
	protected function seedDatabasePool(): void
	{
		$existingCount = DB::table('database_pool')->count();
		$databasesToCreate = 5; // Number of databases to pre-create

		if ($existingCount >= $databasesToCreate) {
			$this->command->info('Database pool already seeded.');
			return;
		}

		$prefix = 'nexusflo_ubix_'; // Use your cPanel database prefix
		for ($i = 1; $i <= $databasesToCreate; $i++) {
			$dbName = $prefix . 'db' . str_pad((string)$i, 3, '0', STR_PAD_LEFT);
			DB::table('database_pool')->updateOrInsert(
					['database_name' => $dbName],
					['is_available' => true, 'assigned_to_tenant' => null, 'created_at' => now(), 'updated_at' => now()]
			);
		}

		$this->command->info('Database pool seeded with ' . $databasesToCreate . ' databases.');
	}
	
	// is this the correct way to run tenant migrations and seeders?
	protected function setupTenantDatabase(Tenant $tenant): void
    {
        // Run migrations for the tenant and only run seeders after migration
        $migrationStatus = \Artisan::call('tenants:migrate', [ // why are we getting SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'permissions' already exists (Connection: central, SQL: create table `permissions`... ? 
            '--tenants' => [$tenant->id],
        ]);
		

        if ($migrationStatus !== 0) {
            $this->command->error('Failed to run migrations for tenant: ' . $tenant->id);
            return;
        }
        // Seed the tenant database using the tenant's context
        $tenant->run(function () {
            \Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                '--force' => true,
            ]);
        });
    }
}
