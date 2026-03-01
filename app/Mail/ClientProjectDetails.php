<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Services\EmailServiceMatcher;
use App\Models\ContactMessage;

class ClientProjectDetails extends Mailable
{
    use Queueable, SerializesModels;

    public ContactMessage $contact;
    public array $suggestedServices;
    public string $personalMessage;  // ← নতুন

    public function __construct(ContactMessage $contact, string $personalMessage)  // ← নতুন
    {
        $this->contact = $contact;
        $this->personalMessage = $personalMessage;  // ← নতুন
        $this->suggestedServices = EmailServiceMatcher::getSuggestedServices($contact->message);
    }

    public function build()
    {
        return $this->subject(' Project Proposal & Details Just for You - ' . $this->contact->name . '!')
                    ->view('emails.client-project-details');
    }
}
