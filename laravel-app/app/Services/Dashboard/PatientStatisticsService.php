<?php

namespace App\Services\Dashboard;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Patient registration statistics for the Reports & Analytics page (admin
 * section), computed from already-loaded patients (ReportService loads them
 * together with the page's other data). "Active" means not archived (the app
 * login is enabled).
 */
class PatientStatisticsService
{
    /**
     * Everything the "Patient registrations" section and the exports need.
     *
     * @param  array  $patients  presented patients, archived ones included
     */
    public function build(Request $request, array $patients): array
    {
        $filters = $this->resolveFilters($request);
        $range = $this->dateRange($request);
        $all = $this->asRecords($patients);
        $selected = $this->filter($all, $filters, $range['start'], $range['end']);

        return [
            'filters' => $filters,
            'range' => $range,
            'rangeLabel' => $range['label'],
            'summary' => $this->summary($all, $selected, $filters),
            'registrationTrend' => $this->buildRegistrationTrend($all, $filters, $range['start'], $range['end']),
            'conditionChart' => $this->buildConditionChart($selected),
            'growthSummary' => $this->buildGrowthComparison($all, $filters, $range),
            'selected' => $selected,
            'recentPatients' => $this->paginate($selected, 10, $request),
            'conditionOptions' => $this->distinct($all, 'condition'),
            'therapistOptions' => $this->distinct($all, 'assigned_to'),
        ];
    }

    /**
     * The report's date range from ?range= (and ?from= / ?to= for "custom").
     * Shared by every section of the reports page so they cover the same days.
     *
     * @return array{start: Carbon, end: Carbon, label: string}
     */
    public function dateRange(Request $request): array
    {
        return $this->resolveDateRange($request);
    }

    /** Patients (newest first) as objects with the properties the statistics views use. */
    protected function asRecords(array $patients): Collection
    {
        return collect($patients)
            ->map(fn (array $p) => (object) [
                'id' => $p['uid'],
                'name' => $p['name'],
                'condition' => $p['condition'],
                'assigned_to' => $p['therapistName'],
                'created_at' => $p['createdAt'] ? Carbon::instance($p['createdAt'])->setTimezone(config('app.timezone')) : null,
                'patient_is_active' => $p['archived'] ? 0 : 1,
            ])
            ->sortByDesc(fn ($p) => $p->created_at?->getTimestamp() ?? 0)
            ->values();
    }

    protected function summary(Collection $all, Collection $selected, array $filters): array
    {
        return [
            'totalPatients' => $selected->count(),
            'activePatients' => $selected->where('patient_is_active', 1)->count(),
            'inactivePatients' => $selected->where('patient_is_active', 0)->count(),
            'newPatientsThisMonth' => $this->filter($all, $filters, Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth())->count(),
        ];
    }

    protected function filter(Collection $patients, array $filters, ?Carbon $start = null, ?Carbon $end = null): Collection
    {
        $from = $start?->copy()->startOfDay();
        $to = $end?->copy()->endOfDay();

        return $patients->filter(function ($p) use ($filters, $from, $to) {
            if ($filters['condition'] !== '' && $p->condition !== $filters['condition']) {
                return false;
            }
            if ($filters['therapist'] !== '' && $p->assigned_to !== $filters['therapist']) {
                return false;
            }
            if ($filters['status'] !== '' && (bool) $p->patient_is_active !== ($filters['status'] === 'active')) {
                return false;
            }

            return ! $from || ($p->created_at && $p->created_at->between($from, $to));
        })->values();
    }

    protected function paginate(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    protected function distinct(Collection $patients, string $field): array
    {
        return $patients->pluck($field)->filter(fn ($v) => $v !== null && $v !== '')->unique()->sort()->values()->all();
    }

    protected function resolveFilters(Request $request): array
    {
        return [
            'range' => $request->queryText('range', 'last_12_months'),
            'condition' => $request->queryText('condition'),
            'therapist' => $request->queryText('therapist'),
            'status' => $request->queryText('status'),
            'from' => $request->queryText('from'),
            'to' => $request->queryText('to'),
        ];
    }

    protected function resolveDateRange(Request $request): array
    {
        $preset = $request->queryText('range', 'last_12_months');
        $from = $request->queryText('from');
        $to = $request->queryText('to');

        $start = $preset === 'custom' ? $this->parseDate($from) : null;
        $end = $preset === 'custom' ? $this->parseDate($to) : null;

        if ($start && $end) {
            if ($start->greaterThan($end)) {
                [$start, $end] = [$end, $start];
            }
            $start = $start->startOfDay();
            $end = $end->endOfDay();

            return ['start' => $start, 'end' => $end, 'label' => $start->format('M j, Y').' - '.$end->format('M j, Y')];
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

    /** A Y-m-d query value as a date, or null if it's missing or malformed (falls back to the preset). */
    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null; // e.g. month 13
        }

        // Rejects dates PHP silently rolls over, like 2026-02-31.
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    protected function buildRegistrationTrend(Collection $all, array $filters, Carbon $start, Carbon $end): array
    {
        $byDay = $start->diffInDays($end) <= 31;
        $format = $byDay ? 'Y-m-d' : 'Y-m';
        $labels = [];
        $values = [];

        $cursor = $byDay ? $start->copy()->startOfDay() : $start->copy()->startOfMonth();
        $last = $byDay ? $end->copy() : $end->copy()->endOfMonth();
        while ($cursor->lte($last)) {
            $labels[] = $cursor->format($byDay ? 'M d' : 'M Y');
            $values[$cursor->format($format)] = 0;
            $byDay ? $cursor->addDay() : $cursor->addMonth();
        }

        foreach ($this->filter($all, $filters, $start, $end) as $patient) {
            $bucket = $patient->created_at->format($format);
            if (isset($values[$bucket])) {
                $values[$bucket]++;
            }
        }

        return [
            'labels' => $labels,
            'values' => array_values($values),
        ];
    }

    protected function buildConditionChart(Collection $patients): array
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

    protected function buildGrowthComparison(Collection $all, array $filters, array $range): array
    {
        $currentPeriodPatients = $this->filter($all, $filters, $range['start'], $range['end'])->count();

        $intervalDays = (int) $range['start']->diffInDays($range['end']) + 1;
        $previousStart = $range['start']->copy()->subDays($intervalDays);
        $previousEnd = $range['end']->copy()->subDays($intervalDays);

        $previousPeriodPatients = $this->filter($all, $filters, $previousStart, $previousEnd)->count();

        $result = [
            'currentPatients' => $currentPeriodPatients,
            'currentPeriodLabel' => $range['label'],
            'previousPeriodLabel' => $previousStart->format('M j, Y').' - '.$previousEnd->format('M j, Y'),
        ];

        if ($previousPeriodPatients === 0 && $currentPeriodPatients === 0) {
            return $result + [
                'comparisonMessage' => 'No patient data is available for the selected comparison period.',
                'trendText' => 'No comparison data available',
            ];
        }

        if ($previousPeriodPatients === 0) {
            return $result + [
                'comparisonMessage' => 'No previous-period data available to compare.',
                'trendText' => 'New patient records detected in the current period.',
            ];
        }

        $changePercent = (($currentPeriodPatients - $previousPeriodPatients) / $previousPeriodPatients) * 100;

        return $result + [
            'comparisonMessage' => null,
            'trendText' => sprintf('%s%0.1f%%', $changePercent >= 0 ? '+' : '', $changePercent).' vs previous period',
        ];
    }
}
