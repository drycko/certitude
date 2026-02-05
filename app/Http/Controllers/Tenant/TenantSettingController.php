<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\TenantSetting;
// use App\Models\Tenant\TenantPreference;
use Illuminate\Http\Request;

class TenantSettingController extends Controller
{
    /**
     * Display a base settings page.
     */
    public function index()
    {
        // Show the base settings page
        $paymentGateways = [
            'payfast' => [
                'is_default' => TenantSetting::getSetting('payfast_is_default'),
            ],
            'paygate' => [
                'is_default' => TenantSetting::getSetting('paygate_is_default'),
            ],
        ];
        return view('tenant.settings.index', compact('paymentGateways'));
    }

    /**
     * Show general settings page.
     */
    public function general()
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        // Fetch the tenant settings (These are key value pairs)
        $settings = TenantSetting::allSettings();
        return view('tenant.settings.general', compact('settings'));
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(Request $request)
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        // Validate and save settings
        $validated = $request->validate([
            'tenant_name' => 'required|string|max:255',
            'tenant_admin_email' => 'required|email|max:255',
            'tenant_phone' => 'nullable|string|max:20',
            'tenant_address_street' => 'nullable|string|max:255',
            'tenant_address_street_2' => 'nullable|string|max:255',
            'tenant_address_city' => 'nullable|string|max:100',
            'tenant_address_state' => 'nullable|string|max:100',
            'tenant_address_zip' => 'nullable|string|max:20',
            'tenant_address_country' => 'nullable|string|max:100',
            'tenant_website' => 'nullable|url|max:255',
            'tenant_tax_number' => 'nullable|string|max:50',
            'tenant_registration_number' => 'nullable|string|max:50',
            'tenant_currency' => 'nullable|string|max:10',
            'tenant_timezone' => 'nullable|string|max:50',
            'tenant_logo' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Handle logo upload if present
        if ($request->hasFile('tenant_logo') && config('app.env') === 'production' && config('filesystems.default') === 'gcs') {
            $file = $request->file('tenant_logo');
            $gcsPath = 'tenant' . $tenant_id . '/branding/' . uniqid() . '_' . $file->getClientOriginalName();
            $stream = fopen($file->getRealPath(), 'r');
            Storage::disk('gcs')->put($gcsPath, $stream);
            fclose($stream);
            $validated['tenant_logo'] = $gcsPath;

        }elseif ($request->hasFile('tenant_logo')) {
            $file = $request->file('tenant_logo');
            $logoPath = $file->store('branding', 'public');;
            $validated['tenant_logo'] = $logoPath;
        } else {
            // Remove tenant_logo from validated if no file uploaded
            unset($validated['tenant_logo']);
        }

        foreach ($validated as $key => $value) {
            TenantSetting::setSetting($key, $value);
        }

        return redirect()->back()->with('success', 'General settings updated successfully.');
    }

    /**
     * Show the theme settings page.
     */
    public function theme()
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        // Fetch the tenant theme settings (These are key value pairs)
        $settings = TenantSetting::allSettings();
        $currentTheme = $settings['package_booking_template'] ?? 'modern';
        $primaryColor = $settings['primary_color'] ?? '#059669';
        $secondaryColor = $settings['secondary_color'] ?? '#7caf9e';
        $fontFamily = $settings['font_family'] ?? "'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";
        $logoPosition = $settings['logo_position'] ?? 'left';
        $enablePackages = $settings['enable_packages'] ?? false;
        $defaultBookingType = $settings['default_booking_type'] ?? 'modern';
        
        return view('tenant.settings.theme', compact('settings', 'currentTheme', 'primaryColor', 'secondaryColor', 'fontFamily', 'logoPosition', 'enablePackages', 'defaultBookingType'));
    }

    /**
     * Update the theme settings.
     */
    public function updateTheme(Request $request)
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        try {
            // Validate and save theme settings
            $validated = $request->validate([
                'theme_selection' => 'required|string|in:modern,classic', // Add more themes as needed
                'primary_color' => 'required|string|max:7', // e.g. #000000
                'secondary_color' => 'required|string|max:7', // e.g. #ffffff
                'font_family' => 'required|string|max:100',
                'logo_position' => 'required|string|in:left,center,right',
                'enable_packages' => 'required|boolean',
                'default_booking_type' => 'required|string|in:standard,package',
            ]);

            $settingsToUpdate = [
                'package_booking_template' => $validated['theme_selection'],
                'standard_booking_template' => $validated['theme_selection'],
                'package_index_template' => $validated['theme_selection'],
                'standard_index_template' => $validated['theme_selection'],
                'enable_packages' => $validated['enable_packages'],
                'default_booking_type' => $validated['default_booking_type'],
                'primary_color' => $validated['primary_color'],
                'secondary_color' => $validated['secondary_color'],
                'font_family' => $validated['font_family'],
                'logo_position' => $validated['logo_position'],
            ];

            foreach ($settingsToUpdate as $key => $value) {
                TenantSetting::setSetting($key, $value);
            }
            return redirect()->back()->with('success', 'Theme settings updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'An error occurred while updating theme settings: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(TenantSetting $tenantSetting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TenantSetting $tenantSetting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TenantSetting $tenantSetting)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantSetting $tenantSetting)
    {
        //
    }

    /**
     * Show the form for editing PayFast credentials.
     */
    public function editPayfast()
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        $settings = [
            'merchant_id' => TenantSetting::getSetting('payfast_merchant_id'),
            'is_test' => TenantSetting::getSetting('payfast_is_test'),
            'is_default' => TenantSetting::getSetting('payfast_is_default'),
            'merchant_key' => TenantSetting::getEncryptedSetting('payfast_merchant_key'),
            'passphrase' => TenantSetting::getEncryptedSetting('payfast_passphrase'),
        ];
        return view('tenant.settings.payfast', compact('settings'));
    }

    /**
     * Update PayFast credentials in storage.
     */
    public function updatePayfast(Request $request)
    {
        // must be super user (in future we will do a proper permission check in __construct)
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'passphrase' => 'nullable|string',
            'is_test' => 'required|boolean',
            'is_default' => 'required|boolean',
        ]);

        TenantSetting::setSetting('payfast_merchant_id', $request->merchant_id);
        TenantSetting::setSetting('payfast_is_test', $request->is_test);
        TenantSetting::setEncryptedSetting('payfast_merchant_key', $request->merchant_key);
        TenantSetting::setEncryptedSetting('payfast_passphrase', $request->passphrase);
        // if is default we have to unset others
        if ($request->is_default) {
            // Here you would typically have logic to unset other payment gateways as default
            if (TenantSetting::getSetting('paygate_is_default')) {
                TenantSetting::setSetting('paygate_is_default', false);
            }
            TenantSetting::setSetting('payfast_is_default', true);
        } else {
            TenantSetting::setSetting('payfast_is_default', false);
        }
        TenantSetting::setSetting('payfast_is_default', $request->is_default);

        return redirect()->back()->with('success', 'PayFast credentials updated successfully.');
    }

    /**
     * Show the form for editing PayGate credentials.
     */
    public function editPaygate()
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        $settings = [
            'merchant_id' => TenantSetting::getSetting('paygate_merchant_id'),
            'is_test' => TenantSetting::getSetting('paygate_is_test'),
            'is_default' => TenantSetting::getSetting('paygate_is_default'),
            'merchant_key' => TenantSetting::getEncryptedSetting('paygate_merchant_key'),
            'passphrase' => TenantSetting::getEncryptedSetting('paygate_passphrase'),
        ];
        return view('tenant.settings.paygate', compact('settings'));
    }
    /**
     * Update PayGate credentials in storage.
     */
    public function updatePaygate(Request $request)
    {
        // must be super user (in future we will do a proper permission check in __construct)
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        $request->validate([
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'passphrase' => 'nullable|string',
            'is_test' => 'required|boolean',
            'is_default' => 'required|boolean',
        ]);

        TenantSetting::setSetting('paygate_merchant_id', $request->merchant_id);
        TenantSetting::setSetting('paygate_is_test', $request->is_test);
        TenantSetting::setEncryptedSetting('paygate_merchant_key', $request->merchant_key);
        TenantSetting::setEncryptedSetting('paygate_passphrase', $request->passphrase);
        // if is default we have to unset others
        if ($request->is_default) {
            // Here you would typically have logic to unset other payment gateways as default
            if (TenantSetting::getSetting('payfast_is_default')) {
                TenantSetting::setSetting('payfast_is_default', false);
            }
            TenantSetting::setSetting('paygate_is_default', true);
        } else {
            TenantSetting::setSetting('paygate_is_default', false);
        }
        TenantSetting::setSetting('paygate_is_default', $request->is_default);

        return redirect()->back()->with('success', 'PayGate credentials updated successfully.');
    }

    /**
     * Show the preferences settings page.
     */
    public function preferences()
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        // $preferences = [
        //     'room_pricing_model' => TenantPreference::getPreference('room_pricing_model', 'room'),
        //     // Module access preferences
        //     'access_to_bookings' => TenantPreference::getPreference('access_to_bookings', true),
        //     'access_to_import_booking' => TenantPreference::getPreference('access_to_import_booking', true),
        //     'access_to_room_management' => TenantPreference::getPreference('access_to_room_management', true),
        //     'access_to_package_booking' => TenantPreference::getPreference('access_to_package_booking', true),
        //     'access_to_finances' => TenantPreference::getPreference('access_to_finances', true),
        //     'access_to_booking_reports' => TenantPreference::getPreference('access_to_booking_reports', false),
        //     'access_to_occupancy_reports' => TenantPreference::getPreference('access_to_occupancy_reports', false),
        //     'access_to_financial_reports' => TenantPreference::getPreference('access_to_financial_reports', false),
        //     'access_to_statistics' => TenantPreference::getPreference('access_to_statistics', false),
        //     'access_to_housekeeping' => TenantPreference::getPreference('access_to_housekeeping', false),
        //     'access_to_maintenance' => TenantPreference::getPreference('access_to_maintenance', false),
        //     'access_to_room_status' => TenantPreference::getPreference('access_to_room_status', false),
        //     'access_to_guest_clubs' => TenantPreference::getPreference('access_to_guest_clubs', false),
        // ];

        return view('tenant.settings.preferences', compact('preferences'));
    }

    /**
     * Update preferences.
     */
    public function updatePreferences(Request $request)
    {
        // must be super user
        if (!is_super_user()) {
            abort(403, 'Unauthorized access');
        }

        // Validate input
        $validated = $request->validate([
            'room_pricing_model' => 'required|string|in:room,person',
        ]);

        // Define module preferences
        $modulePreferences = [
            'access_to_bookings',
            'access_to_import_booking',
            'access_to_room_management',
            'access_to_package_booking',
            'access_to_finances',
            'access_to_booking_reports',
            'access_to_occupancy_reports',
            'access_to_financial_reports',
            'access_to_statistics',
            'access_to_housekeeping',
            'access_to_maintenance',
            'access_to_room_status',
            'access_to_guest_clubs',
        ];

        // Set default modules to always be true (cannot be disabled)
        $defaultModules = ['access_to_bookings', 'access_to_import_booking', 'access_to_room_management'];

        // foreach ($modulePreferences as $module) {
        //     if (in_array($module, $defaultModules)) {
        //         TenantPreference::setPreference($module, true);
        //     } else {
        //         // Laravel's boolean validation already converts checkbox values
        //         TenantPreference::setPreference($module, $request->boolean($module));
        //     }
        // }

        // Save other validated preferences
        // TenantPreference::setPreference('room_pricing_model', $validated['room_pricing_model']);

        return redirect()->back()->with('success', 'Preferences updated successfully.');
    }
}
