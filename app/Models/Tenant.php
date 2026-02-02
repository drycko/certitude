<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Illuminate\Support\Facades\DB;
use App\Models\DatabasePool;
use App\Models\SubscriptionPlan;
use App\Models\Subscription;


class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    const BILLING_CYCLE_MONTHLY = 'monthly';
    const BILLING_CYCLE_YEARLY = 'yearly';
    const BILLING_CYCLES = [
        self::BILLING_CYCLE_MONTHLY,
        self::BILLING_CYCLE_YEARLY,
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function boot()
    {
        parent::boot();

        // Prevent automatic database creation
        static::creating(function (Tenant $tenant) {
            if (!$tenant->name) $tenant->name = 'Default Tenant';
            if (!$tenant->plan) $tenant->plan = 'starter';
            if (!$tenant->billing_cycle) $tenant->billing_cycle = 'monthly';
            if (!$tenant->trial_ends_at) $tenant->trial_ends_at = now()->addMonth();
            if (is_null($tenant->is_active)) $tenant->is_active = true;
        });

        static::created(function (Tenant $tenant) {

            $tenant->setupTenantDomain($tenant);

            \Log::info('Current sent tenancy_db_name: '. $tenant->tenancy_db_name);

            // Assign next available pre-created database if database attribute not set
            if (empty($tenant->tenancy_db_name) && empty($tenant->data['tenancy_db_name'])) {
                if (app()->environment('production')) {
                    // Production: require a pre-created database from the pool
                    $tenant->assignPreCreatedDatabase();
                } else {
                    // Development: auto-generate database name and create it
                    \Log::info('Development mode: auto-generating database name for tenant: '. $tenant->name);
                    $databasePrefix = config('tenancy.database.prefix', 'certidb_');
                    $generatedDbName = $databasePrefix . 'tenant_' . $tenant->id;
                    
                    $tenant->tenancy_db_name = $generatedDbName;
                    $tenant->data = array_merge($tenant->data ?? [], ['tenancy_db_name' => $generatedDbName]);
                    $tenant->save();
                    
                    $tenant->setupDatabase($generatedDbName);
                }
            } else {
                // if tenancy_db_name is set we can setup the database
                $tenant->setupDatabase($tenant->tenancy_db_name);
            }
        });

        static::deleting(function (Tenant $tenant) {
            // Delete subdomain from cPanel when tenant is deleted
            if (app()->environment('production') && config('services.cpanel.api_token')) {
                $cpanelService = app(\App\Services\CpanelService::class);
                $primaryDomain = $tenant->domains()->where('is_primary', true)->first();
                
                if ($primaryDomain) {
                    $result = $cpanelService->deleteSubdomain($primaryDomain->domain);
                    
                    if (!$result['success']) {
                        \Log::error("Failed to delete subdomain from cPanel: " . $result['error']);
                    } else {
                        \Log::info("cPanel subdomain deleted: {$primaryDomain->domain}");
                    }
                }
            }
        });
    }

    /**
     * Assign a pre-created database to this tenant
     */
    public function assignPreCreatedDatabase(): void
    {
        // Get next available database from pool(we will not use the getNextAvailableDatabase method here because if tenancy_db_name is not set)
        $availableDatabase = self::getNextAvailableDatabase();

        if ($availableDatabase) {
            \Log::info("Assigning database: {$availableDatabase} to tenant: {$this->name}");
            
            // Set tenancy_db_name as a direct attribute (not in data JSON)
            // This is critical for Stancl Tenancy to use the correct database
            $this->tenancy_db_name = $availableDatabase;
            
            // Also store in data field for reference
            $this->data = array_merge($this->data ?? [], ['tenancy_db_name' => $availableDatabase]);
            $this->save();

            $this->setupDatabase($availableDatabase);
        } else {
            throw new \Exception('No available databases. Please create more databases in cPanel.');
        }
    }

    /**
     * Create database and run setup using package methods
     */
    public function setupDatabase(string $tenancy_db_name): void
    {
        try {
            \Log::info("Setting up tenant database: {$tenancy_db_name}");
            
            // In production, check database pool
            if (app()->environment('production')) {
                $dbPoolEntry = DatabasePool::where('database_name', $tenancy_db_name)->first();
                if (!$dbPoolEntry || !$dbPoolEntry->is_available) {
                    throw new \Exception("Database {$tenancy_db_name} is not available in the pool.");
                }

                // check if database is already assigned to another tenant
                $existingTenantDb = DB::table('tenant_databases')->where('name', $tenancy_db_name)->first();
                if ($existingTenantDb) {
                    throw new \Exception("Database {$tenancy_db_name} is already assigned to another tenant.");
                }
            }

            // ---------------------------
            // Create physical database - let tenancy package handle it
            // ---------------------------
            // The tenancy package's MySQLDatabaseManager will create the database
            // when we call tenancy()->initialize($this) below

            // Assign the database in tenant_databases table if not exists
            $tenantDb = DB::table('tenant_databases')->where('tenant_id', $this->id)->first();
            if (!$tenantDb) {
                DB::table('tenant_databases')->insert([
                    'tenant_id' => $this->id,
                    'name' => $tenancy_db_name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Mark database as assigned in pool (only in production)
                if (app()->environment('production')) {
                    DatabasePool::where('database_name', $tenancy_db_name)->update([
                        'is_available' => false,
                        'assigned_to_tenant' => $this->id,
                        'updated_at' => now(),
                    ]);
                }
            }

            // ---------------------------
            // Initialize tenancy context
            // ---------------------------
            tenancy()->initialize($this);

            // ---------------------------
            // Run tenant migrations
            // ---------------------------
            $migrationStatus = \Artisan::call('migrate', [
                '--path' => 'database/migrations/tenants',
                '--force' => true,
            ]);
            \Log::debug("Tenant migrations executed. Status: {$migrationStatus}");

            // ---------------------------
            // Run tenant seeders
            // ---------------------------
            $seederStatus = \Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                '--force' => true,
            ]);
            \Log::debug("Tenant seeders executed. Status: {$seederStatus}");

            // ---------------------------
            // Optionally, create subscription (this is supposed to happen in the central context but I am doing it here because I have access to the tenant model)
            // ---------------------------
            // if ($this->plan) {
            //     $defaultPlan = SubscriptionPlan::where('name', $this->plan)->first();
            //     if ($defaultPlan) {
            //         Subscription::create([
            //             'tenant_id' => $this->id,
            //             'subscription_plan_id' => $defaultPlan->id,
            //             'price' => $defaultPlan->monthly_price,
            //             'billing_cycle' => 'monthly',
            //             'start_date' => now(),
            //             'end_date' => now()->addMonth(),
            //         ]);
            //     }
            // }

            \Log::info("Tenant database setup completed for tenant: {$this->name}");
        } catch (\Exception $e) {
            \Log::error("Tenant database setup failed for {$this->name}: " . $e->getMessage());
            throw $e;
        } finally {
            // End tenant context so Eloquent returns to central DB
            tenancy()->end();
        }
    }

    /**
     * Get next available pre-created database(since Tenant::create expects the tenancy_db_name attribute to be set we will make this public static)
     */
    public static function getNextAvailableDatabase(): ?string
    {
        // Define your database naming pattern
        $databasePrefix = 'certidb_';
        
        // Check which databases exist and are not in use
        $existingDatabases = (new self)->getExistingDatabases();
        $usedDatabases = DB::table('tenant_databases')->pluck('name')->toArray();
        
        foreach ($existingDatabases as $db) {
            \Log::info('Checking database: ' . $db);
            if (str_starts_with($db, $databasePrefix) && !in_array($db, $usedDatabases)) {
                return $db;
            }
            // also make sure this is not the central database
            if ($db === env('DB_DATABASE')) {
                \Log::info('Skipping central database: ' . $db);
                continue;
            }
        }
        
        return null;
    }

    /**
     * Get list of databases that exist on the server
     */
    protected function getExistingDatabases(): array
    {
        // This requires a special permission - may not work on cPanel
        try {
            $databases = DB::select('SHOW DATABASES'); // i do not like it, the database maybe in use by another tenant
            // database must be empty or not in use by another tenant

            $databases = array_filter($databases, fn($db) => str_starts_with($db->Database, 'certidb_'));
            return array_column($databases, 'Database');
        } catch (\Exception $e) {
            \Log::error('Error fetching databases: ' . $e->getMessage());
            // get databases from database_pool table instead (this a pre-created list of databases I prefer to use)
            return DB::table('database_pool')->pluck('database_name')->toArray();
        }
    }

    /**
     * give the domain to tenant
     */
    protected function setupTenantDomain($tenant): void
    {
        $centralDomain = config('app.base_domain') ?? config('tenancy.central_domains')[0] ?? 'certitude.local';

        \Log::debug('Setting up tenant domain...');

		// Create a unique domain for the tenant
		$tenant_prefix = \Illuminate\Support\Str::slug($tenant->name, '-');
		// $domain_base = '.nexusflow.certitude.co.za';
		$existingDomains = DB::table('domains')->pluck('domain')->toArray();
		$unique_domain = $tenant_prefix .'.'. $centralDomain;
		$i = 1;
		while (in_array($unique_domain, $existingDomains)) {
			$unique_domain = $tenant_prefix . '-' . $i .'.'. $centralDomain;
			$i++;
		}

        // Create subdomain in cPanel (only in production)
		if (app()->environment('production') && config('services.cpanel.api_token')) {
			$cpanelService = app(\App\Services\CpanelService::class);
			$result = $cpanelService->createSubdomain(
				$tenant_prefix, 
				config('services.cpanel.document_root')
			);
			
			if (!$result['success']) {
				\Log::error("Failed to create subdomain in cPanel: " . $result['error']);
				// Optionally throw exception if subdomain creation is critical
				// throw new \Exception("Failed to create subdomain: " . $result['error']);
			} else {
				\Log::info("cPanel subdomain created: {$unique_domain}");
			}
		}

		$tenant->domains()->create([
			'domain' => $unique_domain,
			'is_primary' => true,
		]);
        // update domain in tenant model
        $tenant->domain = $unique_domain;
        $tenant->save();
        \Log::info("Tenant domain set to: {$unique_domain}");
    }

    /**
     * * available databases cross reference with existing databases from database pool
     */
    public static function getAvailableDatabases(): array
    {
        $existingDatabases = (new self)->getExistingDatabases();
        $usedDatabases = DB::table('tenant_databases')->pluck('name')->toArray();
        $availableDatabases = [];

        $tenancyDbPrefix = config('tenancy.database.prefix', 'certidb_');

        foreach ($existingDatabases as $db) {
            if (str_starts_with($db, $tenancyDbPrefix) && !in_array($db, $usedDatabases)) {
                // if database is marked as available in database_pool and is not used by another tenant
                $dbPoolEntry = DatabasePool::where('database_name', $db)->first();
                if ($dbPoolEntry && $dbPoolEntry->is_available) {
                    // if database is not DB_DATABASE in .env
                    if ($db !== env('DB_DATABASE')) {
                        $availableDatabases[] = $db;
                    }
                }
            }
        }

        return $availableDatabases;
    }

    /**
     * relationship with subscriptions
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * relationship with invoices
     */
    public function invoices()
    {
        return $this->hasMany(SubscriptionInvoice::class);
    }

    /**
     * relationship with subscription plan through subscriptions
     */
    public function subscriptionPlan()
    {
        return $this->hasOneThrough(SubscriptionPlan::class, Subscription::class);
    }

    /**
     * current subscription
     */
    public function currentSubscription()
    {
        return $this->hasOne(Subscription::class)->whereIn('status', ['active', 'trial']);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * relationship with tenant admin
     */
    public function admin()
    {
        return $this->hasOne(TenantAdmin::class, 'tenant_id', 'id');
    }

    /**
     * Check if tenant has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->where('end_date', '>=', now())
            ->exists();
    }

    /**
     * Get the tenant's current active subscription with plan details.
     */
    public function getActiveSubscription()
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trial'])
            ->where('end_date', '>=', now())
            ->with('plan')
            ->first();
    }

    /**
     * Check if tenant subscription is expired.
     */
    public function isSubscriptionExpired(): bool
    {
        $subscription = $this->getActiveSubscription();
        
        if (!$subscription) {
            return true;
        }

        return $subscription->end_date < now();
    }

    /**
     * Check if tenant subscription has unpaid invoices.
     */
    public function hasUnpaidInvoices(): bool
    {
        return $this->invoices()
            ->whereIn('status', ['pending', 'overdue'])
            ->exists();
    }

    /**
     * Check if tenant is on a trial subscription.
     */
    public function isOnTrial(): bool
    {
        return $this->subscriptions()
            ->where('status', 'trial')
            ->where('end_date', '>=', now())
            ->exists();
    }

    /**
     * Get trial days remaining.
     * 
     * @return int|null Days remaining in trial, or null if not on trial
     */
    public function getTrialDaysRemaining(): ?int
    {
        $trialSubscription = $this->subscriptions()
            ->where('status', 'trial')
            ->where('end_date', '>=', now())
            ->first();

        if (!$trialSubscription) {
            return null;
        }

        return (int) now()->diffInDays($trialSubscription->end_date, false);
    }

    /**
     * Check if tenant can access a specific feature.
     * 
     * @param string $feature Feature name from plan's features array
     * @return bool
     */
    public function canAccessFeature(string $feature): bool
    {
        $subscription = $this->getActiveSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $features = $subscription->plan->features ?? [];
        
        if (is_string($features)) {
            $features = json_decode($features, true) ?? [];
        }

        return in_array($feature, $features);
    }

    /**
     * Check if tenant is within a limitation for their plan.
     * 
     * @param string $limitation Limitation key (e.g., 'max_users', 'max_properties')
     * @param int $currentCount Current usage count
     * @return bool
     */
    public function isWithinLimit(string $limitation, int $currentCount): bool
    {
        $subscription = $this->getActiveSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return false;
        }

        $limitations = $subscription->plan->limitations ?? [];
        
        if (is_string($limitations)) {
            $limitations = json_decode($limitations, true) ?? [];
        }

        if (!isset($limitations[$limitation])) {
            return true;
        }

        $limit = $limitations[$limitation];
        
        if ($limit === -1 || $limit === null) {
            return true;
        }

        return $currentCount < $limit;
    }

    /**
     * Get remaining limit for a specific limitation.
     * 
     * @param string $limitation Limitation key
     * @param int $currentCount Current usage count
     * @return int|string Returns remaining count or 'unlimited'
     */
    public function getRemainingLimit(string $limitation, int $currentCount)
    {
        $subscription = $this->getActiveSubscription();
        
        if (!$subscription || !$subscription->plan) {
            return 0;
        }

        $limitations = $subscription->plan->limitations ?? [];
        
        if (is_string($limitations)) {
            $limitations = json_decode($limitations, true) ?? [];
        }

        if (!isset($limitations[$limitation])) {
            return 'unlimited';
        }

        $limit = $limitations[$limitation];
        
        if ($limit === -1 || $limit === null) {
            return 'unlimited';
        }

        return max(0, $limit - $currentCount);
    }

    /**
     * Get subscription status message.
     */
    public function getSubscriptionStatusMessage(): string
    {
        if (!$this->hasActiveSubscription()) {
            if ($this->hasUnpaidInvoices()) {
                return 'Your subscription has unpaid invoices. Please settle your account to continue using the service.';
            }
            return 'Your subscription has expired. Please renew to continue using the service.';
        }

        $subscription = $this->getActiveSubscription();
        $daysRemaining = now()->diffInDays($subscription->end_date, false);

        // Check if on trial
        if ($this->isOnTrial()) {
            $trialDays = $this->getTrialDaysRemaining();
            if ($trialDays !== null) {
                if ($trialDays <= 7 && $trialDays > 0) {
                    return "Your trial expires in {$trialDays} day(s). Please subscribe to continue using the service.";
                }
                return "You are on a trial subscription.";
            }
        }

        if ($daysRemaining <= 7 && $daysRemaining > 0) {
            return "Your subscription expires in {$daysRemaining} day(s). Please renew soon to avoid service interruption.";
        }

        return 'Your subscription is active.';
    }

    /**
     * Check if tenant subscription is in grace period.
     * 
     * @param int $graceDays Number of grace days (default: 3)
     * @return bool
     */
    public function isInGracePeriod(int $graceDays = 3): bool
    {
        $subscription = $this->subscriptions()
            ->where('status', 'active')
            ->latest('end_date')
            ->first();

        if (!$subscription) {
            return false;
        }

        $daysSinceExpiry = $subscription->end_date->diffInDays(now(), false);
        
        return $daysSinceExpiry > 0 && $daysSinceExpiry <= $graceDays;
    }
}