<?php
namespace App\Services\PaymentGateways;

use App\Models\CentralSetting;
use App\Models\SubscriptionInvoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PayfastGatewayService
{
  /**
  * build payfast form for posting
  * @param array $paymentDetails
  * @param string $merchantId
  * @param string $merchantKey
  * @param string $passphrase
  * @param bool $testingMode
  * @return string
  */
  public function buildPayfastForm(SubscriptionInvoice $subscriptionInvoice): string
  {
    $centralSettings = CentralSetting::getSettings([
      'payfast_merchant_id',
      'payfast_merchant_key',
      'payfast_passphrase',
      'payfast_is_test',
    ]);

    $subscription = $subscriptionInvoice->subscription;

    $plan = $subscription->plan;

    $merchant_id = $centralSettings['payfast_merchant_id'] ?? '';
    $merchant_key = CentralSetting::getEncryptedSetting('payfast_merchant_key');
    $passphrase = CentralSetting::getEncryptedSetting('payfast_passphrase');
    // $merchant_id = '10024789'; // temp hardcode for testing
    // $merchant_key = 'dtz5khr0cbz74'; // temp hardcode for testing
    // $passphrase = 'AnotidaL2022'; // temp hardcode for testing
    $testingMode = $centralSettings['payfast_is_test'] ?? true;
    // Construct variables
    // $cartTotal = $subscriptionInvoice->remaining_amount; // This amount needs to be sourced from your application;
    $tenantAdmin = $subscriptionInvoice->tenant->admin;
    $admin_first_last = explode(' ', $tenantAdmin->name, 2);
    // get payment id from latest invoice payment or generate new
    $lastPayment = $subscriptionInvoice->payments()->latest()->first();
    if ($lastPayment) {
      $paymentId = $lastPayment->transaction_id ?? $paymentId = 'PF-INV' . $subscriptionInvoice->id . '-P'. $plan->id . '-S' . $subscription->id . '-' . now()->timestamp;
    } else {
      $paymentId = 'PF-INV' . $subscriptionInvoice->id . '-P' . $plan->id . '-S' . $subscription->id . '-' . now()->timestamp;
      // create a pending payment record
      $subscriptionInvoice->payments()->create([
        'subscription_id' => $subscription->id,
        // 'invoice_id' => $subscriptionInvoice->id, already set by relationship
        'amount' => $subscription->price,
        'payment_date' => null,
        'payment_method' => 'payfast',
        'notes' => "Payment for invoice {$subscriptionInvoice->invoice_number}",
        'transaction_id' => $paymentId,
        'meta' => ['billing_cycle' => $subscription->billing_cycle],
        'status' => 'pending',
      ]);
    }
    // Set payment details
    $paymentDetails = [
        'amount' => $subscriptionInvoice->remaining_balance,
        'item_name' => config('app.name') . ' Invoice #' . $subscriptionInvoice->invoice_number,
        'name_first' => $admin_first_last[0],
        'name_last' => $admin_first_last[1] ?? '',
        'email_address' => $tenantAdmin->email ?? $subscriptionInvoice->tenant->email,
        'cell_number' => $tenantAdmin->phone,
        'm_payment_id' => $paymentId,
    ];
    $cartTotal = $subscriptionInvoice->remaining_balance;

    $data = array(
        // Merchant details
        'merchant_id' => $merchant_id,
        'merchant_key' => $merchant_key,
        'return_url' => route('central.payfast.return'),
        'cancel_url' => route('central.payfast.cancel'),
        'notify_url' => route('central.payfast.notify'),
        // Buyer details
        'name_first' => $paymentDetails['name_first'],
        'name_last'  => $paymentDetails['name_last'],
        'email_address'=> $paymentDetails['email_address'],
        // Transaction details
        'm_payment_id' => $paymentDetails['m_payment_id'],
        'amount' => number_format( sprintf( '%.2f', $cartTotal ), 2, '.', '' ),
        'item_name' => $paymentDetails['item_name'],
        // Add custom field as backup identifier
        'custom_str1' => 'INV_' . $subscriptionInvoice->id,
        'custom_str2' => 'SUB_' . $subscription->id,
        'custom_str3' => 'BC_' . Str::slug($subscription->billing_cycle, '_'),
    );

    \Log::info('Passphrase for signature: ' . $passphrase);

    $signature = $this->generateSignature($data, $passphrase);
    $data['signature'] = $signature;
    \Log::info('PayFast form data', $data);

    // If in testing mode make use of either sandbox.payfast.co.za or www.payfast.co.za
    $pfHost = $testingMode ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    $htmlForm = '<form action="https://'.$pfHost.'/eng/process" method="post">';
    foreach($data as $name=> $value)
    {
      $htmlForm .= '<input name="'.$name.'" type="hidden" value=\''.$value.'\' />';
    }
    $htmlForm .= '<button type="submit" class="btn btn-danger">
      <i class="fas fa-credit-card me-2"></i>Pay with PayFast
      </button></form>';
    return $htmlForm;
  }
    
  /**
  * Initiate a PayFast payment.
  */
  public function initiatePayment(array $paymentDetails)
  {
    try {
      $centralSettings = CentralSetting::getSettings([
        'payfast_merchant_id',
        'payfast_merchant_key',
        'payfast_passphrase',
        'payfast_is_test',
      ]);
      $merchant_id = $centralSettings['payfast_merchant_id'] ?? '';
      $merchant_key = CentralSetting::getEncryptedSetting('payfast_merchant_key');
      $passphrase = CentralSetting::getEncryptedSetting('payfast_passphrase');
      $testingMode = $centralSettings['payfast_is_test'] ?? true;

      $data = array(
        'merchant_id' => $merchant_id,
        'merchant_key' => $merchant_key,
        'return_url' => route('central.payfast.return'),
        'cancel_url' => route('central.payfast.cancel'),
        'notify_url' => route('central.payfast.notify'),
        'name_first' => $paymentDetails['name_first'],
        'name_last' => $paymentDetails['name_last'],
        'email_address' => $paymentDetails['email_address'],
        'cell_number' => $paymentDetails['cell_number'],
        'm_payment_id' => $paymentDetails['m_payment_id'],
        'amount' => number_format($paymentDetails['amount'], 2, '.', ''),
        'item_name' => $paymentDetails['item_name'],
      );
      $signature = $this->generateSignature($data, $passphrase);
      $data['signature'] = $signature;
      // If in testing mode make use of either sandbox.payfast.co.za or www.payfast.co.za
      $pfHost = $testingMode ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
      $payfastUrl = 'https://' . $pfHost . '/eng/process';
      // Log::info('PayFast payment data', $data);
      // post to PayFast
      $response = Http::asForm()->post($payfastUrl, $data);
      // log response for debugging
      // Log::info('PayFast response', ['status' => $response->status(), 'body' => $response->body()]);
      return $response->body();
    } catch (\Exception $e) {
      Log::error('Error initiating PayFast payment: ' . $e->getMessage());
      throw $e;
    }
  }
  
  /**
   * @param array $data
   * @param null $passPhrase
   * @return string
   */
  private function generateSignature($data, $passPhrase = null) {
    // Create parameter string
    $pfOutput = '';
    foreach( $data as $key => $val ) {
        if($val !== '') {
            $pfOutput .= $key .'='. urlencode( trim( $val ) ) .'&';
        }
    }
    // Remove last ampersand
    $getString = substr( $pfOutput, 0, -1 );
    if( $passPhrase !== null ) {
        $getString .= '&passphrase='. urlencode( trim( $passPhrase ) );
    }
    return md5( $getString );
  }

  /**
   * Validate PayFast IPN
   */
  public function validateIPN($data)
  {
    $centralSettings = CentralSetting::getSettings([
      'payfast_merchant_id',
      'payfast_merchant_key',
      'payfast_passphrase',
      'payfast_is_test',
    ]);
    $testingMode = $centralSettings['payfast_is_test'] ?? true;
    // If in testing mode make use of either sandbox.payfast.co.za or www.payfast.co.za
    $pfHost = $testingMode ? 'sandbox.payfast.co.za' : 'www.payfast.co.za';
    $pfUrl = 'https://' . $pfHost . '/eng/query/validate';

    // Post back to PayFast system to validate
    $response = Http::asForm()->post($pfUrl, $data);
    if ($response->body() === 'VALID') {
      return true;
    }
    return false;
  }
}