<?php

namespace App\Http\Controllers;

use App\Models\ExerciseRecord;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return $this->buildDashboardView('dashboard.index');
    }

    public function analytics(): View
    {
        return $this->buildDashboardView('dashboard.analytics');
    }

    public function patientStatistics(Request $request): View
    {
        $filters = $this->resolveFilters($request);
        $range = $this->resolveDateRange($request); 

        $selectedQuery = $this->buildPatientQuery($filters, $range['start'], $range['end']);
        $selectedPatients = $selectedQuery->get();

        $summary = [
            'totalPatients' => $selectedPatients->count(),
            'activePatients' => $selectedPatients->where('patient_is_active', 1)->count(),
            'inactivePatients' => $selectedPatients->where('patient_is_active', 0)->count(),
        ];

        $monthlyStart = Carbon::now()->startOfMonth();
        $monthlyEnd = Carbon::now()->endOfMonth();
        $monthlyQuery = $this->buildPatientQuery($filters, $monthlyStart, $monthlyEnd);
        $summary['newPatientsThisMonth'] = $monthlyQuery->count();

        $trend = $this->buildRegistrationTrend($filters, $range['start'], $range['end']);
        $conditionChart = $this->buildConditionChart($selectedPatients);

        $comparison = $this->buildGrowthComparison($filters, $range);

        $recentPatients = $this->buildPatientQuery($filters, $range['start'], $range['end'])
            ->orderBy('patients.created_at', 'desc')
            ->paginate(10)
            ->appends($request->query());

        $conditionOptions = Patient::query()
            ->select('condition')
            ->whereNotNull('condition')
            ->where('condition', '!=', '')
            ->distinct()
            ->orderBy('condition')
            ->pluck('condition')
            ->toArray();

        $therapistOptions = Patient::query()
            ->select('assigned_to')
            ->whereNotNull('assigned_to')
            ->where('assigned_to', '!=', '')
            ->distinct()
            ->orderBy('assigned_to')
            ->pluck('assigned_to')
            ->toArray();

        return view('dashboard.patient_statistics', [
            'filters' => $filters,
            'rangeLabel' => $range['label'],
            'summary' => $summary,
            'registrationTrend' => $trend,
            'conditionChart' => $conditionChart,
            'growthSummary' => $comparison,
            'recentPatients' => $recentPatients,
            'conditionOptions' => $conditionOptions,
            'therapistOptions' => $therapistOptions,
        ]);
    }

    public function exportPatientStatisticsCsv(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $range = $this->resolveDateRange($request);
        $selectedPatients = $this->buildPatientQuery($filters, $range['start'], $range['end'])
            ->orderBy('patients.created_at', 'desc')
            ->get();

        $filename = 'patient-statistics-' . now()->format('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($selectedPatients, $range) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['RehabPlus', 'Patient Statistics']);
            fputcsv($handle, ['Selected range', $range['label']]);
            fputcsv($handle, ['Generated', now()->format('F j, Y H:i:s')]);
            fputcsv($handle, []);
            fputcsv($handle, ['Total Patients', $selectedPatients->count()]);
            fputcsv($handle, ['Active Patients', $selectedPatients->where('patient_is_active', 1)->count()]);
            fputcsv($handle, ['Inactive Patients', $selectedPatients->where('patient_is_active', 0)->count()]);
            fputcsv($handle, ['New Patients This Month', $this->buildPatientQuery($this->resolveFilters(request()), Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth())->count()]);
            fputcsv($handle, []);
            fputcsv($handle, ['Patient Name', 'Condition', 'Assigned Therapist', 'Registration Date', 'Status']);

            foreach ($selectedPatients as $patient) {
                fputcsv($handle, [
                    $patient->name,
                    $patient->condition ?: 'Unspecified',
                    $patient->assigned_to ?: 'Unassigned',
                    $patient->created_at ? $patient->created_at->format('Y-m-d H:i:s') : '',
                    $patient->patient_is_active ? 'Active' : 'Inactive',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPatientStatisticsPdf(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $range = $this->resolveDateRange($request);
        $selectedPatients = $this->buildPatientQuery($filters, $range['start'], $range['end'])
            ->orderBy('patients.created_at', 'desc')
            ->paginate(20);

        $summary = [
            'totalPatients' => $selectedPatients->total(),
            'activePatients' => $this->buildPatientQuery($filters, $range['start'], $range['end'])->where('users.is_active', true)->count(),
            'inactivePatients' => $this->buildPatientQuery($filters, $range['start'], $range['end'])->where('users.is_active', false)->count(),
            'newPatientsThisMonth' => $this->buildPatientQuery($filters, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth())->count(),
        ];

        return view('dashboard.patient_statistics_pdf', [
            'summary' => $summary,
            'rangeLabel' => $range['label'],
            'recentPatients' => $selectedPatients,
        ]);
    }

    protected function buildDashboardView(string $viewName): View
    {
        $patientStats = ExerciseRecord::patientStats();
        $recentRecords = ExerciseRecord::recentRecords(10);
        $totalPatients = Patient::count();

        $patientsWithRecords = array_values(array_filter($patientStats, fn (array $patient): bool => (int) $patient['total_sessions'] > 0));
        $recordedPatientCount = count($patientsWithRecords);
        $avgCompliance = $recordedPatientCount ? round(array_sum(array_column($patientsWithRecords, 'compliance_rate')) / $recordedPatientCount, 1) : 0;
        $avgPain = $recordedPatientCount ? round(array_sum(array_column($patientsWithRecords, 'avg_pain')) / $recordedPatientCount, 1) : 0;
        $hasRecoveryData = $recordedPatientCount > 0;

        $recoveryLabels = array_column($patientStats, 'name');
        $recoveryValues = array_map(fn (array $patient): float => (float) ($patient['recovery_score'] ?? 0), $patientStats);

        $conditionCounts = [];
        foreach ($patientStats as $patient) {
            $condition = $patient['condition'];
            $conditionCounts[$condition] = ($conditionCounts[$condition] ?? 0) + 1;
        }

        return view($viewName, compact(
            'patientStats',
            'recentRecords',
            'totalPatients',
            'avgCompliance',
            'avgPain',
            'hasRecoveryData',
            'recoveryLabels',
            'recoveryValues',
            'conditionCounts'
        ));
    }

    protected function resolveFilters(Request $request): array
    {
        return [
            'range' => $request->query('range', 'last_12_months'),
            'condition' => trim((string) $request->query('condition', '')),
            'therapist' => trim((string) $request->query('therapist', '')),
            'status' => trim((string) $request->query('status', '')),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ];
    }

    protected function resolveDateRange(Request $request): array
    {
        $preset = $request->query('range', 'last_12_months');
        $from = $request->query('from');
        $to = $request->query('to');

        if ($preset === 'custom' && $from && $to) {
            $start = Carbon::parse($from)->startOfDay();
            $end = Carbon::parse($to)->endOfDay();
            return ['start' => $start, 'end' => $end, 'label' => $start->format('M j, Y') . ' - ' . $end->format('M j, Y')];
        }

        switch ($preset) {
            case 'today':
                $start = Carbon::today();
                $end = Carbon::today();
                $label = 'Today';
                break;
            case 'week':
                $start = Carbon::now()->startOfWeek();
                $end = Carbon::now()->endOfWeek();
                $label = 'This Week';
                break;
            case 'month':
                $start = Carbon::now()->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                $label = 'This Month';
                break;
            case 'year':
                $start = Carbon::now()->startOfYear();
                $end = Carbon::now()->endOfYear();
                $label = 'This Year';
                break;
            case 'last_12_months':
            default:
                $start = Carbon::now()->subMonths(11)->startOfMonth();
                $end = Carbon::now()->endOfMonth();
                $label = 'Last 12 Months';
                break;
        }

        return ['start' => $start, 'end' => $end, 'label' => $label];
    }

    protected function buildPatientQuery(array $filters, ?Carbon $start = null, ?Carbon $end = null)
    {
        $query = Patient::query()
            ->leftJoin('users', 'users.id', '=', 'patients.user_id')
            ->select(['patients.*', 'users.is_active as patient_is_active']);

        if (! empty($filters['condition'])) {
            $query->where('patients.condition', $filters['condition']);
        }

        if (! empty($filters['therapist'])) {
            $query->where('patients.assigned_to', $filters['therapist']);
        }

        if (! empty($filters['status'])) {
            $query->where('users.is_active', $filters['status'] === 'active');
        }

        if ($start && $end) {
            $query->whereBetween('patients.created_at', [$start->startOfDay(), $end->endOfDay()]);
        }

        return $query;
    }

    protected function buildRegistrationTrend(array $filters, Carbon $start, Carbon $end): array
    {
        $labels = [];
        $values = [];

        $bucketType = $start->diffInDays($end) <= 31 ? 'day' : 'month';

        if ($bucketType === 'day') {
            $cursor = $start->copy()->startOfDay();
            while ($cursor->lte($end)) {
                $labels[] = $cursor->format('M d');
                $values[$cursor->format('Y-m-d')] = 0;
                $cursor->addDay();
            }

            $trendData = $this->buildPatientQuery($filters, $start, $end)
                ->selectRaw('DATE(patients.created_at) as bucket, COUNT(*) as total')
                ->groupBy('bucket')
                ->pluck('total', 'bucket')
                ->toArray();

            foreach ($values as $bucket => $count) {
                $values[$bucket] = (int) ($trendData[$bucket] ?? 0);
            }

            return [
                'labels' => array_values(array_map(fn ($label) => $label, $labels)),
                'values' => array_values(array_map(fn ($bucket) => $values[$bucket] ?? 0, array_keys($values))),
            ];
        }

        $cursor = $start->copy()->startOfMonth();
        while ($cursor->lte($end->copy()->endOfMonth())) {
            $labels[] = $cursor->format('M Y');
            $values[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        $trendData = $this->buildPatientQuery($filters, $start, $end)
            ->selectRaw('strftime("%Y-%m", patients.created_at) as bucket, COUNT(*) as total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->toArray();

        foreach ($values as $bucket => $count) {
            $values[$bucket] = (int) ($trendData[$bucket] ?? 0);
        }

        return [
            'labels' => array_values(array_map(fn ($label) => $label, $labels)),
            'values' => array_values(array_map(fn ($bucket) => $values[$bucket] ?? 0, array_keys($values))),
        ];
    }

    protected function buildConditionChart($patients): array
    {
        $conditionCounts = [];

        foreach ($patients as $patient) {
            $condition = trim((string) ($patient->condition ?? '')) ?: 'Unspecified';
            $conditionCounts[$condition] = ($conditionCounts[$condition] ?? 0) + 1;
        }

        ksort($conditionCounts);

        return [
            'labels' => array_keys($conditionCounts),
            'values' => array_values($conditionCounts),
        ];
    }

    protected function buildGrowthComparison(array $filters, array $range): array
    {
        $currentPeriodPatients = $this->buildPatientQuery($filters, $range['start'], $range['end'])->count();

        $rangeStart = $range['start'];
        $rangeEnd = $range['end'];
        $intervalDays = $rangeStart->diffInDays($rangeEnd) + 1;

        $previousStart = $rangeStart->copy()->subDays($intervalDays);
        $previousEnd = $rangeEnd->copy()->subDays($intervalDays);

        $previousPeriodPatients = $this->buildPatientQuery($filters, $previousStart, $previousEnd)->count();

        if ($previousPeriodPatients === 0 && $currentPeriodPatients === 0) {
            return [
                'currentPatients' => $currentPeriodPatients,
                'comparisonMessage' => 'No patient data is available for the selected comparison period.',
                'trendText' => 'No comparison data available',
                'currentPeriodLabel' => $range['label'],
                'previousPeriodLabel' => $previousStart->format('M j, Y') . ' - ' . $previousEnd->format('M j, Y'),
            ];
        }

        if ($previousPeriodPatients === 0) {
            return [
                'currentPatients' => $currentPeriodPatients,
                'comparisonMessage' => 'No previous-period data available to compare.',
                'trendText' => 'New patient records detected in the current period.',
                'currentPeriodLabel' => $range['label'],
                'previousPeriodLabel' => $previousStart->format('M j, Y') . ' - ' . $previousEnd->format('M j, Y'),
            ];
        }

        $changePercent = (($currentPeriodPatients - $previousPeriodPatients) / $previousPeriodPatients) * 100;

        return [
            'currentPatients' => $currentPeriodPatients,
            'comparisonMessage' => null,
            'trendText' => sprintf('%s%0.1f%%', $changePercent >= 0 ? '+' : '', $changePercent) . ' vs previous period',
            'currentPeriodLabel' => $range['label'],
            'previousPeriodLabel' => $previousStart->format('M j, Y') . ' - ' . $previousEnd->format('M j, Y'),
        ];
    }
}
