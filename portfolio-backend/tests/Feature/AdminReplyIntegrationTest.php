<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReplyMail;
use App\Mail\MeetingRequestReplyMail;
use App\Models\ContactMessage;
use App\Models\MeetingRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The two admin reply flows treated as one contract.
 *
 * ContactMessageReplyEndpointTest and MeetingRequestReplyEndpointTest each cover
 * their own endpoint thoroughly. This suite covers what neither can: that the two
 * are *equivalent*. The contact reply was built deliberately to the same standard
 * as the meeting reply and both share one frontend implementation of the reply
 * semantics (portfolio-admin/lib/useReplyAction.js branches on status alone), so a
 * divergence between them is a bug in whichever one drifted — and a per-endpoint
 * suite cannot notice a divergence by construction.
 *
 * Everything here is asserted through the same table for both types, so adding a
 * third reply flow later means adding one row to the provider.
 */
class AdminReplyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One row per reply flow: the HTTP verb, the URL template, the Mailable, the
     * table, and the recipient the reply must reach.
     *
     * @return array<string, array{0: string, 1: string, 2: class-string, 3: string, 4: string}>
     */
    public static function replyFlows(): array
    {
        return [
            'contact message' => [
                'POST',
                '/api/admin/contact-messages/%d/reply',
                ContactMessageReplyMail::class,
                'contact_messages',
                'dana@example.com',
            ],
            'meeting request' => [
                'PUT',
                '/api/admin/meeting-requests/%d/reply',
                MeetingRequestReplyMail::class,
                'meeting_requests',
                'jane@example.com',
            ],
        ];
    }

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    /** The record a given flow replies to. */
    private function record(string $table): ContactMessage|MeetingRequest
    {
        return $table === 'contact_messages'
            ? ContactMessage::query()->create([
                'name' => 'Dana Sender',
                'email' => 'dana@example.com',
                'subject' => 'Project inquiry',
                'message' => 'Are you available for contract work?',
            ])
            : MeetingRequest::query()->create([
                'name' => 'Jane Client',
                'email' => 'jane@example.com',
                'message' => 'Can we talk next week?',
                'status' => MeetingRequest::STATUS_PENDING,
            ]);
    }

    /** A second record of the same type, used to prove replies do not bleed. */
    private function bystander(string $table): ContactMessage|MeetingRequest
    {
        return $table === 'contact_messages'
            ? ContactMessage::query()->create([
                'name' => 'Other Person',
                'email' => 'other@example.com',
                'message' => 'Unrelated enquiry.',
            ])
            : MeetingRequest::query()->create([
                'name' => 'Other Person',
                'email' => 'other@example.com',
                'status' => MeetingRequest::STATUS_PENDING,
            ]);
    }

    private function reply(string $verb, string $template, int $id, string $text): \Illuminate\Testing\TestResponse
    {
        return $this->json($verb, sprintf($template, $id), ['admin_reply' => $text]);
    }

    // =====================================================================
    // The shape both flows must share
    // =====================================================================

    #[DataProvider('replyFlows')]
    public function test_a_delivered_reply_answers_200_and_returns_the_saved_record(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        Mail::fake();
        $this->actAsAdmin();

        $record = $this->record($table);

        $response = $this->reply($verb, $template, $record->id, 'Happy to help.');

        $response->assertOk();
        $response->assertJsonPath('message', 'Reply sent successfully.');
        $response->assertJsonPath('data.admin_reply', 'Happy to help.');
        $response->assertJsonPath('data.delivery_failed_at', null);
        $this->assertNotNull($response->json('data.replied_at'));
    }

    #[DataProvider('replyFlows')]
    public function test_a_refused_reply_answers_502_and_still_returns_the_saved_record(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        // The single most important shared property. `apiCall()` derives its
        // success boolean from the status code and nothing else, so a 200 here is
        // what produced the false-success toast this project spent a session on.
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('transport refused'));
        $this->actAsAdmin();

        $record = $this->record($table);

        $response = $this->reply($verb, $template, $record->id, 'Never leaves the building.');

        $response->assertStatus(502);
        $response->assertJsonPath('data.admin_reply', 'Never leaves the building.');
        $this->assertNotNull($response->json('data.delivery_failed_at'));
        $response->assertJsonPath('data.replied_at', null);
    }

    #[DataProvider('replyFlows')]
    public function test_both_flows_answer_with_the_same_three_key_envelope(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        // apiCall() reads data.data on success and data.message / data.errors on
        // failure. A fourth key or a missing one breaks the panel with no PHP error.
        Mail::fake();
        $this->actAsAdmin();

        $record = $this->record($table);

        $response = $this->reply($verb, $template, $record->id, 'Envelope check.');

        $this->assertSame(['data', 'message', 'errors'], array_keys($response->json()));
    }

    #[DataProvider('replyFlows')]
    public function test_the_reply_reaches_the_person_who_wrote_in_and_nobody_else(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        // A reply carries the admin's words to one client. Copying the admin in —
        // or worse, sending to the notification address — would leak one client's
        // conversation into the inbox that receives everybody's.
        Mail::fake();
        $this->actAsAdmin();

        $record = $this->record($table);

        $this->reply($verb, $template, $record->id, 'For your eyes only.')->assertOk();

        Mail::assertSent($mailable, fn ($mail) => $mail->hasTo($recipient));
        Mail::assertSentCount(1);
    }

    #[DataProvider('replyFlows')]
    public function test_replying_leaves_every_other_record_untouched(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        Mail::fake();
        $this->actAsAdmin();

        $record = $this->record($table);
        $bystander = $this->bystander($table);

        $this->reply($verb, $template, $record->id, 'Only this one.')->assertOk();

        $bystander->refresh();

        $this->assertNull($bystander->admin_reply);
        $this->assertNull($bystander->replied_at);
        $this->assertNull($bystander->delivery_failed_at);
    }

    #[DataProvider('replyFlows')]
    public function test_the_reply_text_is_persisted_even_when_delivery_fails(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        // The admin typed it; losing it to a transport error would be the worst
        // outcome of the three. This is asserted against the database, not just
        // the response body.
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('transport refused'));
        $this->actAsAdmin();

        $record = $this->record($table);

        $this->reply($verb, $template, $record->id, 'Words worth keeping.')->assertStatus(502);

        $this->assertDatabaseHas($table, [
            'id' => $record->id,
            'admin_reply' => 'Words worth keeping.',
        ]);
    }

    #[DataProvider('replyFlows')]
    public function test_a_reply_is_sent_synchronously_rather_than_queued(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        // Sends are synchronous by deliberate design: QUEUE_CONNECTION is
        // `database` but no worker is expected to be running. A Mailable that
        // later implements ShouldQueue would silently stop delivering in
        // production, and this is the assertion that would notice.
        Mail::fake();
        Queue::fake();
        $this->actAsAdmin();

        $record = $this->record($table);

        $this->reply($verb, $template, $record->id, 'Straight out the door.')->assertOk();

        Mail::assertSent($mailable);
        Mail::assertNotQueued($mailable);
        Queue::assertNothingPushed();
    }

    #[DataProvider('replyFlows')]
    public function test_an_unauthenticated_reply_is_refused_before_anything_happens(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        Mail::fake();

        $record = $this->record($table);

        $this->reply($verb, $template, $record->id, 'Should never land.')->assertStatus(401);

        Mail::assertNothingSent();
        $this->assertDatabaseHas($table, ['id' => $record->id, 'admin_reply' => null]);
    }

    #[DataProvider('replyFlows')]
    public function test_a_blank_reply_is_refused_before_anything_happens(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        Mail::fake();
        $this->actAsAdmin();

        $record = $this->record($table);

        $this->reply($verb, $template, $record->id, '   ')->assertStatus(422);

        Mail::assertNothingSent();
        $this->assertDatabaseHas($table, ['id' => $record->id, 'admin_reply' => null]);
    }

    #[DataProvider('replyFlows')]
    public function test_replying_to_a_missing_record_answers_404(
        string $verb,
        string $template,
        string $mailable,
        string $table,
        string $recipient,
    ): void {
        Mail::fake();
        $this->actAsAdmin();

        $this->reply($verb, $template, 999_999, 'Nobody home.')->assertStatus(404);

        Mail::assertNothingSent();
    }

    // =====================================================================
    // The one difference that is deliberate, pinned so it stays deliberate
    // =====================================================================

    public function test_only_the_meeting_flow_carries_a_status_column(): void
    {
        // contact_messages has no `status`: replied_at non-null already answers
        // "has this been replied to", and a second field could disagree with it.
        // meeting_requests inherited its enum from before the reply feature
        // existed. Both are recorded decisions, so the asymmetry is asserted
        // rather than left to look like an oversight.
        Mail::fake();
        $this->actAsAdmin();

        $meeting = $this->record('meeting_requests');
        $this->reply('PUT', '/api/admin/meeting-requests/%d/reply', $meeting->id, 'Yes.')->assertOk();
        $this->assertSame(MeetingRequest::STATUS_REPLIED, $meeting->refresh()->status);

        $contact = $this->record('contact_messages');
        $response = $this->reply('POST', '/api/admin/contact-messages/%d/reply', $contact->id, 'Yes.');
        $response->assertOk();
        $this->assertArrayNotHasKey('status', $response->json('data'));
    }

    public function test_a_refused_meeting_reply_leaves_the_status_alone(): void
    {
        // Not forced back to pending either: a request replied to successfully
        // earlier and then failing a retry is still replied, and only the new
        // failure is news.
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('transport refused'));
        $this->actAsAdmin();

        $meeting = $this->record('meeting_requests');

        $this->reply('PUT', '/api/admin/meeting-requests/%d/reply', $meeting->id, 'Refused.')
            ->assertStatus(502);

        $this->assertSame(MeetingRequest::STATUS_PENDING, $meeting->refresh()->status);
    }

    public function test_an_internal_note_is_saved_without_sending_anything(): void
    {
        // Notes are meeting-only and must never reach a mailbox.
        Mail::fake();
        $this->actAsAdmin();

        $meeting = $this->record('meeting_requests');

        $this->putJson("/api/admin/meeting-requests/{$meeting->id}/note", [
            'admin_note' => 'Client seems price-sensitive.',
        ])->assertOk();

        Mail::assertNothingSent();
        $this->assertSame('Client seems price-sensitive.', $meeting->refresh()->admin_note);
    }

    public function test_a_reply_token_from_a_deleted_user_no_longer_works(): void
    {
        // Sanctum::actingAs bypasses the token guard, so the real bearer path is
        // exercised once here: an admin whose account is gone must not keep
        // replying to clients.
        Mail::fake();

        $user = User::factory()->create();
        $token = $user->createToken('admin-panel')->plainTextToken;
        $record = $this->record('contact_messages');

        $user->delete();
        PersonalAccessToken::query()->delete();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/admin/contact-messages/{$record->id}/reply", [
                'admin_reply' => 'Should never land.',
            ])->assertStatus(401);

        Mail::assertNothingSent();
    }
}
