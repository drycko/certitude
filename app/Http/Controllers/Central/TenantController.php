<?php

namespace App\Http\Controllers\Central;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\DatabasePool;
use App\Models\SubscriptionInvoice;
use App\Services\TaxCalculationService;


class TenantController extends Controller
{
    use LogsAdminActivity;

    public function __construct()
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        $this->middleware('auth:web');
        $this->middleware('permission:view tenants')->only(['index', 'show']);
        $this->middleware('permission:manage tenants')->except(['index', 'show']);
        
        $this->taxCalculationService = new TaxCalculationService();
    }
    
    public function index(Request $request)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        
        // Build query with filters
        $query = Tenant::with('domains');

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.name')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.email')) LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.contact_person')) LIKE ?", ["%{$search}%"]);
            });
        }

        // Status filter
        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.is_active')) = '1'");
            } elseif ($request->status === 'inactive') {
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.is_active')) = '0'");
            }
        }

        // Plan filter
        if ($request->filled('plan')) {
            $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.plan')) = ?", [$request->plan]);
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate with query string appended
        $tenants = $query->paginate(10)->appends($request->except('page'));
        
        // relationship with domains is provided by HasDomains trait in Tenant model so why eager loading is giving Call to undefined relationship [domains] on model [Stancl\Tenancy\Database\Models\Tenant].? 
        // set tenant->database
        foreach ($tenants as $tenant) {
            $tenant->database = $tenant->tenancy_db_name; // in case tenancy decide to rename these attributes in future
            // check if tenant has a primary domain

            // make sure databases are stored in the tenant_databases table
            if ($tenant->database) {
                // check if database exists in tenant_databases table
                $existingDb = DB::table('tenant_databases')->where('name', $tenant->database)->first();
                if (!$existingDb) {
                    // create it
                    DB::table('tenant_databases')->insert([
                        'tenant_id' => $tenant->id,
                        'name' => $tenant->database,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // check if database exists in database_pool table and mark it as assigned to this tenant
                $dbPool = DatabasePool::where('database_name', $tenant->database)->first();
                if ($dbPool && !$dbPool->assigned_to_tenant) {
                    $dbPool->assigned_to_tenant = $tenant->id;
                    $dbPool->save();
                }
            }
            
            if ($tenant->domains->isNotEmpty()) {
                $tenant->primary_domain = $tenant->domains->where('is_primary', true)->pluck('domain')->first();
            } else {
                // set the first domain as primary if no primary is set
                $tenant->primary_domain = $tenant->domains->pluck('domain')->first();
            }

        }
        
        // Get unique plans for filter dropdown
        $plans = SubscriptionPlan::where('is_active', true)->pluck('name')->unique();
        
        return view('central.tenants.index', compact('tenants', 'plans'));
    }

    public function create()
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        $centralDomain = config('app.base_domain') ?? config('tenancy.central_domains')[0] ?? 'ubixcentral.local';
        
        // subscription plans from database table
        $plans = SubscriptionPlan::where('is_active', true)->get();
        // get available database names from database_pool table that are not assigned to any tenant yet
        $availableDbs = DB::table('database_pool')->where('is_available', true)->whereNull('assigned_to_tenant')->pluck('database_name')->toArray();
        // get app currency from config
        $currency = config('app.currency', 'USD');

        return view('central.tenants.create', compact('plans', 'currency', 'availableDbs', 'centralDomain'));
    }

    public function store(Request $request)
    {
        
        try {
            // use the central database connection from here because I am in the central app
            config(['database.connections.tenant' => config('database.connections.central')]);
            \Log::info('Creating tenant: ' . $request->name . ' with domain: ' . $request->domain . ' and database: ' . ($request->database ?? 'none'));

            // start transaction
            DB::beginTransaction();
            
            // validate name and domain only
            $validate = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'domain' => 'required|string|max:255|unique:domains,domain',
                'database' => 'nullable|string|max:100', // unique is handled in the model
                'contact_person' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'timezone' => 'required|string|max:100',
                'currency' => 'required|string|max:10',
                'locale' => 'required|string|max:10',
                'plan' => 'nullable|string|max:50',
                'is_active' => 'required|boolean',
            ]);

            // check if email does not exist in tenants data json column
            $existingEmail = DB::table('tenants')->where('data->email', $request->email)->first();
            if ($existingEmail) {
                return back()->withErrors(['email' => 'Email already exists.'])->withInput();
            }

            // validate subscription plan exists in subscription_plans table
            if ($request->plan) {
                $billing_cycle = 'monthly';
                if (str_ends_with($request->plan, '_yearly')) {
                    $billing_cycle = 'yearly';
                }
                // remove _yearly from plan name if exists
                $planName = str_replace('_yearly', '', $request->plan);
                $plan = SubscriptionPlan::where('name', $planName)->first();
                if (!$plan) {
                    return back()->withErrors(['plan' => 'Selected subscription plan does not exist.'])->withInput();
                }
                // if is_active is false set to trialing
                if (!$request->is_active) {
                    $request->is_active = 0;
                    $request->trial_ends_at = date('Y-m-d H:i:s', strtotime(now()->addDays(14))); // 14 days trial
                }
            }

            // if database is not provided, get next available database from database_pool table
            if (!$request->database) {
                $availableDb = Tenant::getNextAvailableDatabase() ?? null;
                \Log::info('Assigning database: ' . ($availableDb ?? 'none') . ' to tenant: ' . $request->name);
                if (!$availableDb) {

                    // when not in production allow Tenancy to create a new database on the fly
                    if (app()->environment('local', 'development', 'testing')) {
                        $availableDb = null; // here we are sending null so that Tenancy can create a new database on the fly
                        \Log::info('No available pre-created databases in the pool, allowing Tenancy to create a new database on the fly for tenant: ' . $request->name);
                    }
                    else {
                        // rollback transaction
                        // DB::rollBack();
                        
                        \Log::error('No available pre-created databases in the pool for tenant: ' . $request->name);
                        return back()->withErrors(['database' => 'No available pre-created databases in the pool. Please seed the database_pool table with pre-created databases before creating a tenant.'])->withInput();
                    }
                }
            }
            else {
                $availableDb = $request->database;
            }

            
            $tempPassword = \Str::random(12);

            $tenant = Tenant::create([
                'name' => $request->name,
                'tenancy_db_name' => $availableDb, // assign next available pre-created database (production only)
                'email' => $validate['email'],
                'phone' => $validate['phone'] ?? '',
                'contact_person' => $validate['contact_person'] ?? null,
                'primary_domain' => $request->domain,
                'logo' => null,
                'address' => $validate['address'] ?? null,
                'timezone' => $validate['timezone'] ?? 'UTC',
                'currency' => $validate['currency'] ?? 'ZAR',
                'locale' => $validate['locale'] ?? 'za',
                'plan' => $plan ? $plan->name : 'starter',
                'subscription_plan_id' => $plan ? $plan->id : null,
                'billing_cycle' => $billing_cycle,
                'trial_ends_at' => $request->trial_ends_at, // 14 days trial
                'properties_count' => 0,
                'is_active' => true,
                'tenant_admin_temp_password' => $tempPassword,
                'data' => null

            ]);

            \Log::info("Tenant created with ID: {$tenant->id} and database: {$tenant->tenancy_db_name}");

            if ($planName) {
                // check if the plan exists (this is the central connection so we will have to do this outside the tenant context)
                // incase we are in a tenant context already
                // switch to central connection
                // config(['database.connections.tenant' => config('database.connections.central')]);
                $defaultPlan = SubscriptionPlan::where('name', $planName)->first();
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
            // Create tenant admin account for portal access
            $tenantAdmin = \App\Models\TenantAdmin::create([
                'tenant_id' => $tenant->id,
                'name' => $tenant->contact_person,
                'email' => $tenant->email,
                'password' => \Hash::make($tempPassword),
                'phone' => $tenant->phone,
                'can_manage_billing' => true,
                'can_manage_users' => true,
                'can_manage_settings' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            // update tenant and store the tenant_admin_id and temp password in data column
            // $tenant->data = array_merge($tenant->data ?? [], [
            //     'tenant_admin_id' => $tenantAdmin->id,
            //     'tenant_admin_temp_password' => $tempPassword,
            // ]);
            // $tenant->save();

            \Log::info("Tenant admin created for tenant {$tenant->name} with email {$tenantAdmin->email}");

            // commit transaction
            DB::commit();

            // send an email to site admin about new tenant created
            \Mail::to(config('app.admin_email'))->send(new \App\Mail\TenantCreatedAdminEmail($tenant));
            \Mail::to(config('app.developer_email'))->send(new \App\Mail\TenantCreatedAdminEmail($tenant));
            // send an email to tenant admin with login details (implement later)
            // TODO: Create TenantAdminCredentialsEmail with portal login credentials
            // \Mail::to($tenant->email)->send(new \App\Mail\TenantAdminCredentialsEmail($tenantAdmin, $tempPassword));

            $this->logAdminActivity(
                "create",
                "tenants",
                $tenant->id,
                "Created new tenant '{$request->name}' with domain {$request->domain}"
            );
            $this->createAdminNotification("New tenant '{$request->name}' was created");

            return redirect()->route('tenants.index')
                ->with('success', "Tenant {$request->name} created successfully! Access: {$request->domain}");
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error creating tenant: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error creating tenant: ' . $e->getMessage()])->withInput();
        }
    }

    public function show(Tenant $tenant)
    {
        // Store the return page in session for pagination preservation
        if (request()->has('return_page')) {
            session(['tenants_page' => request('return_page')]);
        }
        
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        
        $centralDomain = config('tenancy.central_domains')[0] ?? 'ubixcentral.local';
        
        // subscription plans for the tenant from database table
        $subscriptions = $tenant->subscriptions()->with('plan')->get();
        // get available database names from database_pool table that are not assigned to any tenant yet
        $availableDbs = DB::table('database_pool')->where('is_available', true)->whereNull('assigned_to_tenant')->pluck('database_name')->toArray();
        // get app currency from config
        $currency = config('app.currency', 'USD');

        // load tenant relationships
        $tenant->load('domains');
        // current subscription where plan name is tenant->plan and status is active or trial
        $currentSubscription = $tenant->currentSubscription ?? null;
        if ($currentSubscription) {

            $currentSubscription->plan = SubscriptionPlan::find($currentSubscription->subscription_plan_id) ?? null;
            $tenant->current_plan = $currentSubscription;
            // if current subscription is trialing calculate trial days left, but if there is no trial_ends_at set it to
            if ($currentSubscription && $currentSubscription->status === 'trial') {
                $tenant->current_plan->trial_ends_at = $currentSubscription->trial_ends_at ?? $currentSubscription->end_date;
                $tenant->current_plan->trial_days_left = $currentSubscription->trial_ends_at ? round(now()->diffInDays($currentSubscription->trial_ends_at ?? $currentSubscription->end_date)) : null;
            }
            else {
                $tenant->current_plan->trial_days_left = null;
            }
        }
        else {
            $tenant->current_plan = null;

        }
        // set tenant->database
        $tenant->database = $tenant->tenancy_db_name; // in case tenancy decide to rename these attributes in future
        // check if tenant has a primary domain
        if ($tenant->domains->isNotEmpty()) {
            $tenant->primary_domain = $tenant->domains->first()->domain;
        } else {
            $tenant->primary_domain = null;
        }

        $availablePlans = SubscriptionPlan::where('is_active', true)->get();

        return view('central.tenants.show', compact('tenant', 'subscriptions', 'availableDbs', 'currency', 'centralDomain', 'availablePlans'));
    }

    public function edit(Tenant $tenant)
    {
        // Store the return page in session for pagination preservation
        if (request()->has('return_page')) {
            session(['tenants_page' => request('return_page')]);
        }
        
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        $centralDomain = config('app.base_domain') ?? config('tenancy.central_domains')[0] ?? 'nexusflow.ubix.co.za';
        
        // subscription plans from database table
        $plans = SubscriptionPlan::where('is_active', true)->get();
        // get available database names from database_pool table that are not assigned to any tenant yet
        $availableDbs = DB::table('database_pool')->where('is_available', true)->whereNull('assigned_to_tenant')->pluck('database_name')->toArray();
        // current tenant primary domain
        $tenant->primary_domain = $tenant->domains->where('is_primary', true)->pluck('domain')->first();
        // set tenant->plan to null if the plan does not exist in subscriptions table or subscription_plans table
        $currentPlan = $tenant->subscriptions()->where('status', 'active')->first();
        if (!$currentPlan) {
            $tenant->current_plan = null;
            $tenant->current_plan_name = null;
        }
        else {
            $tenant->current_plan = $currentPlan;
            $tenant->current_plan_name = $currentPlan->name;
        }

        // get app currency from config
        $currency = config('app.currency', 'USD');
        return view('central.tenants.edit', compact('tenant', 'plans', 'availableDbs', 'currency', 'centralDomain'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        // Store return page for redirect
        $returnPage = $request->input('return_page', session('tenants_page', 1));
        
        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'domain' => 'required|string|max:255',
            'database' => 'nullable|string|max:100',
            'contact_person' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'timezone' => 'required|string|max:100',
            'currency' => 'required|string|max:10',
            'locale' => 'required|string|max:10',
            // 'plan' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        // check if email does not exist in tenants data json column for other tenants
        $existingEmail = DB::table('tenants')->where('data->email', $request->email)->where('id', '!=', $tenant->id)->first();
        if ($existingEmail) {
            return back()->withErrors(['email' => 'Email already exists.'])->withInput();
        }

        $primaryDomain = $tenant->domains->where('is_primary', true)->first();

        // check if domain is changed and unique
        if ($request->domain !== $tenant->primary_domain) {
            $existingDomain = DB::table('domains')->where('domain', $request->domain)->where('tenant_id', '!=', $tenant->id)->first();
            if ($existingDomain) {
                return back()->withErrors(['domain' => 'Domain already exists.'])->withInput();
            }
        }

        // check if database is changed and unique
        if ($request->database && $request->database !== $tenant->tenancy_db_name) {
            $existingDb = DB::table('tenants')->where('tenancy_db_name', $request->database)->first();
            if ($existingDb) {
                return back()->withErrors(['database' => 'Database already exists.'])->withInput();
            }
        }

        // Update tenant details
        $tenant->update([
            'name' => $request->name,
            'tenancy_db_name' => $request->database,
            'email' => $validate['email'],
            'phone' => $validate['phone'] ?? '',
            'contact_person' => $validate['contact_person'] ?? null,
            'primary_domain' => $request->domain,
            'address' => $validate['address'] ?? null,
            'timezone' => $validate['timezone'] ?? 'UTC',
            'currency' => $validate['currency'] ?? 'ZAR',
            'locale' => $validate['locale'] ?? 'za',
            'is_active' => $request->is_active,            
        ]);

        // Update primary domain
        if ($primaryDomain) {
            $primaryDomain->update(['domain' => $request->domain]);
        } else {
            // create primary domain
            $tenant->domains()->create([
                'domain' => $request->domain,
                'is_primary' => true,
            ]);
        }

        // update plan if changed
        // if ($request->plan && $request->plan !== $tenant->plan) {
        //     $planName = str_replace('_yearly', '', $request->plan);
        //     $billing_cycle = str_ends_with($request->plan, '_yearly') ? 'yearly' : 'monthly';
        //     $plan = SubscriptionPlan::where('name', $planName)->first();
        //     // if there is an existing active or trialing subscription, end it today
        //     $activeSubscription = $tenant->subscriptions()->whereIn('status', ['active', 'trialing'])->first();
        //     if ($activeSubscription) {
        //         $activeSubscription->update([
        //             'status' => 'cancelled',
        //             'end_date' => now(),
        //         ]);
        //     }
        //     if ($plan) {
        //         $tenant->plan = $plan->name;
        //         $tenant->subscription_plan_id = $plan->id;
        //         $tenant->billing_cycle = $billing_cycle;
        //         $tenant->save();

        //         // create new subscription record
        //         $endDate = now()->addMonth();
        //         $planPrice = $tenant->billing_cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        //         $tenant->subscriptions()->create([
        //             'tenant_id' => $tenant->id,
        //             'subscription_plan_id' => $plan->id,
        //             'price' => $planPrice,
        //             'billing_cycle' => $tenant->billing_cycle,
        //             'start_date' => now(),
        //             'end_date' => $endDate,
        //             'status' => 'active',
        //             'trial_ends_at' => null,
        //         ]);

        //         $this->logAdminActivity(
        //             "update",
        //             "tenants",
        //             $tenant->id,
        //             "Changed tenant '{$tenant->name}' plan to {$plan->name} ({$billing_cycle})"
        //         );
        //         $this->createAdminNotification("Tenant '{$tenant->name}' was switched to {$plan->name} plan ({$billing_cycle})");
        //     }
        // }

            $this->logAdminActivity(
                "update",
                "tenants",
                $tenant->id,
                "Updated tenant '{$tenant->name}' details"
            );
        
        return redirect()->route('tenants.index', ['page' => $returnPage])
            ->with('success', 'Tenant updated successfully.');
    }    public function showSendEmailForm(Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        return view('central.tenants.send-welcome-email', compact('tenant'));
    }

    public function sendEmailToTenant(Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        try {
            // we have to make sure we have the tenant admin temp password in the data column else we have to set a new one and update the tenant admin password
            if (empty($tenant->tenant_admin_temp_password)) {
                $newTempPassword = \Str::random(12);
                $tenantAdmin = \App\Models\TenantAdmin::where('tenant_id', $tenant->id)->first();
                if ($tenantAdmin) {
                    $tenantAdmin->password = \Hash::make($newTempPassword);
                    $tenantAdmin->save();
                    // update tenant temp password
                    $tenant->tenant_admin_temp_password = $newTempPassword;
                    $tenant->save();
                } else {
                    throw new \Exception('Tenant admin not found for tenant ID: ' . $tenant->id);
                }
            }
            \Mail::to($tenant->email)->send(new \App\Mail\NewTenantWelcomeEmail($tenant));
            // testing send to developer email
            \Mail::to(config('app.developer_email'))->send(new \App\Mail\NewTenantWelcomeEmail($tenant));
            return redirect()->route('central.tenants.show', $tenant)->with('success', 'Tenant credentials email sent successfully to ' . $tenant->email);
            // log email sent
            
        } catch (\Exception $e) {
            \Log::error('Error sending tenant credentials email: ' . $e->getMessage());
            return redirect()->route('central.tenants.show', $tenant)->withErrors(['error' => 'Error sending tenant credentials email: ' . $e->getMessage()]);
        }
    }

    public function domains(Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        
        $domains = $tenant->domains;
        return view('central.tenants.domains', compact('tenant', 'domains'));
    }

    public function subscriptions(Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        
        $subscriptions = $tenant->subscriptions()->with('plan')->paginate(10);
        $subscriptions->getCollection()->transform(function ($subscription) {
            $subscription->plan = SubscriptionPlan::find($subscription->subscription_plan_id) ?? null;
            return $subscription;
        });
        $currency = config('app.currency', 'USD');
        return view('central.subscriptions.tenant-subs', compact('tenant', 'subscriptions', 'currency'));
    }

    public function switchToPremium(Request $request, Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        try {
            \Log::info('Switching tenant: ' . $tenant->name . ' to premium plan with request data: ' . json_encode($request->all()));
            // transaction can be added here if needed
            DB::beginTransaction();

            // Accept Request $request as parameter
            // Validate that the sent plan exists in the subscription_plans table
            $planName = $request->input('plan_name');
            $basePlanName = str_replace('_yearly', '', $planName);

            $request->merge(['base_plan_name' => $basePlanName]);
            $request->validate([
                'base_plan_name' => 'required|exists:subscription_plans,name',
            ]);

            $planName = $request->input('plan_name');
            $billing_cycle = str_ends_with($planName, '_yearly') ? 'yearly' : 'monthly';
            $basePlanName = str_replace('_yearly', '', $planName);
            $premiumPlan = SubscriptionPlan::where('name', $basePlanName)->first();

            if ($premiumPlan) {
                $plan_amount = $billing_cycle === 'yearly' ? $premiumPlan->yearly_price : $premiumPlan->monthly_price;
                $endDate = $billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();

                // Create new subscription record with status 'pending' (not assigned to tenant until payment is confirmed)
                $newSubscription = $tenant->subscriptions()->create([
                    'subscription_plan_id' => $premiumPlan->id,
                    'price' => $plan_amount,
                    'billing_cycle' => $billing_cycle,
                    'start_date' => now(),
                    'end_date' => $endDate,
                    'status' => 'inactive', // will be set to active when payment is confirmed
                    'trial_ends_at' => null,
                ]);

                // Create a subscription invoice linked to the new subscription
                // Calculate tax for the booking
                $taxCalculation = $this->taxCalculationService->calculateTaxForInvoice($plan_amount);
                
                // Create invoice
                $newInvoice = SubscriptionInvoice::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $newSubscription->id,
                    'invoice_number' => SubscriptionInvoice::generateInvoiceNumber(),
                    'invoice_date' => now(),
                    'due_date' => now()->addDays(7),
                    'amount' => $taxCalculation['total_amount'],
                    'subtotal_amount' => $taxCalculation['subtotal_amount'],
                    'tax_amount' => $taxCalculation['tax_amount'],
                    'tax_rate' => $taxCalculation['tax_rate'],
                    'tax_name' => $taxCalculation['tax_name'],
                    'tax_type' => $taxCalculation['tax_type'],
                    'tax_inclusive' => $taxCalculation['tax_inclusive'],
                    'tax_id' => $taxCalculation['tax_id'],
                    'status' => 'pending',
                    'notes' => 'Invoice for switching to ' . $premiumPlan->name . ' plan.',
                ]);
                // SubscriptionInvoice::create([
                //     'tenant_id' => $tenant->id,
                //     'subscription_id' => $newSubscription->id,
                //     'invoice_number' => SubscriptionInvoice::generateInvoiceNumber(),
                //     'invoice_date' => now(),
                //     'due_date' => now()->addDays(7),
                //     'amount' => $plan_amount,
                //     'status' => 'pending',
                //     'notes' => 'Invoice for switching to ' . $premiumPlan->name . ' plan.',
                // ]);

                // Do NOT update tenant plan fields until payment is confirmed
                // This should be handled in a payment confirmation callback/webhook
                DB::commit();
                // redirecting to show invoice
                return redirect()->route('central.invoices.show', $newInvoice->id)
                    ->with('success', 'Tenant switch to ' . $premiumPlan->name . ' ' . ucfirst($billing_cycle) . ' plan initiated. Please process payment for invoice #' . $newInvoice->invoice_number . '.');
                // return redirect()->route('central.tenants.subscriptions', $tenant)->with('success', 'Tenant switched to ' . $premiumPlan->name . ' ' . ucfirst($billing_cycle) . ' plan. Awaiting payment confirmation.');
            } else {
                DB::rollBack();
                return redirect()->route('central.tenants.show', $tenant)->withErrors(['plan' => 'Premium plan does not exist.']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error logging switch to premium request data: ' . $e->getMessage());
            return redirect()->route('central.tenants.show', $tenant)->withErrors(['error' => 'Error switching to premium plan: ' . $e->getMessage()]);
        }
    }

    public function cancelSubscription(Tenant $tenant)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        
        // cancel the current active subscription
        $activeSubscription = $tenant->subscriptions()->where('status', 'active')->first();
        if ($activeSubscription) {
            $activeSubscription->update([
                'status' => 'cancelled',
                'end_date' => now(),
            ]);
            // switch tenant back to default plan
            $plan = SubscriptionPlan::where('is_default', true)->first();
            if ($plan) {
                $tenant->plan = $plan->name;
                $tenant->subscription_plan_id = $plan->id;
                $tenant->billing_cycle = 'monthly';
                $tenant->is_active = true;
                $tenant->save();

                $this->logAdminActivity(
                    "cancel_subscription",
                    "subscriptions",
                    $activeSubscription->id,
                    "Cancelled subscription '{$activeSubscription->id}' for tenant '{$tenant->name}' and reverted to default plan"
                );
                $this->createAdminNotification("Subscription cancelled for tenant '{$tenant->name}'");
            }
            return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant subscription cancelled successfully.');
        } else {
            return redirect()->route('tenants.show', $tenant)->withErrors(['subscription' => 'No active subscription found for this tenant.']);
        }
    }

    /**
     * Log in as a tenant user for testing purposes
     */
    public function loginAsTenant(Tenant $tenant)
    {
        // Store central user session info
        session()->put('central_user_id', auth()->id());
        session()->put('central_user_email', auth()->user()->email);

        // Get or create a test user for the tenant
        tenancy()->initialize($tenant);
        
        $tenantUser = \App\Models\User::firstOrCreate(
            ['email' => 'test@' . $tenant->domains->first()->domain],
            [
                'name' => 'Test User',
                'email' => 'test@' . $tenant->domains->first()->domain,
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        // Login as the tenant user
        auth()->login($tenantUser);

        $this->logAdminActivity(
            "login",
            "tenants",
            $tenant->id,
            "Admin logged in as tenant user for '{$tenant->name}'"
        );

        // Redirect to tenant dashboard with the tenant's domain
        return redirect('http://' . $tenant->domains->first()->domain . '/dashboard');
    }

    public function destroy(Tenant $tenant)
    {   
        try {
            // Get return page before deleting
            $returnPage = request()->input('return_page', session('tenants_page', 1));
            
            // use the central database connection from here because I am in the central app
            config(['database.connections.tenant' => config('database.connections.central')]);
            
            // start transaction
            DB::beginTransaction();
            // remove database from tenant_databases table
            DB::table('tenant_databases')->where('name', $tenant->tenancy_db_name)->delete();
            // mark database as unassigned in database_pool table
            $dbPool = DatabasePool::where('database_name', $tenant->tenancy_db_name)->first();
            if ($dbPool) {
                $dbPool->assigned_to_tenant = null;
                $dbPool->save();
            }
            // delete tenant using tenancy package method
            $tenantName = $tenant->name;
            $tenant->delete();

            // commit transaction
            DB::commit();

            $this->logAdminActivity(
                "delete",
                "tenants",
                $tenant->id,
                "Deleted tenant '{$tenantName}'"
            );
            $this->createAdminNotification("Tenant '{$tenantName}' was deleted");

            return redirect()->route('tenants.index', ['page' => $returnPage])
                ->with('success', 'Tenant deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error deleting tenant: ' . $e->getMessage());
            return redirect()->route('tenants.index', ['page' => $returnPage])
                ->withErrors(['error' => 'Error deleting tenant: ' . $e->getMessage()]);
        }

    }
}