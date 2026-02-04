<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/* 
* Get the current tenant using Stancl Tenancy
*/
if (!function_exists('current_tenant')) {
    function current_tenant()
    {
        // Use the tenancy() helper from Stancl package
        if (tenancy()->initialized) {
            // Get the current tenant
            // bypass APP_TIMEZONE with tenant timezone if set
            if (tenant() && tenant()->timezone) {
                date_default_timezone_set(tenant()->timezone);
            }
            return tenant();
        }

        // If tenancy is not initialized (e.g., in central domain)
        return null;
    }
}


/* 
* current tenant currency
*/
if (!function_exists('tenant_currency')) {
    function tenant_currency()
    {   
        // first try to get from settings if set
        $currency = \App\Models\Tenant\TenantSetting::getSetting('currency');
        if ($currency) {
            return $currency;
        }
        $tenant = current_tenant();
        return $tenant ? $tenant->currency : null;
    }
}

/* 
* current tenant support email
*/
if (!function_exists('tenant_support_email')) {
    function tenant_support_email()
    {   
        // first try to get from settings if set
        $email = \App\Models\Tenant\TenantSetting::getSetting('support_email');
        if ($email) {
            return $email;
        }
        $tenant = current_tenant();
        return $tenant ? $tenant->support_email : null;
    }
}

/**
 * Check if the currently authenticated user is a super-user
 */
if (!function_exists('is_super_user')) {
    function is_super_user()
    {
        // I want to do this check in a way that if the user is not logged in, it returns false
        if (!Auth::check()) {
            return false;
        }
        
        $user = Auth::user();
        
        // Check if user has super-user role with tenant guard
        $hasSuperUserRole = $user->hasRole('super-user', 'tenant');
        
        // Alternative check: super-users typically have property_id as null
        $hasNullPropertyId = is_null($user->property_id);
        
        return $hasSuperUserRole || $hasNullPropertyId;
    }
}

/**
 * Get the tenant ID if we're in a tenant context
 */
if (!function_exists('current_tenant_id')) {
    function current_tenant_id()
    {
        $tenant = current_tenant();
        return $tenant ? $tenant->getTenantKey() : null;
    }
}


/**
 * clean time formt from imported csv files
 * examples: "17h00, 17-18h00 or 5pm"
 */
if (!function_exists('clean_time')) {
    function clean_ctime($cleanTime)
    {
        // csv TIMEARRIVE needs to be cleaned/formatted if needed - currency it's just a string sometime like "17h00, 17-18h00 or 5pm"
        // format to HH:MM:SS - if there is a '-' or 'to' we take the last part
        if (!empty($cleanTime)) {
            if (strpos($cleanTime, '-') !== false) {
                $parts = explode('-', $cleanTime);
                $timePart = trim(end($parts));
            } elseif (stripos($cleanTime, 'to') !== false) {
                $parts = preg_split('/\s+to\s+/i', $cleanTime);
                $timePart = trim(end($parts));
            } else {
                $timePart = trim($cleanTime);
            }
            // Now parse timePart to HH:MM:SS
            $timePart = str_ireplace(['h', 'H'], ':', $timePart); // Replace h or H with :
            $timePart = str_ireplace(['am', 'pm', 'AM', 'PM'], '', $timePart); // Remove am/pm for now
            $timePart = trim($timePart);
            // If timePart is like 17:00 or 17:00:00 it's fine, if it's like 5 or 5:30 we need to convert to 24h format
            if (preg_match('/^\d{1,2}(:\d{2})?$/', $timePart)) {
                // If it's like 5 or 5:30
                if (strpos($timePart, ':') === false) {
                    $timePart .= ':00'; // Add minutes if missing
                }
                // Convert to 24h format assuming PM if less than 12
                list($hour, $minute) = explode(':', $timePart);
                if ($hour < 12) {
                    $hour += 12; // Convert to PM
                }
                $arrivalTime = sprintf('%02d:%02d:00', $hour, $minute);
            } else {
                // If it's already in HH:MM:SS format or invalid, just use as is or null
                $arrivalTime = preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $timePart) ? $timePart : null;
            }
        } else {
            $arrivalTime = null;
        }
        return $arrivalTime;
    }
}

/**
 * function to increment unique number strings, e.g. INV-001 to INV-002
 * handles leading zeros
 */
if (!function_exists('increment_unique_number')) {
    function increment_unique_number($number)
    {
        // Match the numeric part at the end of the string
        if (preg_match('/(.*?)(\d+)$/', $number, $matches)) {
            $prefix = $matches[1]; // The non-numeric prefix
            $num = $matches[2];    // The numeric part
            $newNum = str_pad($num + 1, strlen($num), '0', STR_PAD_LEFT); // Increment and pad with leading zeros
            return $prefix . $newNum; // Combine prefix with new number
        } else {
            // If no numeric part, just append '1'
            return $number . '1';
        }
    }
}

/**
 * Get the full name of a user
 */
if (!function_exists('full_name')) {
    function full_name($user): string
    {
        return trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->name ?? 'Unknown');
    }
}

/**
 * Get the groups a user belongs to as a comma-separated string
 */
if (!function_exists('user_groups')) {
    function user_groups($user): string
    {
        return $user->groups->pluck('name')->implode(', ');
    }
}

/**
 * Check if a user has a specific permission
 */
if (!function_exists('has_permission')) {
	function has_permission($user, $permission): bool
	{
		return $user->hasPermissionTo($permission);
	}
}

/**
 * Check if a user belongs to a specific group
 */
if (!function_exists('in_group')) {
	function in_group($user, $group): bool
	{
		return $user->groups->contains('name', $group);
	}
}

/**
 * Truncate the given text to a specified length with ellipsis at the end
 */
if (!function_exists('truncate_text')) {
    function truncate_text(string $text, int $maxLength = 100): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        return substr($text, 0, $maxLength - 3) . '...';
    }
}


/**
 * Truncate the given text/filename to a specified length with ellipsis in the middle
 */
if (!function_exists('truncate_filename')) {
    function truncate_filename(string $filename, int $maxLength = 100): string
    {
        if (strlen($filename) <= $maxLength) {
            return $filename;
        }

        $halfLength = (int)($maxLength / 2);
        return substr($filename, 0, $halfLength) . '...' . substr($filename, -$halfLength);
    }
}

/*
I want to first read the countries from my json file and
return them as an array
*/
if (!function_exists('get_countries')) {
    /**
     * Get the list of countries from the JSON file.
     *
     * @return array
     */
    function get_countries(): array
    {   
        // if app is in production use public path else use vendor path
        $json = config('app.env') === 'production' ? file_get_contents('vendor/countries.json') : file_get_contents('../public/vendor/countries.json');
        return json_decode($json, true);
    }
}

// get currencies from countries.json
if (!function_exists('get_currencies')) {
    /**
     * Get the list of unique currencies from the countries JSON file.
     *
     * @return array
     */
    function get_currencies(): array
    {
        $countries = get_countries();
        $currencies = [];
        foreach ($countries as $country) {
            if (isset($country['currency']['code']) && !in_array($country['currency']['code'], $currencies)) {
                $currencies[] = $country['currency']['code'];
            }
        }
        sort($currencies);
        return $currencies;
    }
}

// allowed curencies
if (!function_exists('allowed_currencies')) {
    function allowed_currencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY', 'CNY', 'INR', 'BRL', 'ZAR'];
    }
}

// get supported currencies (intersection of all currencies and allowed currencies)
if (!function_exists('get_supported_currencies')) {
    function get_supported_currencies(): array
    {
        $allCurrencies = get_currencies();
        $allowed = allowed_currencies();
        $supported = array_intersect($allCurrencies, $allowed);
        
        // Return as associative array with code => name for easy use in forms
        $countries = get_countries();
        $result = [];
        
        foreach ($supported as $currencyCode) {
            // Find the currency details from any country that uses this currency
            foreach ($countries as $country) {
                if (isset($country['currency']['code']) && $country['currency']['code'] === $currencyCode) {
                    $result[$currencyCode] = $country['currency']['name'];
                    break;
                }
            }
        }
        
        return $result;
    }
}

// get currency name by code
if (!function_exists('get_currency_name')) {
    function get_currency_name($currencyCode): string
    {
        $countries = get_countries();
        foreach ($countries as $country) {
            if (isset($country['currency']['code']) && $country['currency']['code'] === $currencyCode) {
                return $country['currency']['name'];
            }
        }
        return $currencyCode; // Fallback to code if name not found
    }
}

// get currency symbol by code
if (!function_exists('get_currency_symbol')) {
    function get_currency_symbol($currencyCode): string
    {
        $countries = get_countries();
        foreach ($countries as $country) {
            if (isset($country['currency']['code']) && $country['currency']['code'] === $currencyCode) {
                return $country['currency']['symbol'];
            }
        }
        return '$'; // Fallback to dollar sign
    }
}

// get supported timezones
if (!function_exists('get_supported_timezones')) {
    function get_supported_timezones(): array
    {
        $timezones = [];
        foreach (timezone_identifiers_list() as $timezone) {
            // Create readable format: timezone => "Timezone (UTC+/-X)"
            $dt = new DateTime('now', new DateTimeZone($timezone));
            $offset = $dt->format('P');
            $timezones[$timezone] = str_replace('_', ' ', $timezone) . " (UTC{$offset})";
        }
        return $timezones;
    }
}

// get supported locales (based on available countries for now we only support en)
if (!function_exists('get_supported_locales')) {
    function get_supported_locales(): array
    {
        return ['en' => 'English'];
    }
}

// format price with currency 
if (!function_exists('format_price')) {
    /**
     * Format a price with the given currency.
     *
     * @param float|int $price The price to format
     * @param string|null $currency The currency code (e.g., USD, ZAR). If null, use tenant or app default currency
     * @param bool $showCurrency Whether to show the currency code
     * @return string
     */
    function format_price($price, $currency = null, $showCurrency = true): string
    {
        // will this work if we are in central context too, I am asking because I added this helper in central context too? - 
        if ($currency === null) {
            // if in tenant context, use tenant currency else use app default
            if (current_tenant()) {
                $currency = property_currency() ?? tenant_currency() ?? config('app.currency');
            } else {
                $currency = config('app.currency');
            }
        }
        
        // Get currency symbol
        $symbol = get_currency_symbol($currency);
        
        $formattedPrice = number_format((float) $price, 2, '.', ',');
        
        return $showCurrency ? "{$symbol} {$formattedPrice}" : $formattedPrice;
    }
}

// Configure an on‑the‑fly disk or factory that points to tenant root
if (!function_exists('tenant_disk')) {
    function tenant_disk($diskName = 'local')
    {
        $tenant = current_tenant();
        if (!$tenant) {
            throw new \Exception('No tenant context available for tenant_disk().');
        }
        
        // Create a custom disk configuration for the tenant
        $tenantDiskConfig = [
            'driver' => $diskName,
            'root' => storage_path("app/tenants/{$tenant->id}"),
            'throw' => false,
            'report' => false,
        ];
        
        // Create a temporary disk instance
        return Storage::build($tenantDiskConfig);
    }
}

// get all tenant preferences as associative array
if (!function_exists('tenant_preferences')) {
    function tenant_preferences(): array
    {
        return \App\Models\Tenant\TenantPreference::allPreferences();
    }
}

// get a specific tenant preference by key
if (!function_exists('tenant_preference')) {
    function tenant_preference($key, $default = null)
    {
        return \App\Models\Tenant\TenantPreference::getPreference($key, $default);
    }
}

// get enabled modules for tenant
if (!function_exists('get_enabled_modules')) {
    function get_enabled_modules(): array
    {
        $allModules = [
            'bookings',
            'import_booking',
            'room_management',
            'package_booking',
            'guest_clubs',
            'finances',
            'booking_reports',
            'occupancy_reports',
            'financial_reports',
            'statistics',
            'housekeeping',
            'maintenance',
            'room_status',
        ];
        
        $enabled = [];
        foreach ($allModules as $module) {
            if (tenant_preference("access_to_{$module}", false)) {
                $enabled[] = $module;
            }
        }
        
        return $enabled;
    }
}