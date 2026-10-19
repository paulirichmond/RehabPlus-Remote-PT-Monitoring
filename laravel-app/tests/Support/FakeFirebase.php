<?php

namespace Tests\Support;

use App\Auth\StaffUser;
use App\Services\Firebase\FirestoreClient;
use App\Services\Firebase\GoogleAccessToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * In-memory Firestore + Firebase Auth + FCM behind Http::fake, so feature tests
 * exercise the real REST calls without touching a Firebase project.
 * Laravel calls setUpFakeFirebase() itself (setUp<TraitName>), so tests don't.
 *
 * - $docs: path => fields (PHP values; timestamps as CarbonImmutable). Commits
 *   are applied to it (create/merge, increments, server timestamps) and also
 *   recorded raw in $commits so tests can assert exact write payloads.
 * - $authUsers: email => [uid, password, disabled, displayName]
 */
trait FakeFirebase
{
    protected const ROOT = 'projects/rehabplus-80675/databases/(default)/documents';

    /** @var array<string, array> */
    protected array $docs = [];

    protected array $authUsers = [];

    protected array $commits = [];

    protected array $deleted = [];

    protected array $pushes = [];

    protected array $authCalls = [];

    protected bool $failCommits = false;

    /**
     * Failures to return before answering normally, per URL fragment, e.g.
     * [':runQuery' => [503, 503]] or ['/users/a' => ['connection']]. Each
     * matching request takes the next entry.
     *
     * @var array<string, array<int, int|string>>
     */
    protected array $injectedFailures = [];

    protected function setUpFakeFirebase(): void
    {
        config([
            'services.firebase.credentials' => __FILE__,
            'services.firebase.project_id' => 'rehabplus-80675',
            'services.firebase.api_key' => 'test-api-key',
            'services.firebase.retry_backoff_ms' => 0, // retries without sleeping
        ]);
        $this->instance(GoogleAccessToken::class, new class extends GoogleAccessToken
        {
            public function get(): string
            {
                return 'test-token';
            }
        });

        Http::fake(fn (Request $r) => $this->fakeGoogle($r));
    }

    /** A staff member: users/{uid} doc + matching login, returned ready for actingAs(). */
    protected function staff(string $role, ?string $uid = null, ?string $name = null, ?string $email = null): StaffUser
    {
        $uid ??= $role.'Uid';
        $name ??= ucfirst($role).' User';
        $email ??= "{$uid}@rehabplus.com";
        $this->docs["users/{$uid}"] = ['role' => $role, 'name' => $name, 'email' => $email, 'active' => true];
        $this->authUsers[$email] = ['uid' => $uid, 'password' => 'secret-pass', 'disabled' => false, 'displayName' => $name];

        return new StaffUser($uid, $name, $email, $role);
    }

    protected function patientDoc(string $uid, array $fields = []): void
    {
        $this->docs["patients/{$uid}"] = $fields + [
            'name' => ucfirst($uid), 'email' => "{$uid}@mail.com", 'condition' => 'Knee rehab',
            'therapistId' => null, 'therapistName' => '', 'archived' => false,
            'createdAt' => CarbonImmutable::now()->subDays(10),
        ];
        $this->docs["users/{$uid}"] = ['role' => 'patient', 'name' => $this->docs["patients/{$uid}"]['name'], 'email' => "{$uid}@mail.com"];
        $this->authUsers["{$uid}@mail.com"] = ['uid' => $uid, 'password' => 'secret-pass', 'disabled' => false, 'displayName' => $this->docs["patients/{$uid}"]['name']];
    }

    /** Collection-group queries sent so far (collection ids), e.g. ['sessions']. */
    protected function collectionGroupReads(): array
    {
        return Http::recorded()
            ->map(fn ($pair) => $pair[0]->data()['structuredQuery']['from'][0] ?? null)
            ->filter(fn ($from) => ! empty($from['allDescendants']))
            ->map(fn ($from) => $from['collectionId'])
            ->values()
            ->all();
    }

    /** Docs whose path matches a pattern like "patients/x/prescriptions/*". */
    protected function docsLike(string $pattern): array
    {
        return array_filter($this->docs, fn ($path) => Str::is($pattern, $path), ARRAY_FILTER_USE_KEY);
    }

    protected function fakeGoogle(Request $r)
    {
        $url = urldecode($r->url());
        $body = $r->data();

        foreach ($this->injectedFailures as $fragment => $failures) {
            if ($failures !== [] && str_contains($url, $fragment)) {
                $failure = array_shift($this->injectedFailures[$fragment]);

                return $failure === 'connection'
                    ? Http::failedConnection()
                    : Http::response(['error' => ['code' => $failure, 'status' => 'INJECTED', 'message' => 'injected failure']], $failure);
            }
        }

        if (str_contains($url, 'identitytoolkit')) {
            return $this->fakeAuth($url, $body);
        }

        if (str_contains($url, 'fcm.googleapis.com')) {
            $this->pushes[] = $body['message'];

            return $body['message']['token'] === 'deadToken'
                ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                : Http::response(['name' => 'projects/x/messages/1']);
        }

        $path = trim(Str::after($url, self::ROOT), '/');

        if (str_ends_with($path, ':commit')) {
            return $this->applyCommit($body['writes']);
        }

        if (str_ends_with($path, ':batchGet')) {
            return Http::response(array_map(function ($name) {
                $p = Str::after($name, self::ROOT.'/');

                return isset($this->docs[$p]) ? ['found' => $this->restDoc($p)] : ['missing' => $name];
            }, $body['documents']));
        }

        if (str_ends_with($path, ':runQuery')) {
            return Http::response($this->runQuery(Str::before($path, ':runQuery'), $body['structuredQuery']));
        }

        if ($r->method() === 'DELETE') {
            $this->deleted[] = $path;
            unset($this->docs[$path]);

            return Http::response([]);
        }

        return isset($this->docs[$path]) ? Http::response($this->restDoc($path)) : Http::response(['error' => ['code' => 404]], 404);
    }

    private function fakeAuth(string $url, array $body)
    {
        $this->authCalls[] = ['url' => Str::after($url, 'identitytoolkit.googleapis.com/v1/'), 'body' => $body];
        $error = fn (string $code) => Http::response(['error' => ['message' => $code]], 400);

        if (str_contains($url, 'accounts:signInWithPassword')) {
            $user = $this->authUsers[$body['email']] ?? null;
            if (! $user || $user['password'] !== $body['password']) {
                return $error('INVALID_LOGIN_CREDENTIALS');
            }

            return $user['disabled'] ? $error('USER_DISABLED') : Http::response(['localId' => $user['uid']]);
        }

        if (str_ends_with($url, 'accounts:lookup') && isset($body['localId'])) {
            $users = array_values(array_filter($this->authUsers, fn ($u) => in_array($u['uid'], $body['localId'], true)));

            return Http::response($users ? ['users' => array_map(fn ($u) => ['localId' => $u['uid']], $users)] : []);
        }

        if (str_ends_with($url, 'accounts:lookup')) {
            $uid = $this->authUsers[$body['email'][0]]['uid'] ?? null;

            return Http::response($uid ? ['users' => [['localId' => $uid]]] : []);
        }

        $email = collect($this->authUsers)->search(fn ($u) => $u['uid'] === ($body['localId'] ?? null));

        if (str_ends_with($url, 'accounts:update')) {
            if ($email === false) {
                return $error('USER_NOT_FOUND');
            }
            foreach (['password' => 'password', 'displayName' => 'displayName', 'disableUser' => 'disabled'] as $in => $field) {
                if (array_key_exists($in, $body)) {
                    $this->authUsers[$email][$field] = $body[$in];
                }
            }

            return Http::response(['localId' => $body['localId']]);
        }

        if (str_ends_with($url, 'accounts:delete')) {
            if ($email === false) {
                return $error('USER_NOT_FOUND');
            }
            unset($this->authUsers[$email]);

            return Http::response([]);
        }

        if (str_ends_with($url, '/accounts')) { // create
            if (isset($this->authUsers[$body['email']])) {
                return $error('EMAIL_EXISTS');
            }
            $uid = 'newUid'.(count($this->authUsers) + 1);
            $this->authUsers[$body['email']] = ['uid' => $uid, 'password' => $body['password'], 'disabled' => false, 'displayName' => $body['displayName']];

            return Http::response(['localId' => $uid]);
        }

        return Http::response(['error' => ['message' => 'UNEXPECTED '.$url]], 500);
    }

    private function applyCommit(array $writes)
    {
        if ($this->failCommits) {
            return Http::response(['error' => ['message' => 'backend unavailable']], 503);
        }

        $db = app(FirestoreClient::class);
        foreach ($writes as $w) {
            $path = Str::after($w['update']['name'], self::ROOT.'/');
            if (($w['currentDocument']['exists'] ?? null) === false && isset($this->docs[$path])) {
                return Http::response(['error' => ['message' => 'ALREADY_EXISTS: '.$path]], 409);
            }
        }

        $this->commits[] = $writes;

        foreach ($writes as $w) {
            $path = Str::after($w['update']['name'], self::ROOT.'/');
            $fields = array_map(fn ($v) => $db->decode($v), $w['update']['fields'] ?? []);
            $doc = isset($w['updateMask']) ? array_merge($this->docs[$path] ?? [], $fields) : $fields;

            foreach ($w['updateTransforms'] ?? [] as $t) {
                $doc[$t['fieldPath']] = isset($t['increment'])
                    ? ($doc[$t['fieldPath']] ?? 0) + (int) $t['increment']['integerValue']
                    : CarbonImmutable::now();
            }
            $this->docs[$path] = $doc;
        }

        return Http::response(['writeResults' => []]);
    }

    private function runQuery(string $parent, array $q): array
    {
        $from = $q['from'][0];
        $parent = trim($parent, '/');
        $prefix = $parent === '' ? '' : $parent.'/';

        $rows = [];
        foreach ($this->docs as $path => $fields) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }
            $segments = explode('/', substr($path, strlen($prefix)));
            $match = ! empty($from['allDescendants'])
                ? count($segments) >= 2 && $segments[count($segments) - 2] === $from['collectionId']
                : count($segments) === 2 && $segments[0] === $from['collectionId'];
            if ($match) {
                $rows[$path] = $fields;
            }
        }

        $filters = isset($q['where']['compositeFilter']) ? $q['where']['compositeFilter']['filters'] : (isset($q['where']) ? [$q['where']] : []);
        foreach ($filters as $f) {
            $field = $f['fieldFilter']['field']['fieldPath'];
            $value = app(FirestoreClient::class)->decode($f['fieldFilter']['value']);
            $rows = array_filter($rows, fn ($doc) => ($doc[$field] ?? null) === $value);
        }

        if (isset($q['orderBy'])) {
            $field = $q['orderBy'][0]['field']['fieldPath'];
            uasort($rows, fn ($a, $b) => $a[$field] <=> $b[$field]);
            if ($q['orderBy'][0]['direction'] === 'DESCENDING') {
                $rows = array_reverse($rows, true);
            }
        }
        if (isset($q['limit'])) {
            $rows = array_slice($rows, 0, $q['limit'], true);
        }

        return $rows
            ? array_map(fn ($p) => ['document' => $this->restDoc($p)], array_keys($rows))
            : [['readTime' => '2026-10-18T00:00:00Z']];
    }

    private function restDoc(string $path): array
    {
        return [
            'name' => self::ROOT.'/'.$path,
            'fields' => app(FirestoreClient::class)->encodeFields($this->docs[$path]),
        ];
    }
}
