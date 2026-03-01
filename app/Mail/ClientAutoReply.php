<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientAutoReply extends Mailable
{
    use Queueable, SerializesModels;

    public array $contactData;

    public function __construct(array $contactData)
    {
        $this->contactData = $contactData;
    }

    public function build()
    {
        return $this->subject(' Message Received - I\'ll contact you within 24-48 hours!')
                    ->view('emails.client-auto-reply');
    }
}
