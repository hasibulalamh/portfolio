<?php

namespace Tests\Feature;

use App\Mail\NewSubmissionMail;
use App\Mail\SubmissionReceivedMail;
use App\Models\ContactInfo;
use App\Models\ContactMessage;
use App\Models\MeetingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The two public submission endpoints end to end through the real stack — route,
 * throttle, FormRequest, model and SubmissionNotifier — against the test
 * database, with the transport faked.
 *
 * Integration rather than unit: the point is that the pieces are *wired*. The
 * notifier's own branching is covered in isolation by
 * tests/Unit/SubmissionNotifierTest.php (which names this file as its integration
 * counterpart) and the validation rules by tests/Unit/FormRequestValidationTest.php.
 * What neither can see is whether the controller actually calls the notifier,
 * whether the row is committed independently of the send, and whether the two
 * emails reach the two different addresses they are meant to.
 *
 * That last point is the gap the 2026-08-25 email-flow audit called out by name:
 * flows 1–4 had zero PHPUnit coverage, and "a Mail::fake() test per public
 * endpoint asserting assertSent(NewSubmissionMail) AND
 * assertSent(SubmissionReceivedMail) with the right recipient would have caught a
 * missing dispatch or a swapped admin/client address".
 */
class PublicSubmissionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = 'owner@portfolio.test';

    protected function setUp(): void
    {
        parent::setUp();

        // Pinned so the admin-recipient assertions do not depend on whatever the
        // developer's own .env holds. The CMS fallback gets its own test below.
        config(['mail.admin_notify_address' => self::ADMIN]);
    }

    /** @return array<string, mixed> */
    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dana Visitor',
            'email' => 'dana@example.com',
            'subject' => 'Contract work',
            'message' => 'Are you free next month?',
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function meetingPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Client',
            'email' => 'jane@example.com',
            'preferred_date' => '2026-09-15',
            'preferred_time' => '09:00',
            'message' => 'Can we talk about the rebuild?',
        ], $overrides);
    }

    // =====================================================================
    // Contact messages
    // =====================================================================

    public function test_a_contact_submission_creates_a_row_with_exactly_what_was_posted(): void
    {
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload())->assertCreated();

        $this->assertDatabaseCount('contact_messages', 1);
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Dana Visitor',
            'email' => 'dana@example.com',
            'subject' => 'Contract work',
            'message' => 'Are you free next month?',
        ]);
    }

    public function test_a_new_contact_message_starts_unread_and_unanswered(): void
    {
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload())->assertCreated();

        $message = ContactMessage::query()->sole();

        $this->assertFalse((bool) $message->is_read);
        $this->assertNull($message->admin_reply);
        $this->assertNull($message->replied_at);
        $this->assertNull($message->delivery_failed_at);
    }

    public function test_a_contact_submission_dispatches_both_emails_to_two_different_recipients(): void
    {
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload())->assertCreated();

        // The admin notification goes to the configured owner address...
        Mail::assertSent(
            NewSubmissionMail::class,
            fn (NewSubmissionMail $mail) => $mail->hasTo(self::ADMIN),
        );

        // ...and the acknowledgment to the visitor. A swap would still send two
        // emails, which is why the recipient is asserted and not just the count.
        Mail::assertSent(
            SubmissionReceivedMail::class,
            fn (SubmissionReceivedMail $mail) => $mail->hasTo('dana@example.com'),
        );

        Mail::assertSentCount(2);
    }

    public function test_the_contact_notification_can_be_replied_to_directly(): void
    {
        // The replyTo header is what lets the admin answer from their own mail
        // client rather than from the site's sending identity.
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload())->assertCreated();

        Mail::assertSent(
            NewSubmissionMail::class,
            fn (NewSubmissionMail $mail) => $mail->hasReplyTo('dana@example.com'),
        );
    }

    public function test_the_contact_notification_renders_the_submitted_content(): void
    {
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload([
            'message' => 'A very specific enquiry body.',
        ]))->assertCreated();

        Mail::assertSent(NewSubmissionMail::class, function (NewSubmissionMail $mail) {
            $rendered = $mail->render();

            return str_contains($rendered, 'Dana Visitor')
                && str_contains($rendered, 'A very specific enquiry body.');
        });
    }

    public function test_the_contact_response_carries_no_record_back_to_the_public_caller(): void
    {
        // A public caller has no reason to read the stored row back, and shipping
        // it would hand out the id the admin endpoints address.
        Mail::fake();

        $response = $this->postJson('/api/contact-messages', $this->contactPayload());

        $response->assertCreated();
        $response->assertJsonPath('data', null);
        $this->assertStringContainsString('Thanks for reaching out', $response->json('message'));
    }

    public function test_admin_only_fields_in_a_contact_payload_are_ignored(): void
    {
        // `create()` is fed validated data only, so a visitor cannot mark their
        // own message read or pre-fill a reply.
        Mail::fake();

        $this->postJson('/api/contact-messages', $this->contactPayload([
            'is_read' => true,
            'admin_reply' => 'I replied to myself.',
            'replied_at' => '2026-01-01 00:00:00',
        ]))->assertCreated();

        $message = ContactMessage::query()->sole();

        $this->assertFalse((bool) $message->is_read);
        $this->assertNull($message->admin_reply);
        $this->assertNull($message->replied_at);
    }

    // =====================================================================
    // Meeting requests
    // =====================================================================

    public function test_a_meeting_submission_creates_a_pending_row(): void
    {
        Mail::fake();

        $this->postJson('/api/meeting-requests', $this->meetingPayload())->assertCreated();

        $this->assertDatabaseCount('meeting_requests', 1);

        $request = MeetingRequest::query()->sole();

        $this->assertSame('Jane Client', $request->name);
        $this->assertSame('jane@example.com', $request->email);
        $this->assertSame('2026-09-15', $request->preferred_date->toDateString());
        $this->assertSame('09:00', $request->preferred_time);
        // Status is set by the controller, not by the visitor's payload.
        $this->assertSame(MeetingRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->replied_at);
        $this->assertNull($request->delivery_failed_at);
    }

    public function test_a_meeting_submission_dispatches_both_emails_to_two_different_recipients(): void
    {
        Mail::fake();

        $this->postJson('/api/meeting-requests', $this->meetingPayload())->assertCreated();

        Mail::assertSent(
            NewSubmissionMail::class,
            fn (NewSubmissionMail $mail) => $mail->hasTo(self::ADMIN),
        );
        Mail::assertSent(
            SubmissionReceivedMail::class,
            fn (SubmissionReceivedMail $mail) => $mail->hasTo('jane@example.com'),
        );

        Mail::assertSentCount(2);
    }

    public function test_the_meeting_acknowledgment_repeats_the_requested_slot(): void
    {
        // The visitor's only confirmation of what they asked for, so the date and
        // the time label both have to survive into the rendered body.
        Mail::fake();

        $this->postJson('/api/meeting-requests', $this->meetingPayload())->assertCreated();

        Mail::assertSent(SubmissionReceivedMail::class, function (SubmissionReceivedMail $mail) {
            $rendered = $mail->render();

            return str_contains($rendered, 'Jane Client') && str_contains($rendered, '09:00');
        });
    }

    public function test_a_meeting_request_needs_only_a_name_and_an_email(): void
    {
        // Both optional slot fields are nullable on the public form.
        Mail::fake();

        $this->postJson('/api/meeting-requests', [
            'name' => 'Minimal Mick',
            'email' => 'mick@example.com',
        ])->assertCreated();

        $request = MeetingRequest::query()->sole();

        $this->assertNull($request->preferred_date);
        $this->assertNull($request->preferred_time);
        $this->assertSame(MeetingRequest::STATUS_PENDING, $request->status);
    }

    public function test_a_visitor_cannot_submit_a_meeting_request_as_already_replied(): void
    {
        Mail::fake();

        $this->postJson('/api/meeting-requests', $this->meetingPayload([
            'status' => MeetingRequest::STATUS_REPLIED,
            'admin_reply' => 'Self-service reply.',
            'admin_note' => 'Internal.',
        ]))->assertCreated();

        $request = MeetingRequest::query()->sole();

        $this->assertSame(MeetingRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->admin_reply);
        $this->assertNull($request->admin_note);
    }

    // =====================================================================
    // Behaviour shared by both endpoints
    // =====================================================================

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function publicEndpoints(): array
    {
        return [
            'contact message' => ['/api/contact-messages', 'contact_messages'],
            'meeting request' => ['/api/meeting-requests', 'meeting_requests'],
        ];
    }

    /** @return array<string, mixed> */
    private function payloadFor(string $endpoint, array $overrides = []): array
    {
        return $endpoint === '/api/contact-messages'
            ? $this->contactPayload($overrides)
            : $this->meetingPayload($overrides);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicEndpoints')]
    public function test_a_submission_still_succeeds_when_the_transport_refuses(
        string $endpoint,
        string $table,
    ): void {
        // The whole reason SubmissionNotifier swallows its own failures: a visitor
        // must never see a 500 for a submission that was already saved.
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('transport unreachable'));

        $this->postJson($endpoint, $this->payloadFor($endpoint))->assertCreated();

        $this->assertDatabaseCount($table, 1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicEndpoints')]
    public function test_a_failure_on_one_email_does_not_stop_the_other(
        string $endpoint,
        string $table,
    ): void {
        // Resend refused arbitrary visitor addresses throughout this project's
        // sandbox era while accepting the owner's, so exactly this split was the
        // normal outcome: the admin notification lands, the acknowledgment does not.
        $client = $endpoint === '/api/contact-messages' ? 'dana@example.com' : 'jane@example.com';

        Mail::shouldReceive('to')->with(self::ADMIN)->once()->andReturnSelf();
        Mail::shouldReceive('to')->with($client)->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andReturnNull();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('refused'));

        $this->postJson($endpoint, $this->payloadFor($endpoint))->assertCreated();

        $this->assertDatabaseCount($table, 1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicEndpoints')]
    public function test_a_rejected_submission_writes_nothing_and_sends_nothing(
        string $endpoint,
        string $table,
    ): void {
        Mail::fake();

        $this->postJson($endpoint, $this->payloadFor($endpoint, ['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount($table, 0);
        Mail::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicEndpoints')]
    public function test_the_admin_address_falls_back_to_the_cms_contact_info(
        string $endpoint,
        string $table,
    ): void {
        // With ADMIN_NOTIFY_EMAIL unset the notifier reads contact_info.email,
        // which is admin-maintained CMS data. Exercising that needs a real row,
        // which is why it lives here rather than in the unit suite.
        config(['mail.admin_notify_address' => null]);
        ContactInfo::singleton()->update(['email' => 'cms-inbox@portfolio.test']);

        Mail::fake();

        $this->postJson($endpoint, $this->payloadFor($endpoint))->assertCreated();

        Mail::assertSent(
            NewSubmissionMail::class,
            fn (NewSubmissionMail $mail) => $mail->hasTo('cms-inbox@portfolio.test'),
        );
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicEndpoints')]
    public function test_the_endpoint_is_rate_limited(string $endpoint, string $table): void
    {
        // Unauthenticated writes reachable by anyone on the internet. The group
        // carries throttle:10,1, so the eleventh attempt in a minute is refused.
        Mail::fake();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson($endpoint, $this->payloadFor($endpoint, ['message' => "Body {$i}"]))
                ->assertCreated();
        }

        $this->postJson($endpoint, $this->payloadFor($endpoint))->assertStatus(429);

        $this->assertDatabaseCount($table, 10);
    }
}
