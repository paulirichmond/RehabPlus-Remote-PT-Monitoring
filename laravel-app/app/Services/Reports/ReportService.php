<?php

namespace App\Services\Reports;

use App\Auth\StaffUser;
use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;
use App\Services\Dashboard\DashboardOverviewService;
use App\Services\Dashboard\PatientStatisticsService;
use App\Services\Firebase\FirebaseTransport;
use App\Services\Patient\ClinicalRecordService;
use App\Services\Patient\PatientProgress;
use App\Services\Patient\PatientService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The Reports & Analytics page: one report, with sections shown by role so
 * nobody sees more than their other pages already show them.
 *
 *   recovery       everyone       session accuracy/pain (therapists: own patients)
 *   appointments   admin, staff   appointments dated in the range
 *   billing        admin, staff   payments dated in the range
 *   goals          admin, therapist  treatment goals (therapists: own patients)
 *   patients       admin          registrations, filters, charts, exports
 *
 * Data is loaded in two parallel rounds: patients, appointments, payments
 * and (clinic-wide) sessions together; then whatever needs the patient list
 * (a therapist's sessions, goals).
 */
class ReportService
{
    public const SECTIONS = ['recovery', 'appointments', 'billing', 'goals', 'patients'];

    public function __construct(
        private PatientService $patients,
        private PatientProgress $progress,
        private ClinicalRecordService $records,
        private ClinicStore $store,
        private PatientStatisticsService $patientStatistics,
        private FirebaseTransport $firebase,
    ) {
    }

    /** Which sections this user gets. */
    public static function sectionsFor(StaffUser $user): array
    {
        return array_values(array_filter(self::SECTIONS, fn (string $section) => match ($section) {
            'recovery' => true,
            'appointments', 'billing' => $user->isAdmin() || $user->role === StaffUser::STAFF,
            'goals' => $user->isClinician(),
            'patients' => $user->isAdmin(),
        }));
    }

    public function build(StaffUser $user, Request $request): array
    {
        $sections = self::sectionsFor($user);
        $shows = fn (string $section) => in_array($section, $sections, true);
        $range = $this->patientStatistics->dateRange($request);

        // Round 1: everything that doesn't depend on the patient list.
        $first = $this->firebase->concurrently(array_filter([
            'patients' => fn () => $this->patients->visible($user, includeArchived: true),
            'appointments' => $shows('appointments') ? fn () => $this->store->all(ClinicStore::APPOINTMENTS) : null,
            'payments' => $shows('billing') ? fn () => $this->store->all(ClinicStore::PAYMENTS) : null,
            'sessions' => $user->isTherapist() ? null : fn () => $this->progress->sessionsByPatient(),
        ]));

        $allPatients = $first['patients'];
        $activePatients = array_values(array_filter($allPatients, fn (array $p) => ! $p['archived']));

        // Round 2: reads that need the patient list.
        $second = $this->firebase->concurrently(array_filter([
            'sessions' => $user->isTherapist()
                ? fn () => $this->progress->sessionsByPatient(array_column($activePatients, 'uid'))
                : null,
            'goals' => $shows('goals') ? fn () => $this->records->list(ClinicalRecordService::GOALS, $activePatients) : null,
        ]));

        $sessionsByPatient = $second['sessions'] ?? $first['sessions'] ?? [];

        return [
            'sections' => $sections,
            'filters' => [
                'range' => $request->queryText('range', 'last_12_months'),
                'from' => $request->queryText('from'),
                'to' => $request->queryText('to'),
            ],
            'rangeLabel' => $range['label'],
            'recovery' => DashboardOverviewService::summarise($activePatients, $sessionsByPatient),
            'appointments' => $shows('appointments') ? $this->appointmentSummary($first['appointments'], $range) : null,
            'billing' => $shows('billing') ? $this->billingSummary($first['payments'], $range) : null,
            'goals' => $shows('goals') ? $this->goalSummary($second['goals'], $range) : null,
            'patients' => $shows('patients') ? $this->patientStatistics->build($request, $allPatients) : null,
        ];
    }

    /**
     * Rows for the CSV export: a summary block per section the user can see,
     * then the filtered patient list.
     */
    public function csvRows(array $report): array
    {
        $rows = [
            ['RehabPlus', 'Reports & Analytics'],
            ['Range', $report['rangeLabel']],
            ['Generated', now()->format('F j, Y H:i:s')],
            [],
        ];

        $recovery = $report['recovery'];
        array_push($rows,
            ['Recovery'],
            ['Active patients', $recovery['totalPatients']],
            ['Average movement accuracy (%)', $recovery['avgCompliance']],
            ['Average pain (0-10)', $recovery['avgPain']],
            [],
        );

        if ($appointments = $report['appointments']) {
            array_push($rows,
                ['Appointments'],
                ['Total', $appointments['total']],
                ['Completed', $appointments['completed']],
                ['Upcoming', $appointments['upcoming']],
                ['Cancelled', $appointments['cancelled']],
                ['Completion rate (%)', $appointments['completionRate'] ?? ''],
                [],
            );
        }

        if ($billing = $report['billing']) {
            array_push($rows,
                ['Billing'],
                ['Billed', number_format($billing['billed'], 2, '.', '')],
                ['Collected (paid)', number_format($billing['collected'], 2, '.', '')],
                ['Outstanding (pending + partial)', number_format($billing['outstanding'], 2, '.', '')],
                ['Records', $billing['count']],
                [],
            );
        }

        if ($goals = $report['goals']) {
            array_push($rows,
                ['Treatment goals'],
                ['Achievement rate (%)', $goals['achievementRate'] ?? ''],
                ['In progress', $goals['active']],
                ['Achieved in range', $goals['achievedInRange']],
                [],
            );
        }

        if ($patients = $report['patients']) {
            $summary = $patients['summary'];
            array_push($rows,
                ['Patient registrations'],
                ['Patients (filtered)', $summary['totalPatients']],
                ['Active', $summary['activePatients']],
                ['Inactive', $summary['inactivePatients']],
                ['New this month', $summary['newPatientsThisMonth']],
                [],
                ['Patient Name', 'Condition', 'Assigned Therapist', 'Registration Date', 'Status'],
            );

            foreach ($patients['selected'] as $patient) {
                $rows[] = [
                    $patient->name,
                    $patient->condition ?: 'Unspecified',
                    $patient->assigned_to ?: 'Unassigned',
                    $patient->created_at ? $patient->created_at->format('Y-m-d H:i:s') : '',
                    $patient->patient_is_active ? 'Active' : 'Inactive',
                ];
            }
        }

        return $rows;
    }

    /**
     * Appointments dated inside the range, by status and by therapist.
     *
     * @param  Collection<int, ClinicRecord>  $appointments
     */
    private function appointmentSummary(Collection $appointments, array $range): array
    {
        $inRange = $appointments->filter(fn (ClinicRecord $a) => $this->dateInRange($a->date, $range))->values();
        $count = fn (string $status) => $inRange->where('status', $status)->count();

        $completed = $count('Completed');
        $cancelled = $count('Cancelled');
        $decided = $completed + $cancelled;

        $byTherapist = $inRange
            ->groupBy(fn (ClinicRecord $a) => trim((string) $a->therapist) ?: 'Unassigned')
            ->map(fn (Collection $group, string $therapist) => [
                'therapist' => $therapist,
                'total' => $group->count(),
                'completed' => $group->where('status', 'Completed')->count(),
                'cancelled' => $group->where('status', 'Cancelled')->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $inRange->count(),
            'completed' => $completed,
            'upcoming' => $count('Upcoming'),
            'cancelled' => $cancelled,
            // Of the appointments that are over (completed or cancelled), how many happened.
            'completionRate' => $decided ? (int) round($completed / $decided * 100) : null,
            'byTherapist' => $byTherapist,
        ];
    }

    /**
     * Payments dated inside the range. Same definitions as the Billing page:
     * outstanding = full fee of Pending and Partial records.
     *
     * @param  Collection<int, ClinicRecord>  $payments
     */
    private function billingSummary(Collection $payments, array $range): array
    {
        $inRange = $payments->filter(fn (ClinicRecord $p) => $this->dateInRange($p->date, $range))->values();
        $fee = fn (ClinicRecord $p) => (float) $p->fee;

        return [
            'billed' => $inRange->sum($fee),
            'collected' => $inRange->where('status', 'Paid')->sum($fee),
            'outstanding' => $inRange->whereIn('status', ['Pending', 'Partial'])->sum($fee),
            'count' => $inRange->count(),
            'byStatus' => [
                'Paid' => $inRange->where('status', 'Paid')->count(),
                'Partial' => $inRange->where('status', 'Partial')->count(),
                'Pending' => $inRange->where('status', 'Pending')->count(),
            ],
        ];
    }

    /** Goal achievement over all goals, plus goals achieved inside the range. */
    private function goalSummary(array $goals, array $range): array
    {
        $achievedInRange = array_filter($goals, fn (array $g) => $g['status'] === 'achieved'
            && $g['achievedAt'] instanceof CarbonImmutable
            && $g['achievedAt']->between($range['start'], $range['end']));

        return [
            'achievementRate' => ClinicalRecordService::goalAchievement($goals),
            'active' => count(array_filter($goals, fn (array $g) => $g['status'] === 'active')),
            'achievedInRange' => count($achievedInRange),
            'total' => count($goals),
        ];
    }

    /** Whether a stored "Y-m-d" date falls inside the report range. */
    private function dateInRange(mixed $date, array $range): bool
    {
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        return $date >= $range['start']->format('Y-m-d') && $date <= $range['end']->format('Y-m-d');
    }
}
