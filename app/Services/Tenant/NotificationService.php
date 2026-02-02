<?php

namespace App\Services\Tenant;

use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\AccessRequest;
use App\Mail\EmailVerification;
use App\Mail\AccessRequestDenialEmail;
use App\Mail\WelcomeEmail;

class NotificationService
{
    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user, string $temporaryPassword): bool
    {
        try {
            // Skip if user is inactive
            if (!$user->is_active) {
                Log::warning('User is inactive, skipping welcome email', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return false;
            }
            
            // Prevent duplicate emails using cache lock (1 minute protection)
            $cacheKey = "welcome_email_sent_{$user->id}";
            
            if (\Cache::has($cacheKey)) {
                Log::warning('Welcome email already sent recently, skipping duplicate', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return true; // Return success to avoid error messages
            }
            
            // Set cache flag for 1 minute to prevent duplicates
            \Cache::put($cacheKey, true, now()->addMinute());

            // Log only user info, NOT temp password in production
            $context = [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->roles->pluck('name')->implode(', '),
            ];
            Log::info('Sending welcome email to user', $context);
            $this->logEmail('info', 'welcome_email_sent', $context);

            if (app()->environment('production')) {
                // Send actual welcome email using Mailable
                Mail::to($user->email)->send(new WelcomeEmail($user, $temporaryPassword));
            }

            Log::info('Welcome email sent successfully', $context);
            return true;
        } catch (\Exception $e) {
            $context = [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send welcome email', $context);
            $this->logEmail('error', 'welcome_email_failed', $context);
            
            return false;
        }
    }

    /**
     * Send document expiry alert
     */
    public function sendExpiryAlert(User $user, $documents): bool
    {
        try {
            // Skip if user is inactive
            if (!$user->is_active) {
                Log::info('User is inactive, skipping expiry alert', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return false;
            }
            
            // TODO: Create expiry alert email template
            $context = [
                'user_id' => $user->id,
                'email' => $user->email,
                'document_count' => count($documents),
            ];
            Log::info('Document expiry alert sent', $context);
            $this->logEmail('info', 'expiry_alert_sent', $context);

            return true;
        } catch (\Exception $e) {
            $context = [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send expiry alert', $context);
            $this->logEmail('error', 'expiry_alert_failed', $context);
            
            return false;
        }
    }

    /**
     * Send password change notification
     */
    public function sendPasswordChangeNotification(User $user): bool
    {
        try {
            // Skip if user is inactive
            if (!$user->is_active) {
                Log::info('User is inactive, skipping password change notification', [
                    'user_id' => $user->id,
                    'email' => $user->email
                ]);
                return false;
            }
            
            $context = [
                'user_id' => $user->id,
                'email' => $user->email,
            ];
            Log::info('Password change notification sent', $context);
            $this->logEmail('info', 'password_change_sent', $context);

            return true;
        } catch (\Exception $e) {
            $context = [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send password change notification', $context);
            $this->logEmail('error', 'password_change_failed', $context);
            
            return false;
        }
    }

    /**
     * Send email verification notification (I want a mailable for email verification)
     */
    public function sendEmailVerification(User $user): bool
    {
        try {
            // log sending email verification
            Log::info('Sending email verification notification', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            $this->logEmail('info', 'email_verification_sent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
            if (app()->environment('production')) {
                Mail::to($user->email)->send(new EmailVerification($user));
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email verification notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate temporary password
     */
    public function generateTemporaryPassword(): string
    {
        // Generate password that meets policy: 8 chars, 1 capital, 1 number, 1 special character
        $lowercase = Str::random(4);
        $uppercase = strtoupper(Str::random(1));
        $number = rand(10, 99);
        $special = ['!', '@', '#', '$', '%'][rand(0, 4)];
        
        return $lowercase . $uppercase . $number . $special;
    }

    /**
     * Validate password policy
     */
    public function validatePasswordPolicy(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
        
        return $errors;
    }

    /**
     * Send document upload notification to admins
     */
    public function sendDocumentUploadNotification($document, User $uploader): bool
    {
        try {
            // Get admin users (sending to all super-users only)
            $adminUsers = User::role(['super-user'])->get();

            foreach ($adminUsers as $admin) {
                $context = [
                    'admin_id' => $admin->id,
                    'document_id' => $document->id,
                    'uploader_id' => $uploader->id,
                ];
                Log::info('Document upload notification sent to admin', $context);
                $this->logEmail('info', 'document_upload_notification_sent', $context);
            }

            return true;
        } catch (\Exception $e) {
            $context = [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send document upload notification', $context);
            $this->logEmail('error', 'document_upload_notification_failed', $context);
            
            return false;
        }
    }

    /**
     * Send bulk notification to users
     */
    public function sendBulkNotification(array $userIds, string $subject, string $message): bool
    {
        try {
            $users = User::whereIn('id', $userIds)->get();
            
            foreach ($users as $user) {
                $context = [
                    'user_id' => $user->id,
                    'subject' => $subject,
                    'message' => substr($message, 0, 100) . '...',
                ];
                Log::info('Bulk notification sent', $context);
                $this->logEmail('info', 'bulk_notification_sent', $context);
            }

            return true;
        } catch (\Exception $e) {
            $context = [
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send bulk notification', $context);
            $this->logEmail('error', 'bulk_notification_failed', $context);
            
            return false;
        }
    }

    /**
     * Send access request received notification to admins
     */
    public function sendAccessRequestReceivedNotification(AccessRequest $accessRequest): bool
    {
        try {
            // Get admin users (sending to all super-users only)
            $adminEmails = [
                'colette.le.roux@dole.com',
                'nicolen.mackay@dole.com',
                'antoinette.opperman@dole.com',
                'consult@ukuyila.com',
            ];
            // Get admin users that are super-users and have emails
            $adminUsers = User::whereIn('email', $adminEmails)->get();

            $context = [
                'name' => $accessRequest->name ?? null,
                'email' => $accessRequest->email,
                'phone' => $accessRequest->phone ?? null,
                'company' => $accessRequest->company ?? null,
                'message' => $accessRequest->message ?? null,
                'additional_data' => $accessRequest->except(['name', 'email', 'phone', 'company', 'message']),
                'ip' => $accessRequest->ip(),
                'user_agent' => $accessRequest->header('User-Agent'),
            ];

            $admins = User::role(['admin', 'super-user'])->get();
            $subject = 'New Access Request submitted';
            $body = "A new access request has been submitted:\n\n";
            $body .= "Name: " . ($context['name'] ?? '-') . "\n";
            $body .= "Email: " . $context['email'] . "\n";
            $body .= "Phone: " . ($context['phone'] ?? '-') . "\n";
            $body .= "Company: " . ($context['company'] ?? '-') . "\n\n";
            $body .= "Message:\n" . ($context['message'] ?? '-') . "\n\n";
            $body .= "IP: " . $context['ip'] . "\n";
            $body .= "User Agent: " . ($context['user_agent'] ?? '-') . "\n";

            // if in production, send actual emails ( we will get templates later )
            if (app()->environment('production')) {
                foreach ($admins as $admin) {
                    Mail::raw($body, function ($m) use ($admin, $subject) {
                        $m->to($admin->email)->subject($subject);
                    });
                }
            } else {
                foreach ($adminUsers as $admin) {
                    $context = [
                        'admin_id' => $admin->id,
                        'access_request_id' => $accessRequest->id,
                        'requester_email' => $accessRequest->email,
                    ];
                    Log::info('Access request received notification sent to admin', $context);
                    $this->logEmail('info', 'access_request_received_notification_sent', $context);
                }
            }

            return true;
        } catch (\Exception $e) {
            $context = [
                'access_request_id' => $accessRequest->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send access request received notification', $context);
            $this->logEmail('error', 'access_request_received_notification_failed', $context);
            
            return false;
        }
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordResetNotification(User $user): bool
    {
        try {
            $context = [
                'user_id' => $user->id,
                'email' => $user->email,
            ];

            // log mail in development and send actual email in production
            Log::info('Password reset notification sent', $context);
            $this->logEmail('info', 'password_reset_notification_sent', $context);
            if (app()->environment('production')) {
                // Send actual password reset email using Mailable
                // Assuming we have a PasswordResetEmail mailable
                Mail::to($user->email)->send(new \App\Mail\PasswordResetSuccessEmail($user));
            }

            return true;
        } catch (\Exception $e) {
            $context = [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send password reset notification', $context);
            $this->logEmail('error', 'password_reset_notification_failed', $context);

            return false;
        }
    }

    /**
     * Send access request denial notification to requester
     */
    public function sendAccessRequestDenialNotification(AccessRequest $accessRequest): bool
    {
        try {
            $context = [
                'access_request_id' => $accessRequest->id,
                'requester_email' => $accessRequest->email,
            ];
            Log::info('Access request denial notification sent to requester', $context);
            $this->logEmail('info', 'access_request_denial_notification_sent', $context);

            if (app()->environment('production')) {
                // Send actual denial email using Mailable
                Mail::to($accessRequest->email)->send(new AccessRequestDenialEmail($accessRequest));
            }

            return true;
        } catch (\Exception $e) {
            $context = [
                'access_request_id' => $accessRequest->id,
                'error' => $e->getMessage(),
            ];
            Log::error('Failed to send access request denial notification', $context);
            $this->logEmail('error', 'access_request_denial_notification_failed', $context);
            
            return false;
        }
    }

    /**
     * Log notification (for audit purposes)
     */
    public function logNotification(string $type, string $message, int $userId): void
    {
        $context = [
            'type' => $type,
            'message' => $message,
            'user_id' => $userId,
        ];
        Log::info('Notification sent', $context);
        $this->logEmail('info', 'notification_sent', $context);
    }

    /**
     * Log email-specific events to the dedicated email log channel.
     *
     * @param string $level  Log level: info, error, warning, etc.
     * @param string $event  Short event code (e.g. 'welcome_email_sent')
     * @param array  $context Additional structured context
     */
    public function logEmail(string $level, string $event, array $context = []): void
    {
        try {
            $payload = array_merge([
                'event' => $event,
                'timestamp' => now()->toDateTimeString(),
            ], $context);

            // Use the dedicated 'email' logging channel if available
            if (config('logging.channels.email')) {
                Log::channel('email')->log($level, $event, $payload);
            } else {
                // Fallback to default logging if channel missing
                Log::log($level, $event, $payload);
            }
        } catch (\Exception $e) {
            // As a last resort, write to the default log
            Log::error('Failed to write to email log channel', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send mail
     */
    public function sendMail()
    {
        // Implement mail sending logic here
    }
}