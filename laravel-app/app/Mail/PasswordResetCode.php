<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The 6-digit "forgot password" code (see PasswordResetService). */
class PasswordResetCode extends Mailable
{
    public function __construct(public string $code, public string $name, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your RehabPlus password reset code');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.password-reset-code');
    }
}
