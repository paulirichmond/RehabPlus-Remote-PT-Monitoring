<?php

namespace App\Services\Firebase;

/**
 * Running totals of Firebase traffic for the current request, written into
 * the one-line request summary by RequestDiagnostics. Registered as a scoped
 * singleton, so it starts at zero for every request.
 */
class FirebaseMetrics
{
    /** HTTP calls sent, including retries. */
    public int $calls = 0;

    /** Calls that were sent again after a transient failure. */
    public int $retries = 0;

    /** Calls that finally failed (network error or an error status other than 404). */
    public int $failures = 0;

    /** Parallel batches sent (a single call counts as a batch of one). */
    public int $batches = 0;

    /** Largest number of calls sent together in one batch. */
    public int $largestBatch = 0;

    /** Wall-clock time spent waiting for Firebase, in milliseconds. */
    public float $waitMs = 0.0;

    public function toArray(): array
    {
        return [
            'calls' => $this->calls,
            'batches' => $this->batches,
            'largest_batch' => $this->largestBatch,
            'retries' => $this->retries,
            'failures' => $this->failures,
            'wait_ms' => round($this->waitMs, 1),
        ];
    }
}
