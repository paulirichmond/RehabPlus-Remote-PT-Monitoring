# Patient messaging

Staff answer patient messages from the RehabPlus app on the **Messages** page
(`/messages`). The data lives in Firestore (project `rehabplus-80675`), shared
with the Flutter app; the field layout is in `API_CONTRACT.md` on the
`camera/monitoring` branch.

## Who sees what

| Dashboard role | Answers | Sees |
| --- | --- | --- |
| `therapist` | the patient's `therapist` thread | only patients whose Firestore `therapistId` is the therapist's Firebase uid |
| `superadmin`, `manager`, `staff` | the patient's `clinic` thread (bookings, home visits, app problems) | every patient |
| `patient` | — | no access |

A therapist is matched to Firebase by **login email**: their dashboard email
must be the email of a Firebase Auth account, and patients must have that
account's uid as `therapistId`. If there's no match the page says so.

## Setup

1. Get the Firebase service-account JSON (Firebase console → Project settings
   → Service accounts → Generate new private key). Store it **outside** the
   repo; never commit it.
2. In `.env`:

   ```
   FIREBASE_CREDENTIALS=/absolute/path/to/service-account.json
   FIREBASE_PROJECT_ID=rehabplus-80675
   ```
3. `composer install` (adds `google/auth`; no `grpc` extension needed).

Without `FIREBASE_CREDENTIALS` the page shows a setup message instead of
failing.

## What a reply does

Matches `tool/seed/reply.mjs` in the app repo:

1. One atomic Firestore commit: the message (`senderRole: staff`), the thread
   summary (`lastMessage`, `lastMessageAt`, `lastSenderRole`,
   `unreadForPatient + 1`) and a `notifications` entry for the app.
2. An FCM push to each token in `patients/{uid}/devices` on the app's
   `chat_messages` channel. Dead tokens are deleted. Push failures are logged
   and don't fail the reply.

Opening a conversation resets `unreadForStaff` to 0. The open conversation
checks for new messages every 10 seconds.

## Code

- `app/Services/Firebase/` — REST clients: `FirestoreClient`, `FirebaseAdmin`
  (Auth lookup + FCM), `GoogleAccessToken` (cached OAuth token)
- `app/Services/Messaging/MessagingService.php` — inbox, permissions, reply
- `app/Http/Controllers/MessageController.php`, `resources/views/messages/`
- `tests/Feature/MessagingTest.php` — runs against a fake Firestore (`Http::fake`)
