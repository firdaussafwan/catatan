<?php

namespace App\Mail;

use App\Support\Registration;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class RegistrationCode extends Mailable
{
    public function __construct(public string $code, public string $name)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your verification code: {$this->code}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.registration-code', with: [
            'code' => $this->code,
            'name' => $this->name,
            'minutes' => Registration::TTL_MINUTES,
        ]);
    }
}
