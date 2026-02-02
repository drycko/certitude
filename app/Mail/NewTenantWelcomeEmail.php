<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTenantWelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $tenant;

    /**
     * Create a new message instance.
     */
    public function __construct(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to ' . config('app.name') . ' - Your Account Details',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $domain = $this->tenant->primary_domain ?? $this->tenant->domains->first()?->domain ?? $this->tenant->id . '.certitude.local';
        
        return new Content(
            view: 'emails.tenant-welcome',
            with: [
                'tenantName' => $this->tenant->name,
                'contactPerson' => $this->tenant->contact_person ?? 'Administrator',
                'loginUrl' => 'http://' . $domain . '/login',
                'email' => $this->tenant->email,
                'tempPassword' => $this->tenant->tenant_admin_temp_password,
                'domain' => $domain,
                'plan' => ucfirst($this->tenant->plan ?? 'Starter'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
