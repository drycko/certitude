<?php

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Tenant\User; // Make sure to import User if you use it
use App\Models\Tenant\TenantSettings;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $temporaryPassword)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->tenantSupportEmail = TenantSettings::getSetting('support_email', config('app.email', 'support@example.com'));
    }

    /**
     * Build the message.
     */
    public function build()
    {
        // Determine template based on user role (priority: grower > customer > default)
        $template = 'emails.tenant.welcome'; // default
        
        if ($this->user->isGrowerRole()) {
            $template = 'emails.tenant.grower_welcome';
        } elseif ($this->user->isCustomerRole()) {
            $template = 'emails.tenant.customer_welcome';
        }

        return $this
            ->subject('Welcome to ' . (current_tenant()->name ?? config('app.name')) . ' Portal')
            ->markdown($template)
            ->with([
                'user' => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'tenantSupportEmail' => $this->tenantSupportEmail,
            ]);
    }
}
