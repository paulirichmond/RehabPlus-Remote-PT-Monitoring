<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Receives Content-Security-Policy violation reports from browsers and logs
 * them as "csp.violation". Anyone can POST here, so the body is size-capped,
 * only known fields are kept, query strings are stripped (they can hold
 * search terms) and the route is rate-limited.
 */
class CspReportController extends Controller
{
    private const MAX_BODY_BYTES = 16384;

    public function __invoke(Request $request): Response
    {
        $raw = (string) $request->getContent();
        if ($raw === '' || strlen($raw) > self::MAX_BODY_BYTES) {
            return response()->noContent();
        }

        $data = json_decode($raw, true);
        if (! is_array($data)) {
            return response()->noContent();
        }

        // report-uri sends {"csp-report": {...}}; the Reporting API sends [{"body": {...}}].
        $reports = isset($data['csp-report']) ? [$data['csp-report']] : array_column(array_filter($data, 'is_array'), 'body');

        foreach (array_slice($reports, 0, 10) as $report) {
            if (is_array($report)) {
                Log::warning('csp.violation', $this->summarise($report));
            }
        }

        return response()->noContent();
    }

    private function summarise(array $report): array
    {
        $line = $this->first($report, 'line-number', 'lineNumber');

        return [
            'directive' => $this->clean($this->first($report, 'effective-directive', 'effectiveDirective', 'violated-directive')),
            'blocked' => $this->clean($this->first($report, 'blocked-uri', 'blockedURL')),
            'page' => $this->clean($this->first($report, 'document-uri', 'documentURL')),
            'source' => $this->clean($this->first($report, 'source-file', 'sourceFile')),
            'line' => is_numeric($line) ? (int) $line : null,
            'sample' => $this->clean($this->first($report, 'script-sample', 'sample')),
            'disposition' => $this->clean($this->first($report, 'disposition')),
        ];
    }

    /** The first of these fields that holds a plain value (browsers name them differently). */
    private function first(array $report, string ...$names): mixed
    {
        foreach ($names as $name) {
            if (isset($report[$name]) && is_scalar($report[$name])) {
                return $report[$name];
            }
        }

        return null;
    }

    /** Drop query strings and fragments, cap the length. */
    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return mb_substr(preg_replace('/[?#].*$/s', '', (string) $value), 0, 200);
    }
}
