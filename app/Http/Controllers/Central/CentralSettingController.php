<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\CentralSetting;
use App\Traits\LogsAdminActivity;
use Illuminate\Http\Request;

class CentralSettingController extends Controller
{
    use LogsAdminActivity;
        
    public function __construct()
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);
        $this->middleware('auth:web');
        // TODO: Implement central permission checks without tenant context
        $this->middleware('permission:view central settings')->only(['index', 'show']);
        $this->middleware('permission:manage central settings')->only([
            'create', 'edit', 'update', 'store', 'editPayfast', 'editGeneral', 'updateGeneral', 'updatePayfast', 'updatePaygate']);
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        // Show the base settings page with payment gateways
        $paymentGateways = [
            'payfast' => [
                'is_default' => CentralSetting::getSetting('payfast_is_default'),
            ],
            'paygate' => [
                'is_default' => CentralSetting::getSetting('paygate_is_default'),
            ],
        ];
        
        return view('central.settings.index', compact('paymentGateways'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // not needed since the settings index is the form
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // use the central database connection from here because I am in the central app
        config(['database.connections.tenant' => config('database.connections.central')]);

        // Validate and save settings
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_email' => 'required|email|max:255',
            'support_email' => 'nullable|email|max:255',
            'contact_email' => 'nullable|email|max:255',
            'site_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            // Add more settings validation as needed
        ]);

        // for now we do not save anything (we will get back to this)
        return redirect()->route('central.settings')->with('success', 'Settings updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(CentralSetting $centralSetting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CentralSetting $centralSetting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CentralSetting $centralSetting)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CentralSetting $centralSetting)
    {
        //
    }

    /**
     * Show general settings page.
     */
    public function general()
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        // Fetch the central settings (These are key value pairs)
        $settings = CentralSetting::allSettings();
        return view('central.settings.general', compact('settings'));
    }

    /**
     * Update general settings.
     */
    public function updateGeneral(Request $request)
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        // Validate and save settings
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_email' => 'required|email|max:255',
            'support_email' => 'nullable|email|max:255',
            'contact_email' => 'nullable|email|max:255',
            'site_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            // Add more settings validation as needed
        ]);

        foreach ($validated as $key => $value) {
            CentralSetting::setSetting($key, $value);
        }

        // Log activity
        $this->logAdminActivity(
            "update",
            "general_settings",
            1,
            "Updated general settings"
        );
        $this->createAdminNotification("General settings were updated");

        return redirect()->back()->with('success', 'General settings updated successfully.');
    }

    /**
     * Show the form for editing PayFast credentials.
     */
    public function editPayfast()
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        $settings = [
            'merchant_id' => CentralSetting::getSetting('payfast_merchant_id'),
            'is_test' => CentralSetting::getSetting('payfast_is_test'),
            'is_default' => CentralSetting::getSetting('payfast_is_default'),
            'merchant_key' => CentralSetting::getEncryptedSetting('payfast_merchant_key'),
            'passphrase' => CentralSetting::getEncryptedSetting('payfast_passphrase'),
        ];
        
        return view('central.settings.payfast', compact('settings'));
    }

    /**
     * Update PayFast credentials in storage.
     */
    public function updatePayfast(Request $request)
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        $request->validate([
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'passphrase' => 'nullable|string',
            'is_test' => 'required|boolean',
            'is_default' => 'required|boolean',
        ]);

        CentralSetting::setSetting('payfast_merchant_id', $request->merchant_id);
        CentralSetting::setSetting('payfast_is_test', $request->is_test);
        CentralSetting::setEncryptedSetting('payfast_merchant_key', $request->merchant_key);
        
        if ($request->passphrase) {
            CentralSetting::setEncryptedSetting('payfast_passphrase', $request->passphrase);
        }
        
        // if is default we have to unset others
        if ($request->is_default) {
            if (CentralSetting::getSetting('paygate_is_default')) {
                CentralSetting::setSetting('paygate_is_default', false);
            }
            CentralSetting::setSetting('payfast_is_default', true);
        } else {
            CentralSetting::setSetting('payfast_is_default', false);
        }

        // Log activity
        $this->logAdminActivity(
            "update",
            "payfast_settings",
            1,
            "Updated PayFast payment gateway settings"
        );
        $this->createAdminNotification("PayFast credentials were updated");

        return redirect()->back()->with('success', 'PayFast credentials updated successfully.');
    }

    /**
     * Show the form for editing PayGate credentials.
     */
    public function editPaygate()
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        $settings = [
            'merchant_id' => CentralSetting::getSetting('paygate_merchant_id'),
            'is_test' => CentralSetting::getSetting('paygate_is_test'),
            'is_default' => CentralSetting::getSetting('paygate_is_default'),
            'merchant_key' => CentralSetting::getEncryptedSetting('paygate_merchant_key'),
            'passphrase' => CentralSetting::getEncryptedSetting('paygate_passphrase'),
        ];
        
        return view('central.settings.paygate', compact('settings'));
    }

    /**
     * Update PayGate credentials in storage.
     */
    public function updatePaygate(Request $request)
    {
        // use the central database connection
        config(['database.connections.tenant' => config('database.connections.central')]);

        $request->validate([
            'merchant_id' => 'required|string',
            'merchant_key' => 'required|string',
            'passphrase' => 'nullable|string',
            'is_test' => 'required|boolean',
            'is_default' => 'required|boolean',
        ]);

        CentralSetting::setSetting('paygate_merchant_id', $request->merchant_id);
        CentralSetting::setSetting('paygate_is_test', $request->is_test);
        CentralSetting::setEncryptedSetting('paygate_merchant_key', $request->merchant_key);
        
        if ($request->passphrase) {
            CentralSetting::setEncryptedSetting('paygate_passphrase', $request->passphrase);
        }
        
        // if is default we have to unset others
        if ($request->is_default) {
            if (CentralSetting::getSetting('payfast_is_default')) {
                CentralSetting::setSetting('payfast_is_default', false);
            }
            CentralSetting::setSetting('paygate_is_default', true);
        } else {
            CentralSetting::setSetting('paygate_is_default', false);
        }

        // Log activity
        $this->logAdminActivity(
            "update",
            "paygate_settings",
            1,
            "Updated PayGate payment gateway settings"
        );
        $this->createAdminNotification("PayGate credentials were updated");


        return redirect()->back()->with('success', 'PayGate credentials updated successfully.');
    }
}
