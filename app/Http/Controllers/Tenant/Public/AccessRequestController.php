<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\AccessRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AccessRequestController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Show the access request form to guests.
     */
    public function create()
    {
        $countries = get_countries();
        return view('access.request_access', compact('countries'));
    }

    /**
     * Handle access request submission.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'company' => 'nullable|string|max:255',
            'message' => 'nullable|string|max:2000',
        ]);



        // Build context for logging and email
        $context = [
            'name' => $validated['name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'company' => $validated['company'] ?? null,
            'message' => $validated['message'] ?? null,
            'additional_data' => $request->except(['name', 'email', 'phone', 'company', 'message']),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        // Persist the request so we have a record even if emails fail
        $accessRequest = AccessRequest::create([
            'name' => $context['name'] ?? null,
            'email' => $context['email'],
            'phone' => $context['phone'] ?? null,
            'company' => $context['company'] ?? null,
            'message' => $context['message'] ?? null,
            'ip' => $context['ip'],
            'user_agent' => $context['user_agent'] ?? null,
            'status' => 'pending',
            'additional_data' => $context['additional_data'],
        ]);

        // Log the request to the email log channel
        $this->notificationService->logEmail('info', 'access_request_submitted', array_merge($context, ['access_request_id' => $accessRequest->id]));

        // Notify admin users by email (simple raw email)
        try {
            // admin emails to notify
            $notifyAdmins = config('mail.access_request_notify_admins', true);
            if (!$notifyAdmins) {
                throw new \Exception('Admin notifications for access requests are disabled in config.');
            }

            $adminEmails = config('mail.access_request_admin_emails', []);

            if (empty($adminEmails)) {
                
                $admins = User::role(['admin', 'super-user'])->get();
                $adminEmails = $admins->pluck('email')->toArray();
            }

            $subject = 'New Access Request submitted';
            $body = "A new access request has been submitted:\n\n";
            $body .= "Name: " . ($context['name'] ?? '-') . "\n";
            $body .= "Email: " . $context['email'] . "\n";
            $body .= "Phone: " . ($context['phone'] ?? '-') . "\n";
            $body .= "Company: " . ($context['company'] ?? '-') . "\n\n";
            $body .= "Message:\n" . ($context['message'] ?? '-') . "\n\n";
            $body .= "IP: " . $context['ip'] . "\n";
            $body .= "User Agent: " . ($context['user_agent'] ?? '-') . "\n";

            foreach ($adminEmails as $adminEmail) {
                Mail::raw($body, function ($m) use ($adminEmail, $subject) {
                    $m->to($adminEmail)->subject($subject);
                });
            }

            // Also log that emails were dispatched and update request
            $this->notificationService->logEmail('info', 'access_request_notified_admins', [
                'admin_count' => count($adminEmails),
                'requester_email' => $context['email'],
                'access_request_id' => $accessRequest->id,
            ]);
            $accessRequest->update(['status' => 'notified']);
        } catch (\Exception $e) {
            // Log failure but don't expose to user
            $this->notificationService->logEmail('error', 'access_request_notify_failed', [
                'error' => $e->getMessage(),
                'requester_email' => $context['email'],
                'access_request_id' => $accessRequest->id,
            ]);
            $accessRequest->update(['status' => 'notify_failed']);
        }

        return redirect()->back()->with('success', 'Your access request has been submitted. We will contact you via email shortly.');
    }
}
