<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Mail\ClientAutoReply;
use App\Mail\AdminNotification;
use App\Mail\ClientProjectDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Rate limiting
        $key = 'contact-message:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->with('error', 'Too many messages sent. Please try again later.');
        }

        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'required|string|max:2000',
        ]);

        // DB তে save
        ContactMessage::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'subject'    => $validated['subject'] ?? 'Contact Form Submission',
            'message'    => $validated['message'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status'     => 'unread',
        ]);

        RateLimiter::hit($key, 3600);

        // Step 1: Simple auto-reply (24-48 hours)
        try {
            Mail::to($validated['email'])->send(new ClientAutoReply($validated));
            Mail::to(config('mail.from.address'))->send(new AdminNotification($validated));
        } catch (\Exception $e) {
            // Mail fail হলেও form submit হবে
        }

        return back()->with('success', 'Thank you! Your message has been sent successfully. Check your email! 📧');
    }

    // Admin থেকে project details email পাঠানো
   public function sendProjectDetails(Request $request, $id)
{
    $contact = ContactMessage::findOrFail($id);

    if ($contact->project_details_sent) {
        return back()->with('error', 'Project details already sent to this client!');
    }

    $request->validate([
        'personal_message' => 'required|string|min:20',
    ]);

    try {
        Mail::to($contact->email)->send(
            new ClientProjectDetails($contact, $request->personal_message)
        );

        $contact->update([
            'project_details_sent'    => true,
            'project_details_sent_at' => now(),
        ]);

        return back()->with('success', 'Reply sent to ' . $contact->email . '! 🚀');
    } catch (\Exception $e) {
        return back()->with('error', 'Failed to send email: ' . $e->getMessage());
    }
}
}
