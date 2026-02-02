<?php

use App\Http\Controllers\Portal\Auth\LoginController;
use App\Http\Controllers\Portal\Auth\ForgotPasswordController;
use App\Http\Controllers\Portal\Auth\ResetPasswordController;
use App\Http\Controllers\Portal\PortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal Routes
|--------------------------------------------------------------------------
|
| These routes are for tenant administrators to manage their subscriptions,
| billing, and account settings. Separate from both central admin and
| tenant application routes.
|
*/

// redirect /portal
Route::redirect('/portal', '/p/login');

// portal payment routes are in portal-payment.php
require __DIR__.'/portal-payment.php';

// prefix all routes with /p
Route::prefix('/p')->group(function () {
  Route::get('/', function() {
    return redirect()->route('portal.dashboard');
  });
  // Authentication Routes
  Route::middleware('guest:tenant_admin')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('portal.login');
    Route::post('/login', [LoginController::class, 'login']);
    
    // Password Reset Routes
    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('portal.password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('portal.password.email');
    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('portal.password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('portal.password.update');
  });

  Route::post('/logout', [LoginController::class, 'logout'])->name('portal.logout');

  // Protected Portal Routes
  Route::middleware('auth:tenant_admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    
    // Subscription Management
    Route::get('/subscription', [PortalController::class, 'subscription'])->name('portal.subscription');
    Route::post('/subscription/upgrade', [PortalController::class, 'requestUpgrade'])->name('portal.subscription.upgrade');
    
    // Invoices
    Route::get('/invoices', [PortalController::class, 'invoices'])->name('portal.invoices');
    Route::get('/invoices/{invoice}', [PortalController::class, 'showInvoice'])->name('portal.invoices.show');
    Route::get('/invoices/{invoice}/download', [PortalController::class, 'downloadInvoice'])->name('portal.invoices.download');
    Route::get('/invoices/{invoice}/print', [PortalController::class, 'printInvoice'])->name('portal.invoices.print');
    
    // Account Settings
    Route::get('/settings', [PortalController::class, 'settings'])->name('portal.settings');
    Route::post('/settings', [PortalController::class, 'updateSettings'])->name('portal.settings.update');
    Route::post('/settings/password', [PortalController::class, 'updatePassword'])->name('portal.password.change');
  });
});