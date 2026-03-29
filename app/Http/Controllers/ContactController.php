<?php

namespace App\Http\Controllers;

use App\Jobs\SendContactEmail;
use App\Mail\ClientProjectDetails;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class ContactController extends Controller
{
    public function store(Request $request)
    {
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

        // ✅ Create first
        $contact = ContactMessage::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'subject'    => $validated['subject'] ?? 'Contact Form Submission',
            'message'    => $validated['message'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status'     => 'unread',
        ]);

        RateLimiter::hit($key, 3600);

        // ✅ Dispatch ContactMessage object
        SendContactEmail::dispatch($contact);

        return back()->with('success', 'Thank you! Your message has been sent successfully. Check your email! 📧');
    }

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
