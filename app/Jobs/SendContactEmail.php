<?php

namespace App\Jobs;

use App\Mail\ContactNotification;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendContactEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contact
    ) {}

    public function handle(): void
    {
        try {
            // ✅ Only send to admin (auto-reply already exists)
            Mail::to(config('mail.from.address'))
                ->send(new ContactNotification($this->contact));

            // ✅ Mark as sent
            $this->contact->update([
                'email_sent' => true,
                'email_sent_at' => now(),
            ]);

            Log::info("Contact email sent to admin: {$this->contact->email}");

        } catch (\Exception $e) {
            Log::error("Email send failed: {$e->getMessage()}");
            $this->release(60); // Retry after 60 seconds
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Contact job failed: {$exception->getMessage()}");
    }
}
