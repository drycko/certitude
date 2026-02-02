<?php

use App\Http\Controllers\Central\DashboardController;
use App\Http\Controllers\Central\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Access request form (public)
Route::get('/access-request', function () {
    return view('auth.request-access');
})->name('access.request');

Route::post('/access-request', function (Illuminate\Http\Request $request) {
    $validated = $request->validate([
        'organization' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'required|string|max:20',
        'message' => 'nullable|string|max:1000',
    ]);

    // TODO: Store access request in database and notify admins
    // For now, just show success message
    
    return back()->with('success', 'Your access request has been submitted. Our team will review it and contact you soon.');
})->name('access.request.submit');

// Authentication routes (Laravel UI - for central admin)
// Registration is disabled - central users are created by super-admin only
Auth::routes([
    'register' => false, // Disable public registration
    'verify' => false,   // Disable email verification (not needed)
]);

Route::middleware(['auth'])->group(function () {
    Route::get('/central', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/central/dashboard', [DashboardController::class, 'index'])->name('central.dashboard');

    Route::get('/central/tenants', [TenantController::class, 'index'])->name('central.tenants.index');
    Route::get('/central/tenants/create', [TenantController::class, 'create'])->name('central.tenants.create');
    Route::post('/central/tenants', [TenantController::class, 'store'])->name('central.tenants.store');
    Route::get('/central/tenants/{tenant}', [TenantController::class, 'show'])->name('central.tenants.show');
    Route::get('/central/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('central.tenants.edit');
    Route::put('/central/tenants/{tenant}', [TenantController::class, 'update'])->name('central.tenants.update');
    Route::delete('/central/tenants/{tenant}', [TenantController::class, 'destroy'])->name('central.tenants.destroy');
    Route::get('/central/tenants/{tenant}/domains', [TenantController::class, 'domains'])->name('central.tenants.domains');
    Route::get('/central/tenants/{tenant}/subscriptions', [TenantController::class, 'subscriptions'])->name('central.tenants.subscriptions');
    Route::post('/central/tenants/{tenant}/switch-to-premium', [TenantController::class, 'switchToPremium'])->name('central.tenants.switch-to-premium');
    Route::get('/central/tenants/{tenant}/login-as-tenant', [TenantController::class, 'loginAsTenant'])->name('central.tenants.login-as-tenant');
    Route::get('/central/tenants/{tenant}/send-email', [TenantController::class, 'showSendEmailForm'])->name('central.tenants.show-send-email-form');
    Route::post('/central/tenants/{tenant}/send-email', [TenantController::class, 'sendEmailToTenant'])->name('central.tenants.send-email');
    // Route::post('/central/tenants/{tenant}/cancel-subscription', [TenantController::class, 'cancelSubscription'])->name('central.tenants.cancel-subscription');
    Route::resource('tenants', TenantController::class);
});