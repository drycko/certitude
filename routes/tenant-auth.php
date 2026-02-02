<?php

use App\Http\Controllers\Tenant\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Tenant\Auth\RegisteredUserController;
use App\Http\Controllers\Tenant\Auth\PasswordResetLinkController;
use App\Http\Controllers\Tenant\Auth\NewPasswordController;
use App\Http\Controllers\Tenant\Auth\ChangePasswordController;
use Illuminate\Support\Facades\Route;

// Tenant admin authentication routes (prefixed with /t/)
Route::prefix('t')->group(function () {
    Route::middleware('guest:tenant')->group(function () {
        Route::get('register', [RegisteredUserController::class, 'create'])
            ->name('tenant.register');

        Route::post('register', [RegisteredUserController::class, 'store'])
            ->name('tenant.register.store');

        Route::get('login', [AuthenticatedSessionController::class, 'create'])
            ->name('tenant.login');

        Route::post('login', [AuthenticatedSessionController::class, 'store']);

        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
            ->name('tenant.password.request');

        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->name('tenant.password.email');

        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
            ->name('tenant.password.reset');

        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->name('tenant.password.store');
    });

    Route::middleware('auth:tenant')->group(function () {
        // Change password routes (must be accessible even if password change is required)
        Route::get('change-password', [ChangePasswordController::class, 'create'])
            ->name('tenant.password.change');
        
        Route::post('change-password', [ChangePasswordController::class, 'store'])
            ->name('tenant.password.update');
        
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
            ->name('tenant.logout');
    });
});