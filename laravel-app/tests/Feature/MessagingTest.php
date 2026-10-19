<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/**
 * Staff messaging against a fake Firestore / Firebase Auth / FCM (Http::fake),
 * so no network or real Firebase project is touched.
 */
class MessagingTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $t = CarbonImmutable::parse('2026-10-18 09:00:00');
        $this->docs += [
            'patients/pAnna' => ['name' => 'Anna Cruz', 'condition' => 'ACL rehab', 'therapistId' => 'therapistUid1', 'therapistName' => 'Dr. Reyes'],
            'patients/pBen' => ['name' => 'Ben Lim', 'condition' => 'Shoulder', 'therapistId' => 'otherTherapist'],
            'patients/pOld' => ['name' => 'Olga Archived', 'condition' => 'Hip', 'therapistId' => 'therapistUid1', 'archived' => true],
            'patients/pAnna/threads/therapist' => ['lastMessage' => 'My knee hurts', 'lastMessageAt' => $t, 'lastSenderRole' => 'patient', 'unreadForStaff' => 2],
            'patients/pAnna/threads/therapist/messages/m1' => ['text' => 'Hi doc', 'senderRole' => 'patient', 'sentAt' => $t->subMinute()],
            'patients/pAnna/threads/therapist/messages/m2' => ['text' => 'My knee hurts', 'senderRole' => 'patient', 'sentAt' => $t],
            'patients/pBen/threads/clinic' => ['lastMessage' => 'Can I book Tuesday?', 'lastMessageAt' => $t, 'lastSenderRole' => 'patient', 'unreadForStaff' => 1],
            'patients/pAnna/devices/goodToken' => ['token' => 'goodToken'],
            'patients/pAnna/devices/deadToken' => ['token' => 'deadToken'],
        ];
    }

    private function therapist()
    {
        return $this->staff('therapist', 'therapistUid1', 'Dr. Reyes');
    }

    public function test_therapist_inbox_lists_only_their_patients(): void
    {
        $this->actingAs($this->therapist())
            ->get('/messages')
            ->assertOk()
            ->assertSee('Anna Cruz')
            ->assertSee('My knee hurts')
            ->assertDontSee('Ben Lim')
            ->assertDontSee('Olga Archived');
    }

    public function test_clinic_staff_inbox_lists_clinic_threads(): void
    {
        $this->actingAs($this->staff('staff'))
            ->get('/messages')
            ->assertOk()
            ->assertSee('Ben Lim')
            ->assertSee('Can I book Tuesday?')
            ->assertSee('Anna Cruz') // no clinic messages yet, still listed
            ->assertDontSee('My knee hurts'); // therapist thread is private
    }

    public function test_opening_a_conversation_shows_messages_and_marks_it_read(): void
    {
        $this->actingAs($this->therapist())
            ->get('/messages/pAnna')
            ->assertOk()
            ->assertSeeInOrder(['Hi doc', 'My knee hurts']);

        $write = $this->commits[0][0];
        $this->assertSame(self::ROOT.'/patients/pAnna/threads/therapist', $write['update']['name']);
        $this->assertSame(['unreadForStaff'], $write['updateMask']['fieldPaths']);
        $this->assertSame('0', $write['update']['fields']['unreadForStaff']['integerValue']);
    }

    public function test_therapist_cannot_open_another_therapists_patient(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->get('/messages/pBen')->assertNotFound();
        $this->actingAs($user)->post('/messages/pBen', ['text' => 'hi'])->assertNotFound();
        $this->assertSame([], $this->commits);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/messages')->assertRedirect('/login');
    }

    public function test_bad_patient_id_is_rejected_before_reaching_firestore(): void
    {
        $this->actingAs($this->staff('staff'))->get('/messages/'.urlencode('pAnna/threads'))->assertNotFound();
    }

    public function test_reply_writes_message_thread_and_notification_then_pushes(): void
    {
        $this->actingAs($this->therapist())
            ->post('/messages/pAnna', ['text' => '  Ice it for 15 minutes.  '])
            ->assertRedirect('/messages/pAnna')
            ->assertSessionMissing('success') // no banner: the bubble's tick shows it was sent
            ->assertSessionMissing('error');

        [$message, $thread, $notification] = $this->commits[0];

        $this->assertStringStartsWith(self::ROOT.'/patients/pAnna/threads/therapist/messages/', $message['update']['name']);
        $this->assertSame(['exists' => false], $message['currentDocument']);
        $this->assertSame('Ice it for 15 minutes.', $message['update']['fields']['text']['stringValue']);
        $this->assertSame('staff', $message['update']['fields']['senderRole']['stringValue']);
        $this->assertSame('therapistUid1', $message['update']['fields']['senderId']['stringValue']);
        $this->assertSame('Dr. Reyes', $message['update']['fields']['senderName']['stringValue']);
        $this->assertArrayHasKey('timestampValue', $message['update']['fields']['sentAt']);
        $this->assertSame([['fieldPath' => 'createdAt', 'setToServerValue' => 'REQUEST_TIME']], $message['updateTransforms']);

        $this->assertSame(self::ROOT.'/patients/pAnna/threads/therapist', $thread['update']['name']);
        $this->assertSame(['lastMessage', 'lastMessageAt', 'lastSenderRole'], $thread['updateMask']['fieldPaths']);
        $this->assertSame([['fieldPath' => 'unreadForPatient', 'increment' => ['integerValue' => '1']]], $thread['updateTransforms']);

        $this->assertStringStartsWith(self::ROOT.'/patients/pAnna/notifications/', $notification['update']['name']);
        $this->assertSame('message', $notification['update']['fields']['type']['stringValue']);
        $this->assertSame('Message from Dr. Reyes', $notification['update']['fields']['title']['stringValue']);
        $this->assertSame('therapist', $notification['update']['fields']['data']['mapValue']['fields']['thread']['stringValue']);
        $this->assertFalse($notification['update']['fields']['read']['booleanValue']);

        // One push per device, with the app's chat channel; the dead token is removed.
        $this->assertCount(2, $this->pushes);
        $this->assertSame('chat_messages', $this->pushes[0]['android']['notification']['channel_id']);
        $this->assertSame(['type' => 'chat', 'thread' => 'therapist'], $this->pushes[0]['data']);
        $this->assertSame(['patients/pAnna/devices/deadToken'], $this->deleted);
    }

    public function test_no_push_when_the_patient_turned_message_notifications_off(): void
    {
        $this->docs['patients/pAnna/preferences/notifications'] = ['messages' => false, 'exercises' => true];

        $this->actingAs($this->therapist())
            ->post('/messages/pAnna', ['text' => 'See you Monday.'])
            ->assertRedirect('/messages/pAnna')
            ->assertSessionMissing('error');

        $this->assertCount(3, $this->commits[0], 'message, thread and Notifications-page entry still written');
        $this->assertCount(0, $this->pushes);
        $this->assertSame([], $this->deleted, 'devices are not touched');
    }

    public function test_clinic_reply_is_sent_as_the_clinic(): void
    {
        $this->actingAs($this->staff('admin'))->post('/messages/pBen', ['text' => 'Tuesday 10am works.'])
            ->assertRedirect('/messages/pBen')
            ->assertSessionMissing('error');

        [$message, , $notification] = $this->commits[0];
        $this->assertStringContainsString('/patients/pBen/threads/clinic/messages/', $message['update']['name']);
        $this->assertSame('clinic', $message['update']['fields']['senderId']['stringValue']);
        $this->assertSame('Message from RehabPlus Clinic', $notification['update']['fields']['title']['stringValue']);
    }

    public function test_patient_messages_are_left_and_staff_right_with_sender_names(): void
    {
        $t = CarbonImmutable::parse('2026-10-18 10:00:00');
        $this->docs += [
            'patients/pBen/threads/clinic/messages/c1' => ['text' => 'Can I book Tuesday?', 'senderRole' => 'patient', 'sentAt' => $t],
            'patients/pBen/threads/clinic/messages/c2' => ['text' => 'Tuesday works.', 'senderRole' => 'staff', 'senderName' => 'Maria Santos', 'sentAt' => $t->addMinute()],
            'patients/pBen/threads/clinic/messages/c3' => ['text' => 'Old reply.', 'senderRole' => 'staff', 'sentAt' => $t->addMinutes(2)],
        ];

        $html = $this->actingAs($this->staff('admin'))->get('/messages/pBen/feed')->assertOk()->getContent();

        $rows = preg_split('/(?=<div class="d-flex flex-column)/', $html);
        $row = fn ($text) => collect($rows)->first(fn ($r) => str_contains($r, $text));
        $this->assertStringContainsString('align-items-start', $row('Can I book Tuesday?'));
        $this->assertStringContainsString('Ben Lim', $row('Can I book Tuesday?'));
        $this->assertStringContainsString('align-items-end', $row('Tuesday works.'));
        $this->assertStringContainsString('Maria Santos', $row('Tuesday works.'));
        $this->assertStringContainsString('RehabPlus Clinic', $row('Old reply.'), 'older replies without a name');
    }

    public function test_staff_replies_show_one_tick_until_the_patient_has_seen_them(): void
    {
        $t = CarbonImmutable::parse('2026-10-18 10:00:00');
        $this->docs += [
            'patients/pBen/threads/clinic/messages/c1' => ['text' => 'Can I book Tuesday?', 'senderRole' => 'patient', 'sentAt' => $t],
            'patients/pBen/threads/clinic/messages/c2' => ['text' => 'Tuesday works.', 'senderRole' => 'staff', 'sentAt' => $t->addMinute()],
            'patients/pBen/threads/clinic/messages/c3' => ['text' => 'See you at 10.', 'senderRole' => 'staff', 'sentAt' => $t->addMinutes(2)],
        ];
        // The patient opened the chat after c2 but hasn't seen c3 yet.
        $this->docs['patients/pBen/threads/clinic']['unreadForPatient'] = 1;

        $html = $this->actingAs($this->staff('admin'))->get('/messages/pBen/feed')->assertOk()->getContent();

        $rows = preg_split('/(?=<div class="d-flex flex-column)/', $html);
        $row = fn ($text) => collect($rows)->first(fn ($r) => str_contains($r, $text));
        $this->assertStringContainsString('bi-check2-all', $row('Tuesday works.'));
        $this->assertStringContainsString('bi-check2 chat-tick"', $row('See you at 10.'));
        $this->assertStringNotContainsString('bi-check2', $row('Can I book Tuesday?'), 'no ticks on patient messages');

        // Once the app resets the counter, both show as seen.
        $this->docs['patients/pBen/threads/clinic']['unreadForPatient'] = 0;
        $html = $this->actingAs($this->staff('admin'))->get('/messages/pBen/feed')->getContent();
        $this->assertSame(2, substr_count($html, 'bi-check2-all'));
    }

    public function test_status_version_changes_only_when_the_page_would(): void
    {
        $this->docs['patients/pBen/threads/clinic']['unreadForPatient'] = 1; // a reply not seen yet
        $user = $this->staff('admin');
        $version = fn () => $this->actingAs($user)->getJson('/messages/pBen/status')->assertOk()->json('version');

        $first = $version();
        $this->assertSame($first, $version(), 'stable while nothing changes');
        $this->assertSame([], $this->commits, 'polling the status writes nothing');

        $this->docs['patients/pBen/threads/clinic']['unreadForStaff'] = 0; // opening the page
        $this->assertSame($first, $version());

        $this->docs['patients/pBen/threads/clinic']['unreadForPatient'] = 0; // patient read it
        $seen = $version();
        $this->assertNotSame($first, $seen);

        $this->docs['patients/pBen/threads/clinic']['lastMessageAt'] = CarbonImmutable::parse('2026-10-18 11:00'); // new message
        $this->assertNotSame($seen, $version());

        // The feed hands out the same version, so the page doesn't reload twice.
        $this->actingAs($user)->get('/messages/pBen/feed')->assertHeader('X-Thread-Version', $version());
    }

    public function test_status_is_scoped_like_the_conversation(): void
    {
        $this->get('/messages/pBen/status')->assertRedirect('/login');
        $this->actingAs($this->therapist())->getJson('/messages/pBen/status')->assertNotFound();
    }

    public function test_read_receipts_without_a_summary_show_sent(): void
    {
        $messages = [
            ['fromPatient' => false], ['fromPatient' => true], ['fromPatient' => false],
        ];

        $this->assertSame(['sent', null, 'sent'], array_column(\App\Services\Messaging\MessagingService::withReadReceipts($messages, null), 'status'));
        $this->assertSame(['seen', null, 'sent'], array_column(\App\Services\Messaging\MessagingService::withReadReceipts($messages, ['unreadForPatient' => 1]), 'status'));
    }

    public function test_empty_and_too_long_replies_are_rejected(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->post('/messages/pAnna', ['text' => ''])->assertSessionHasErrors('text');
        $this->actingAs($user)->post('/messages/pAnna', ['text' => str_repeat('a', 1001)])->assertSessionHasErrors('text');
        $this->assertSame([], $this->commits);
    }

    public function test_page_explains_missing_firebase_config(): void
    {
        $user = $this->staff('staff');
        config(['services.firebase.credentials' => null]);

        $this->actingAs($user)->get('/messages')->assertOk()->assertSee('FIREBASE_CREDENTIALS is missing');
    }

    public function test_firebase_outage_shows_friendly_errors(): void
    {
        $user = $this->therapist();
        Http::swap(new Factory);
        Http::fake(fn () => Http::failedConnection());

        $this->actingAs($user)->get('/messages')->assertOk()->assertSee('Could not load conversations');
        $this->actingAs($user)->get('/messages/pAnna')->assertStatus(503);
    }

    public function test_reply_still_succeeds_when_push_network_fails(): void
    {
        Http::swap(new Factory);
        Http::fake(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com')
            ? Http::failedConnection()
            : $this->fakeGoogle($r));

        $this->actingAs($this->therapist())
            ->post('/messages/pAnna', ['text' => 'Hello'])
            ->assertRedirect('/messages/pAnna')
            ->assertSessionMissing('error');
        $this->assertCount(1, $this->commits);
    }
}
