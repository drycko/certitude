<?php
namespace App\Http\Controllers\Portal\PaymentGateways;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionInvoice;
use App\Models\CentralSetting;
use App\Services\PaymentGateways\PayfastGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class PayfastGatewayController extends Controller
{
  protected $payfastGatewayService;
  
  public function __construct(PayfastGatewayService $payfastGatewayService)
  {
    $this->payfastGatewayService = $payfastGatewayService;
  }
  
  /**
  * Initiate PayFast Payment
  */
  public function initiate(Request $request)
  {
    $invoiceId = $request->input('invoice_id');
    $subscriptionInvoice = SubscriptionInvoice::findOrFail($invoiceId);
    
    $payfastForm = $this->payfastGatewayService->buildPayfastForm($subscriptionInvoice);
    
    return response()->json([
      'success' => true,
      'form' => $payfastForm,
    ]);
  }
  
  /**
  * Handle PayFast Return
  *
  * The return URL is used to redirect the user after payment.
  * Per PayFast docs: The ITN (notify_url) is the authoritative source for payment confirmation.
  * This method should only display the result to the user, not update payment status.
  * 
  * Note: PayFast may send data via GET query parameters or POST, we check both.
  */
  public function handleReturn(Request $request)
  {
    try {
      // Log the return for debugging - check both GET and POST
      Log::info('PayFast return received', [
        'get' => $request->query->all(),
        'post' => $request->request->all(),
        'all' => $request->all()
      ]);
      
      // Try to find the invoice from multiple sources
      // PayFast may pass data via GET query params
      // $invoiceId = $request->input('m_payment_id') 
      //           ?? $request->query('m_payment_id')
      //           ?? $request->input('custom_str1')
      //           ?? $request->query('custom_str1');
      
      // // Extract numeric ID from custom_str1 if it starts with 'INV'
      // if ($invoiceId && str_starts_with($invoiceId, 'INV')) {
      //   $invoiceId = (int) substr($invoiceId, 3);
      // }
      
      // if (!$invoiceId) {
      //   Log::warning('PayFast return received without invoice identifier', [
      //     'all_params' => $request->all()
      //   ]);
      //   return redirect()->route('portal.dashboard')
      //     ->with('info', 'Payment processing. You will receive confirmation once complete.');
      // }
      
      // $subscriptionInvoice = SubscriptionInvoice::find($invoiceId);
      // if (!$subscriptionInvoice) {
      //   Log::warning('PayFast return: invoice not found', ['invoice_id' => $invoiceId]);
      //   return redirect()->route('portal.dashboard')
      //     ->with('info', 'Payment processing. You will receive confirmation shortly.');
      // }
      
      // // Check if ITN has already processed the payment
      // $status = strtolower($subscriptionInvoice->status ?? '');
      // if (in_array($status, ['paid', 'completed', 'successful'])) {
      //   return redirect()->route('portal.dashboard')
      //     ->with('success', 'Payment completed successfully! Your subscription is now active.');
      // }
      
      // Payment not yet confirmed by ITN - show pending message
      return redirect()->route('portal.invoices')
        ->with('info', 'Thank you! Your payment is being processed and will be confirmed shortly.');
        
    } catch (\Exception $e) {
      Log::error('Error handling PayFast return', [
        'exception' => $e->getMessage(), 
        'trace' => $e->getTraceAsString(),
        'request' => $request->all()
      ]);
      return redirect()->route('portal.invoices')
        ->with('info', 'Payment processing. You will receive confirmation once complete.');
    }
  }
  
  /**
  * Handle PayFast Cancel
  *
  * User cancelled the payment on the PayFast payment page.
  * Per PayFast docs: This is triggered when user clicks cancel on the payment page.
  */
  public function handleCancel(Request $request)
  {
    Log::info('PayFast payment cancelled by user', [
      'get' => $request->query->all(),
      'post' => $request->request->all()
    ]);
    
    // Try to find invoice from GET or POST params
    $invoiceId = $request->input('m_payment_id')
              ?? $request->query('m_payment_id')
              ?? $request->input('custom_str1')
              ?? $request->query('custom_str1');
    
    // Extract numeric ID from custom_str1 if it starts with 'INV'
    if ($invoiceId && str_starts_with($invoiceId, 'INV')) {
      $invoiceId = (int) substr($invoiceId, 3);
    }
    
    if ($invoiceId) {
      $subscriptionInvoice = SubscriptionInvoice::find($invoiceId);
      if ($subscriptionInvoice) {
        Log::info('Payment cancelled for invoice', ['invoice_id' => $invoiceId]);
        
        // Redirect to invoice page if route exists
        if (\Illuminate\Support\Facades\Route::has('portal.invoices.show')) {
          return redirect()->route('portal.invoices.show', ['invoice' => $subscriptionInvoice->id])
            ->with('warning', 'Payment was cancelled. Your invoice remains unpaid.');
        }
      }
    }
    
    return redirect()->route('portal.dashboard')
      ->with('warning', 'Payment was cancelled. You can try again anytime.');
  }
  
  /**
  * Handle PayFast Notify (ITN - Instant Transaction Notification)
  *
  * Per PayFast Documentation Step 4: Confirm payment is successful
  * This method MUST perform all 4 security checks:
  * 1. Verify the signature
  * 2. Confirm the payment comes from a valid PayFast IP
  * 3. Validate payment data (amount matches)
  * 4. Perform server request to confirm details with PayFast
  *
  * IMPORTANT: Return HTTP 200 to prevent PayFast retries
  */
  public function handleNotify(Request $request)
  {
    // Step 0: Send header 200 immediately to acknowledge receipt
    header('HTTP/1.0 200 OK');
    flush();
    
    $pfData = $request->all();
    Log::info('PayFast ITN received', ['payload' => $pfData]);
    
    // Strip slashes from data
    foreach ($pfData as $key => $val) {
      $pfData[$key] = stripslashes($val);
    }
    
    // Build parameter string for validation (exclude signature)
    $pfParamString = '';
    foreach ($pfData as $key => $val) {
      if ($key !== 'signature') {
        $pfParamString .= $key . '=' . urlencode(trim($val)) . '&';
      }
    }
    $pfParamString = substr($pfParamString, 0, -1); // Remove last ampersand

    $centralSettings = CentralSetting::getSettings([
      'payfast_merchant_id',
      'payfast_merchant_key',
      'payfast_passphrase',
      'payfast_is_test',
    ]);

    $merchantId = $centralSettings['payfast_merchant_id'] ?? '';
    $merchantKey = CentralSetting::getEncryptedSetting('payfast_merchant_key');
    $passphrase = CentralSetting::getEncryptedSetting('payfast_passphrase');
    $pfHost = $this->getPayfastHost($centralSettings['payfast_is_test'] ?? false);
    
    // SECURITY CHECK 1: Verify signature
    $check1 = $this->pfValidSignature($pfData, $pfParamString, $passphrase);
    if (!$check1) {
      Log::warning('PayFast ITN: Signature validation failed', ['payload' => $pfData]);
      return response('OK', 200);
    }
    
    // SECURITY CHECK 2: Verify PayFast IP address
    $check2 = $this->pfValidIP();
    if (!$check2) {
      Log::warning('PayFast ITN: IP validation failed', [
        'ip' => request()->ip(),
        'referer' => request()->header('referer')
      ]);
      return response('OK', 200);
    }

    // $transactionId = $pfData['pf_payment_id'] ?? null;
    
    // Get invoice details
    $transactionId = $pfData['m_payment_id'] ?? $pfData['custom_str1'] ?? null;
    
    // Extract numeric ID from custom_str1 if it starts with 'INV'
    if ($transactionId && str_starts_with($transactionId, 'PF-INV')) {
      // get invoice id after 'PF-INV' before the next '-'
      $invoiceId = (int) substr($transactionId, 6, strpos($transactionId, '-', 6) - 6);
    }
    
    if (!$invoiceId) {
      Log::warning('PayFast ITN: Missing invoice identifier', ['payload' => $pfData]);
      return response('OK', 200);
    }
    
    Log::info('PayFast ITN: Extracted invoice ID', ['invoice_id' => $invoiceId]);
    
    $subscriptionInvoice = SubscriptionInvoice::find($invoiceId);
    if (!$subscriptionInvoice) {
      Log::warning('PayFast ITN: Invoice not found', ['invoice_id' => $invoiceId]);
      return response('OK', 200);
    }
    
    // SECURITY CHECK 3: Validate payment data (amount comparison)
    $cartTotal = $subscriptionInvoice->amount ?? $subscriptionInvoice->total ?? 0;
    $check3 = $this->pfValidPaymentData($cartTotal, $pfData);
    if (!$check3) {
      Log::warning('PayFast ITN: Amount validation failed', [
        'invoice_id' => $invoiceId,
        'expected' => $cartTotal,
        'received' => $pfData['amount_gross'] ?? $pfData['amount'] ?? 'N/A'
      ]);
      return response('OK', 200);
    }
    
    // SECURITY CHECK 4: Server confirmation with PayFast
    $check4 = $this->pfValidServerConfirmation($pfParamString, $pfHost);
    if (!$check4) {
      Log::warning('PayFast ITN: Server confirmation failed');
      return response('OK', 200);
    }
    
    // All security checks passed
    Log::info('PayFast ITN: All security checks passed', ['invoice_id' => $invoiceId]);
    
    // Check payment status
    $paymentStatus = strtoupper($pfData['payment_status'] ?? '');
    if ($paymentStatus === 'COMPLETE') {
      // Payment successful - update invoice
      try {

        $subscription = $subscriptionInvoice->subscription;
        $plan = $subscription->plan;
        
        $subscriptionInvoice->status = 'paid';
        // $subscriptionInvoice->paid_at = now();
        $subscriptionInvoice->save();

        // $meta = $subscriptionInvoice->meta ? json_decode($subscriptionInvoice->meta, true) : [];
        // $meta['payfast_itn'] = $pfData;

        // update subscription status if needed
        $subscription = $subscriptionInvoice->subscription;
        if ($subscription && $subscription->status !== 'active') {
          $subscription->start_date = $subscription->start_date ?? now();
          $subscription->end_date = $subscription->end_date ?? now()->addMonth();
          $subscription->trial_ends_at = null;
          $subscription->status = 'active';
          // $subscription->meta = json_encode($meta);
          $subscription->save();
        }

        // update invoice payment if found
        $newPayment = $subscriptionInvoice->payments()->where('status', 'pending')->first();
        if ($newPayment) {
          $newPayment->transaction_id = $pfData['m_payment_id'] ?? null;
          $newPayment->amount = $pfData['amount_gross'] ?? 0;
          $newPayment->payment_method = 'payfast';
          $newPayment->notes = 'Payment received via PayFast ITN';
          $newPayment->payment_date = now();
          $newPayment->meta = ['notify_payload' => $pfData];
          $newPayment->status = 'completed';
          $newPayment->save();
        }
        else {
          // create new payment record if not found
          $newPayment = $subscriptionInvoice->payments()->create([
            'subscription_id' => $subscriptionInvoice->subscription_id,
            'amount' => $pfData['amount_gross'] ?? 0,
            'payment_method' => 'payfast',
            'notes' => 'Payment received via PayFast ITN',
            'payment_date' => now(),
            'meta' => ['notify_payload' => $pfData],
            'status' => 'completed',
            'transaction_id' => $pfData['m_payment_id'] ?? null,
          ]);
        }
        // $newPayment = $subscriptionInvoice->payments()->updateOrCreate(
        //   [
        //     'transaction_id' => $pfData['m_payment_id'],
        //   ],
        //   [
        //     'subscription_id' => $subscriptionInvoice->subscription_id,
        //     'amount' => $pfData['amount_gross'] ?? 0,
        //     'payment_method' => 'payfast',
        //     'notes' => 'Payment received via PayFast ITN',
        //     'payment_date' => now(),
        //     'meta' => ['notify_payload' => $pfData],
        //     'status' => 'completed',
        //   ]
        // );

        // Update tenant with subscription status
        $tenant = $subscriptionInvoice->tenant;

        $tenant->plan = $plan->name;
        $tenant->subscription_plan_id = $plan->id;
        $tenant->billing_cycle = 'monthly';
        $tenant->trial_ends_at = null;
        $tenant->subscription_status = 'active';
        $tenant->save();

        // get previous active subscriptions for tenant and cancel them
        $prevSubs = Subscription::where('tenant_id', $subscriptionInvoice->tenant_id)
          ->where('id', '!=', $subscription->id)
          ->whereIn('status', ['active', 'trial'])
          ->get();

        foreach ($prevSubs as $prevSub) {
        
          $prevSub->status = 'canceled';
          $prevSub->end_date = now();
          $prevSub->save();

          $prevInvoice = $prevSub->invoices()->where('id', '!=', $subscriptionInvoice->id)
            ->whereIn('status', ['pending', 'partially_paid', 'overdue'])->first();
            if (!$prevInvoice) {
              continue;
            }

            $prevInvoice->status = 'cancelled'; // I messed up in the spelling for this column earlier so keeping it consistent :D
            $prevInvoice->save();

            // cancel all payments associated with the invoice
            $prevInvoice->payments()->each(function ($payment) {
                $payment->status = 'failed';
                $payment->save();
            });
        }
        
        $tenantAdmin = TenantAdmin::where('tenant_id', $tenant->id)->first() ?? $tenant->admin;

        // send subscription activated email to tenant admin
        \Mail::to($tenantAdmin->email)->send(new \App\Mail\SubscriptionActivatedEmail(
          $newPayment,
          $subscriptionInvoice,
          $subscription
        ));

        // send payment receipt email to tenant admin (payfast sends email receipt too)
        \Mail::to($tenantAdmin->email)->send(new \App\Mail\PaymentReceiptEmail(
          $newPayment,
          $subscriptionInvoice,
          $subscription
        ));
        
        Log::info('PayFast ITN: Payment processed successfully', [
          'invoice_id' => $invoiceId,
          'pf_payment_id' => $pfData['pf_payment_id'] ?? null
        ]);
        
      } catch (\Exception $e) {
        Log::error('PayFast ITN: Failed to update invoice', [
          'invoice_id' => $invoiceId,
          'error' => $e->getMessage()
        ]);
      }
      
    } elseif ($paymentStatus === 'CANCELLED') {
      // Subscription cancelled
      // cancel the subscription and invoice and mark as cancelled
      $subscriptionInvoice->status = 'cancelled';
      $subscriptionInvoice->save();
      $subscription = $subscriptionInvoice->subscription;
      if ($subscription) {
        $subscription->status = 'cancelled';
        $subscription->save();

        // if payment exisit for this invoice mark as deleted
        $payment = $subscriptionInvoice->payments()->where('status', 'pending')->first();
        if ($payment) {
          $payment->status = 'cancelled';
          $payment->payment_date = now();
          $payment->notes = 'Payment cancelled via PayFast ITN';
          $payment->save();
        }
      }

      Log::info('PayFast ITN: Subscription cancelled', ['invoice_id' => $invoiceId]);
      
    } else {
      // store other statuses as pending or failed
      $subscriptionInvoice->status = 'pending';
      $subscriptionInvoice->save();

      $payment = $subscriptionInvoice->payments()->where('status', 'pending')->first();
      if ($payment) {
        $payment->status = 'failed';
        $payment->payment_date = now();
        $payment->notes = 'Payment status: ' . $paymentStatus;
        $payment->save();
      }

      // Other status
      Log::info('PayFast ITN: Payment status not final', [
        'invoice_id' => $invoiceId,
        'status' => $paymentStatus
      ]);
    }
    
    return response('OK', 200);
  }
  
  /**
  * Get PayFast host based on environment
  */
  protected function getPayfastHost(bool $isTest = false): string
  {
    $env = $isTest ? 'sandbox' : 'live';
    return $env === 'sandbox' ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
  }
  
  /**
  * SECURITY CHECK 1: Verify signature
  * Per PayFast docs: Calculate and compare MD5 signature
  */
  protected function pfValidSignature(array $pfData, string $pfParamString,  ?string $pfPassphrase = null) {
    // Calculate security signature
    if($pfPassphrase === null) {
        $tempParamString = $pfParamString;
    } else {
        $tempParamString = $pfParamString.'&passphrase='.urlencode( $pfPassphrase );
    }

    $signature = md5( $tempParamString );
    $isValid = ( $pfData['signature'] === $signature );

    if (!$isValid) {
      Log::debug('PayFast signature mismatch', [
        'expected' => $signature,
        'received' => $pfData['signature'],
        'pfPassphrase' => $pfPassphrase,
        'param_string' => $pfParamString
      ]);
    }
    return $isValid;
  }

  // protected function pfValidSignature( $pfData, $pfParamString, $pfPassphrase = null ) {
  //   // Calculate security signature
  //   if($pfPassphrase === null) {
  //       $tempParamString = $pfParamString;
  //   } else {
  //       $tempParamString = $pfParamString.'&passphrase='.urlencode( $pfPassphrase );
  //   }

  //   $signature = md5( $tempParamString );
  //   return ( $pfData['signature'] === $signature );
  // } 
  
  /**
  * SECURITY CHECK 2: Verify PayFast IP address
  * Per PayFast docs: Check payment originates from valid PayFast server
  */
  protected function pfValidIP(): bool
  {
    // Valid PayFast hosts per documentation
    $validHosts = [
      'www.payfast.co.za',
      'sandbox.payfast.co.za',
      'w1w.payfast.co.za',
      'w2w.payfast.co.za',
    ];
    
    $validIps = [];
    foreach ($validHosts as $pfHostname) {
      $ips = gethostbynamel($pfHostname);
      if ($ips !== false) {
        $validIps = array_merge($validIps, $ips);
      }
    }
    
    // Remove duplicates
    $validIps = array_unique($validIps);
    
    // Get referrer IP
    $refererHeader = request()->header('referer');
    if ($refererHeader) {
      $refererHost = parse_url($refererHeader, PHP_URL_HOST);
      if ($refererHost) {
        $referrerIp = gethostbyname($refererHost);
        
        if (in_array($referrerIp, $validIps, true)) {
          return true;
        }
      }
    }
    
    // Also check direct IP if available
    $requestIp = request()->ip();
    if (in_array($requestIp, $validIps, true)) {
      return true;
    }
    
    Log::debug('PayFast IP validation details', [
      'valid_ips' => $validIps,
      'referrer_header' => $refererHeader,
      'request_ip' => $requestIp
    ]);
    
    return false;
  }
  
  /**
  * SECURITY CHECK 3: Validate payment data
  * Per PayFast docs: Verify amount matches expected amount
  */
  protected function pfValidPaymentData(float $cartTotal, array $pfData): bool
  {
    $amountGross = (float)($pfData['amount_gross'] ?? $pfData['amount'] ?? 0);
    
    // Allow small floating point difference (1 cent)
    return !(abs($cartTotal - $amountGross) > 0.01);
  }
  
  /**
  * SECURITY CHECK 4: Server confirmation with PayFast
  * Per PayFast docs: POST data back to PayFast to confirm validity
  */
  protected function pfValidServerConfirmation(string $pfParamString, string $pfHost): bool
  {
    $url = 'https://' . $pfHost . '/eng/query/validate';
    
    try {
      // Use cURL for validation as per PayFast documentation
      $ch = curl_init();
      
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HEADER, false);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $pfParamString);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      
      if ($httpCode !== 200) {
        Log::error('PayFast server confirmation: Invalid HTTP response', [
          'http_code' => $httpCode,
          'response' => $response
        ]);
        return false;
      }
      
      $isValid = (strtoupper(trim($response)) === 'VALID');
      
      Log::debug('PayFast server confirmation', [
        'url' => $url,
        'response' => $response,
        'is_valid' => $isValid
      ]);
      
      return $isValid;
      
    } catch (\Exception $e) {
      Log::error('PayFast server confirmation exception', [
        'error' => $e->getMessage(),
        'url' => $url
      ]);
      return false;
    }
  }
}