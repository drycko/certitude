<?php

namespace App\Mail\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User; // Make sure to import User if you use it

class PasswordResetSuccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    // public $token;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        // $this->token = $token;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Your Password Has Been Reset')
            ->markdown('emails.tenant.password_reset')
            ->with([
                'user' => $this->user,
                // 'token' => $this->token,
            ]);
    }
}