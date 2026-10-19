<?php

namespace App\Services\Firebase;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Minimal Firestore REST client (no grpc extension needed).
 *
 * Paths are relative to the database root, e.g. "patients/abc/threads/clinic".
 * Documents come back as plain arrays: their fields (timestamps as
 * CarbonImmutable) plus "_id" and "_path".
 *
 * The service account bypasses Firestore security rules, so callers must do
 * their own permission checks (see API_CONTRACT.md in the app repo).
 *
 * Requests go through FirebaseTransport: reads are retried on transient
 * errors and run in parallel inside concurrently() tasks; writes are sent
 * exactly once.
 */
class FirestoreClient
{
    /** Firestore's limit for documents in one batchGet. */
    private const BATCH_GET_LIMIT = 100;

    public function __construct(private FirebaseTransport $transport)
    {
    }

    /**
     * Run independent tasks in parallel; see FirebaseTransport::concurrently().
     *
     * @param  array<array-key, callable(): mixed>  $tasks
     */
    public function concurrently(array $tasks): array
    {
        return $this->transport->concurrently($tasks);
    }

    public function projectId(): string
    {
        return config('services.firebase.project_id');
    }

    /** Full resource name of the database root ("projects/.../documents"). */
    public function root(): string
    {
        return "projects/{$this->projectId()}/databases/(default)/documents";
    }

    public function name(string $path): string
    {
        return $this->root().'/'.trim($path, '/');
    }

    /** One document, or null if it doesn't exist. */
    public function get(string $path): ?array
    {
        $response = $this->send(FirebaseRequest::GET, $this->url($path), null, 'firestore.get', $path, idempotent: true);

        if ($response->status() === 404) {
            return null;
        }

        return $this->decodeDocument($this->ok($response, 'read')->json());
    }

    /**
     * Fetch several documents at once, keyed by path. Missing documents are null.
     *
     * @param  string[]  $paths
     * @return array<string, array|null>
     */
    public function getMany(array $paths): array
    {
        $result = array_fill_keys($paths, null);
        if ($paths === []) {
            return $result;
        }

        // Chunks are independent, so large lookups fetch them in parallel.
        $chunks = $this->concurrently(array_map(
            fn (array $chunk) => fn () => $this->ok($this->send(
                FirebaseRequest::POST,
                $this->url('').':batchGet',
                ['documents' => array_map(fn ($p) => $this->name($p), $chunk)],
                'firestore.batchGet',
                count($chunk).' docs, first '.$chunk[0],
                idempotent: true,
            ), 'read')->json(),
            array_chunk($paths, self::BATCH_GET_LIMIT),
        ));

        foreach ($chunks as $entries) {
            foreach ($entries as $entry) {
                if (isset($entry['found'])) {
                    $doc = $this->decodeDocument($entry['found']);
                    $result[$doc['_path']] = $doc;
                }
            }
        }

        return $result;
    }

    /**
     * Run a structured query against $collection under $parentPath
     * ("" for a top-level collection).
     *
     * @param  array  $where  [[field, op, value], ...] with op one of Firestore's
     *                        FieldFilter operators (EQUAL, GREATER_THAN, ...)
     * @param  array|null  $orderBy  [field, 'ASCENDING'|'DESCENDING']
     * @param  bool  $allDescendants  collection-group query: every $collection
     *                                below $parentPath (e.g. all patients' sessions).
     *                                Filters/ordering need a collection-group index,
     *                                so callers filter these in PHP instead.
     */
    public function query(string $parentPath, string $collection, array $where = [], ?array $orderBy = null, ?int $limit = null, bool $allDescendants = false): array
    {
        $query = ['from' => [array_filter(['collectionId' => $collection, 'allDescendants' => $allDescendants])]];

        $filters = array_map(fn ($w) => ['fieldFilter' => [
            'field' => ['fieldPath' => $w[0]],
            'op' => $w[1],
            'value' => $this->encode($w[2]),
        ]], $where);

        if (count($filters) === 1) {
            $query['where'] = $filters[0];
        } elseif ($filters) {
            $query['where'] = ['compositeFilter' => ['op' => 'AND', 'filters' => $filters]];
        }
        if ($orderBy) {
            $query['orderBy'] = [['field' => ['fieldPath' => $orderBy[0]], 'direction' => $orderBy[1]]];
        }
        if ($limit) {
            $query['limit'] = $limit;
        }

        $target = trim($parentPath.'/'.$collection, '/').($allDescendants ? ' (collection group)' : '');
        $response = $this->ok($this->send(
            FirebaseRequest::POST,
            $this->url($parentPath).':runQuery',
            ['structuredQuery' => $query],
            'firestore.runQuery',
            $target,
            idempotent: true,
        ), 'query');

        return collect($response->json())
            ->filter(fn ($row) => isset($row['document']))
            ->map(fn ($row) => $this->decodeDocument($row['document']))
            ->values()
            ->all();
    }

    /**
     * Apply writes atomically. Build them with the write helpers below.
     * Never retried: increments and create-preconditions are not safe to repeat.
     */
    public function commit(array $writes): void
    {
        if ($writes === []) {
            return;
        }

        $first = Str::after($writes[0]['update']['name'] ?? $writes[0]['delete'] ?? '', $this->root().'/');
        $target = count($writes) === 1 ? $first : count($writes)." writes, first {$first}";

        $this->ok($this->send(FirebaseRequest::POST, $this->url('').':commit', ['writes' => $writes], 'firestore.commit', $target, idempotent: false), 'save');
    }

    /** Delete a document (deleting a missing document is not an error). */
    public function delete(string $path): void
    {
        $this->ok($this->send(FirebaseRequest::DELETE, $this->url($path), null, 'firestore.delete', $path, idempotent: true), 'delete');
    }

    /** A new random document id, same format as the Firebase SDKs. */
    public static function newId(): string
    {
        return Str::random(20);
    }

    /**
     * Whether $id is safe to put in a document path (Firebase uids and
     * generated ids are alphanumeric). Check every id that comes from a URL.
     */
    public static function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id);
    }

    // ---- write builders ---------------------------------------------------

    /** Create a document; fails the whole commit if it already exists. */
    public function createWrite(string $path, array $fields, array $serverTimestamps = []): array
    {
        return array_filter([
            'update' => ['name' => $this->name($path), 'fields' => $this->encodeFields($fields)],
            'currentDocument' => ['exists' => false],
            'updateTransforms' => $this->timestampTransforms($serverTimestamps),
        ]);
    }

    /**
     * Set only the given fields (merge), creating the document if needed.
     *
     * @param  array<string, int>  $increments  field => amount
     */
    public function mergeWrite(string $path, array $fields, array $increments = [], array $serverTimestamps = []): array
    {
        $transforms = $this->timestampTransforms($serverTimestamps);
        foreach ($increments as $field => $by) {
            $transforms[] = ['fieldPath' => $field, 'increment' => $this->encode($by)];
        }

        return array_filter([
            'update' => ['name' => $this->name($path), 'fields' => $this->encodeFields($fields)],
            'updateMask' => ['fieldPaths' => array_keys($fields)],
            'updateTransforms' => $transforms,
        ]);
    }

    // ---- value encoding ---------------------------------------------------

    public function encodeFields(array $fields): array
    {
        return array_map(fn ($v) => $this->encode($v), $fields);
    }

    public function encode(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            is_string($value) => ['stringValue' => $value],
            $value instanceof DateTimeInterface => [
                'timestampValue' => CarbonImmutable::instance($value)->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ],
            is_array($value) && array_is_list($value) => [
                'arrayValue' => ['values' => array_map(fn ($v) => $this->encode($v), $value)],
            ],
            is_array($value) => ['mapValue' => ['fields' => $this->encodeFields($value)]],
            default => throw new FirebaseException('Unsupported Firestore value type: '.get_debug_type($value)),
        };
    }

    public function decode(array $value): mixed
    {
        $type = array_key_first($value);
        $v = $value[$type];

        return match ($type) {
            'nullValue' => null,
            'booleanValue' => (bool) $v,
            'integerValue' => (int) $v,
            'doubleValue' => (float) $v,
            'stringValue', 'referenceValue', 'bytesValue' => $v,
            'timestampValue' => CarbonImmutable::parse($v),
            'arrayValue' => array_map(fn ($x) => $this->decode($x), $v['values'] ?? []),
            'mapValue' => array_map(fn ($x) => $this->decode($x), $v['fields'] ?? []),
            default => $v,
        };
    }

    // ---- internals --------------------------------------------------------

    private function decodeDocument(array $doc): array
    {
        $path = Str::after($doc['name'], $this->root().'/');

        return array_map(fn ($v) => $this->decode($v), $doc['fields'] ?? []) + [
            '_id' => Str::afterLast($path, '/'),
            '_path' => $path,
        ];
    }

    private function timestampTransforms(array $fields): array
    {
        return array_map(fn ($f) => ['fieldPath' => $f, 'setToServerValue' => 'REQUEST_TIME'], $fields);
    }

    private function url(string $path): string
    {
        return 'https://firestore.googleapis.com/v1/'.rtrim($this->name($path), '/');
    }

    private function send(string $method, string $url, ?array $body, string $operation, string $target, bool $idempotent): Response
    {
        return $this->transport->send(new FirebaseRequest($method, $url, $body, $operation, $target, $idempotent));
    }

    /**
     * Throw a staff-safe FirebaseException for an error response. Firebase's
     * full error is already in the log line FirebaseTransport wrote for the call.
     */
    private function ok(Response $response, string $action): Response
    {
        if ($response->failed()) {
            $status = $response->json('error.status') ?? $response->json('0.error.status');

            throw new FirebaseException(match ($status) {
                'ALREADY_EXISTS' => 'That record already exists.',
                'FAILED_PRECONDITION' => "Could not {$action} the data: Firestore needs an index or a precondition failed.",
                'PERMISSION_DENIED' => "The server's Firebase account is not allowed to {$action} this data.",
                'RESOURCE_EXHAUSTED' => 'Firebase is rate-limiting the clinic right now. Try again in a minute.',
                default => "Could not {$action} the data in Firebase (error {$response->status()}). Try again in a moment.",
            });
        }

        return $response;
    }
}
