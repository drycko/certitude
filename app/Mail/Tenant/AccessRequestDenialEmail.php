<?php

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Tenant\User; // Make sure to import User if you use it

class AccessRequestDenialEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Access Request Denied')
            ->markdown('emails.tenant.access_request_denial')
            ->with([
                'user' => $this->user,
            ]);
    }
}
