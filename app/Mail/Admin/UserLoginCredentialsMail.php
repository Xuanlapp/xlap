<?php

namespace App\Mail\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserLoginCredentialsMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create the login credentials email for a newly created account.
     */
    public function __construct(
        public readonly User $user,
        public readonly string $plainPassword,
        public readonly string $appUrl,
        public readonly string $loginUrl,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Thong tin tai khoan dang nhap Offorest',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.user-login-credentials',
        );
    }
}
