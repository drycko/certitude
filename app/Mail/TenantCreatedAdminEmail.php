<?php

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantCreatedAdminEmail extends Mailable
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
            subject: 'New Tenant Created: ' . $this->tenant->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tenant-created-admin',
            with: [
                'tenantName' => $this->tenant->name,
                'tenantEmail' => $this->tenant->email,
                'tenantDomain' => $this->tenant->primary_domain ?? $this->tenant->domains->first()?->domain,
                'tenantPlan' => $this->tenant->plan,
                'createdAt' => $this->tenant->created_at->format('Y-m-d H:i:s'),
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
