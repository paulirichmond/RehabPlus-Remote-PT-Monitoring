<?php

namespace App\Http\Controllers;

use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports & Analytics: one page for every role (sections depend on the role,
 * see ReportService), plus admin-only CSV and PDF exports.
 */
class ReportController extends Controller
{
    /** Exports list at most this many patients (the PDF is a printable page). */
    private const PDF_PATIENT_LIMIT = 500;

    public function __construct(private ReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        return view('reports.index', $this->reports->build($request->user(), $request));
    }

    public function csv(Request $request): StreamedResponse
    {
        $rows = $this->reports->csvRows($this->reports->build($request->user(), $request));
        $filename = 'rehabplus-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, array_map(self::csvSafe(...), $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request): View
    {
        $report = $this->reports->build($request->user(), $request);

        return view('reports.pdf', $report + [
            'printPatients' => $report['patients'] ? $report['patients']['selected']->take(self::PDF_PATIENT_LIMIT) : collect(),
        ]);
    }

    /**
     * Stop spreadsheet formula injection: a cell starting with = + - @ (or a
     * tab/CR) would run as a formula in Excel, so it is prefixed with a quote.
     */
    private static function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
