<?php

namespace App\Services\Firebase;

use Illuminate\Support\Facades\Log;

/**
 * Tells a patient's phone about something: an entry on the app's
 * Notifications page (patients/{uid}/notifications) plus an FCM push to every
 * registered device (patients/{uid}/devices). See API_CONTRACT.md in the app repo.
 *
 * Patients can turn pushes off per kind in the app (Settings > Notifications,
 * stored at patients/{uid}/preferences/notifications). The Notifications-page
 * entry is always written; only the push is skipped.
 */
class PatientNotifier
{
    /** Push `data.type` => the preference field that controls it. */
    private const PREFERENCE_FOR_TYPE = [
        'chat' => 'messages',
        'exercise_assigned' => 'exercises',
    ];

    public function __construct(
        private FirestoreClient $db,
        private FirebaseAdmin $admin,
        private FirebaseTransport $transport,
    ) {
    }

    /**
     * The Notifications-page entry, as a write to include in the caller's commit
     * so it is saved together with whatever it announces.
     */
    public function notificationWrite(string $patientUid, string $type, string $title, string $body, array $data = []): array
    {
        return $this->db->createWrite("patients/{$patientUid}/notifications/".FirestoreClient::newId(), array_filter([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'read' => false,
        ], fn ($v) => $v !== null), serverTimestamps: ['createdAt']);
    }

    /**
     * Push to each of the patient's phones. Dead tokens are deleted; other
     * failures are logged and never thrown (the data is already saved).
     *
     * The preference and the device list are read together, then every phone
     * is pushed to in parallel.
     *
     * @return int|null number of phones reached, or null if the patient turned
     *                  this kind of notification off
     */
    public function push(string $patientUid, string $title, string $body, array $data): ?int
    {
        $type = $data['type'] ?? null;

        try {
            [$wanted, $devices] = $this->transport->concurrently([
                fn () => $this->wantsPush($patientUid, $type),
                fn () => $this->db->query("patients/{$patientUid}", 'devices'),
            ]);
        } catch (FirebaseException $e) {
            Log::warning('push.skipped devices unreadable', ['patient' => $patientUid, 'type' => $type, 'error' => $e->getMessage()]);

            return 0;
        }

        if (! $wanted) {
            Log::info('push.skipped patient opted out', ['patient' => $patientUid, 'type' => $type]);

            return null;
        }

        $sends = [];
        foreach ($devices as $device) {
            $sends[$device['_path']] = fn () => $this->admin->sendPush($device['_id'], $title, $body, $data);
        }
        $results = $this->transport->concurrently($sends);

        $dead = array_keys(array_filter($results, fn ($result) => $result === 'dead'));
        $this->deleteDeadDevices($patientUid, $dead);

        $counts = array_count_values($results) + ['sent' => 0, 'dead' => 0, 'failed' => 0];
        Log::info('push.sent', ['patient' => $patientUid, 'type' => $type, 'devices' => count($devices)] + $counts);

        return $counts['sent'];
    }

    /** Remove device tokens FCM says are gone (app uninstalled, token rotated). */
    private function deleteDeadDevices(string $patientUid, array $devicePaths): void
    {
        $deletes = [];
        foreach ($devicePaths as $path) {
            $deletes[] = function () use ($path, $patientUid) {
                try {
                    $this->db->delete($path);
                } catch (FirebaseException $e) {
                    Log::warning('push.dead_device not deleted', ['patient' => $patientUid, 'error' => $e->getMessage()]);
                }
            };
        }

        $this->transport->concurrently($deletes);
    }

    /**
     * False only when the patient turned this kind of push off. If the
     * preference can't be read, the push is sent (better than a missed message).
     */
    private function wantsPush(string $patientUid, ?string $type): bool
    {
        $field = self::PREFERENCE_FOR_TYPE[$type] ?? null;
        if ($field === null) {
            return true;
        }

        try {
            $prefs = $this->db->get("patients/{$patientUid}/preferences/notifications");
        } catch (FirebaseException $e) {
            Log::warning('push.preferences unreadable, sending anyway', ['patient' => $patientUid, 'error' => $e->getMessage()]);

            return true;
        }

        return ($prefs[$field] ?? true) !== false;
    }
}
