<?php

namespace App\Mail;

use App\Models\ContactMessage;  // ✅ CHANGE এখানে
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contact  // ✅ CHANGE এখানে
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "📬 New Contact: {$this->contact->subject}",
            replyTo: [$this->contact->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-notification',
            with: [
                'contactData' => [
                    'name'    => $this->contact->name,
                    'email'   => $this->contact->email,
                    'subject' => $this->contact->subject,
                    'message' => $this->contact->message,
                ]
            ]
        );
    }
}
