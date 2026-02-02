<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $feature  Optional feature to check access for
     */
    public function handle(Request $request, Closure $next, ?string $feature = null): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            return $next($request);
        }

        // Check if tenant has active subscription
        if (!$tenant->hasActiveSubscription()) {
            // Allow grace period for limited access
            if (!$tenant->isInGracePeriod()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => 'No active subscription found.',
                        'message' => $tenant->getSubscriptionStatusMessage(),
                    ], 403);
                }

                // Redirect to tenant error page with subscription message
                return redirect()->route('tenant.error')
                    ->with('error', $tenant->getSubscriptionStatusMessage() . ' Please contact your administrator or visit the billing portal.');
            }

            // In grace period - show warning
            session()->flash('warning', 'Your subscription has expired. You have limited access during the grace period. Please renew soon.');
        }

        // Check for specific feature access if required
        if ($feature && !$tenant->canAccessFeature($feature)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Feature not available in your current plan.',
                    'feature' => $feature,
                ], 403);
            }

            return redirect()->back()
                ->with('error', 'This feature is not available in your current subscription plan.');
        }

        // Warn about unpaid invoices
        if ($tenant->hasUnpaidInvoices()) {
            $subscription = $tenant->getActiveSubscription();
            $unpaidCount = $tenant->invoices()->whereIn('status', ['pending', 'overdue'])->count();
            
            if ($unpaidCount > 0) {
                session()->flash('warning', "You have {$unpaidCount} unpaid invoice(s). Please settle your account to avoid service interruption.");
            }
        }

        // Warn about expiring subscription
        $subscription = $tenant->getActiveSubscription();
        if ($subscription) {
            $daysRemaining = now()->diffInDays($subscription->end_date, false);
            
            // Check if on trial
            if ($tenant->isOnTrial()) {
                $trialDays = $tenant->getTrialDaysRemaining();
                if ($trialDays !== null && $trialDays <= 7) {
                    session()->flash('info', "Your trial expires in {$trialDays} day(s). Please subscribe to continue using the service after the trial period.");
                }
            } elseif ($daysRemaining > 0 && $daysRemaining <= 7) {
                session()->flash('info', "Your subscription expires in {$daysRemaining} day(s). Please renew soon.");
            }
        }

        return $next($request);
    }
}
