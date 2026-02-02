<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Portal\PaymentGateways\PayfastGatewayController;
use App\Http\Controllers\Portal\PaymentGateways\PaygateGatewayController;
use App\Http\Controllers\Portal\CentralSettingController;

// All PayFast payment routes are public for guest-facing payments
Route::post('p/payfast/initiate', [PayfastGatewayController::class, 'initiate'])->name('central.payfast.initiate');
Route::match(['get', 'post'], 'p/payfast/return', [PayfastGatewayController::class, 'handleReturn'])->name('central.payfast.return');
Route::match(['get', 'post'], 'p/payfast/cancel', [PayfastGatewayController::class, 'handleCancel'])->name('central.payfast.cancel');
Route::post('p/payfast/notify', [PayfastGatewayController::class, 'handleNotify'])
    // ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('central.payfast.notify');

// All PayGate payment routes are public for guest-facing payments
// Route::post('p/paygate/initiate/{bookingInvoiceId}', [PaygateGatewayController::class, 'initiatePayment'])->name('central.paygate.initiate');
// Route::post('p/paygate/return', [PaygateGatewayController::class, 'return'])->name('central.paygate.return');
// Route::post('p/paygate/cancel', [PaygateGatewayController::class, 'return'])->name('central.paygate.cancel');
// Route::post('p/paygate/notify', [PaygateGatewayController::class, 'notify'])->name('central.paygate.notify');