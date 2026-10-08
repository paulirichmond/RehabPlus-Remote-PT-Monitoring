<?php

namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;
use App\Models\UserModel;

class DashboardController extends BaseController
{
    public function index()
    {
        $exerciseRecords = new ExerciseRecordModel();
        $patientStats = $exerciseRecords->getPatientStats();
        $recentRecords = $exerciseRecords->getRecentRecords(10);
        $totalPatients = (new PatientModel())->countAllResults();
        $patientsWithRecords = array_filter($patientStats, static fn (array $patient): bool => (int) $patient['total_sessions'] > 0);
        $recordedPatientCount = count($patientsWithRecords);
        $avgCompliance = $recordedPatientCount
            ? round(array_sum(array_column($patientsWithRecords, 'compliance_rate')) / $recordedPatientCount, 1) : 0;
        $avgPain = $recordedPatientCount
            ? round(array_sum(array_column($patientsWithRecords, 'avg_pain')) / $recordedPatientCount, 1) : 0;
        $hasRecoveryData = $recordedPatientCount > 0;

        $recoveryLabels = array_column($patientStats, 'name');
        $recoveryValues = array_map(
            static fn (array $patient): float => (float) ($patient['recovery_score'] ?? 0),
            $patientStats
        );

        $conditionCounts = [];
        foreach ($patientStats as $patient) {
            $condition = $patient['condition'];
            $conditionCounts[$condition] = ($conditionCounts[$condition] ?? 0) + 1;
        }

        return view('dashboard/index', compact(
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

    public function reports()
    {
        return view('dashboard/reports', [
            'reportTypes' => [
                'Patient Summary' => 'patient-summary',
                'Appointment Overview' => 'appointments',
                'Recovery Analytics' => 'recovery',
                'Therapy Compliance' => 'compliance',
            ],
        ]);
    }

    private function getDefaultBillingRecords(): array
    {
        return [];
    }

    private function formatPeso(float $amount): string
    {
        return '₱ ' . number_format($amount, 2, '.', ',');
    }

    private function normalizeBillingFeeValue($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return (float) ($clean !== '' ? $clean : 0);
    }

    public function billing()
    {
        $payments = session()->get('billing_records') ?? $this->getDefaultBillingRecords();

        foreach ($payments as &$payment) {
            $payment['fee'] = $this->formatPeso($this->normalizeBillingFeeValue($payment['fee'] ?? 0));
        }
        unset($payment);

        $totalFees = 0.0;
        foreach ($payments as $payment) {
            $totalFees += $this->normalizeBillingFeeValue($payment['fee'] ?? 0);
        }

        $paidCount = count(array_filter($payments, static fn (array $payment): bool => ($payment['status'] ?? '') === 'Paid'));
        $statusText = count($payments) > 0 ? round(($paidCount / count($payments)) * 100) . '% collected' : '0% collected';

        return view('dashboard/billing', [
            'payments' => $payments,
            'sessionFees' => $this->formatPeso($totalFees),
            'paymentStatus' => $statusText,
            'outstanding' => $this->formatPeso(max(0, $totalFees * 0.35)),
            'paymentHistory' => count($payments),
        ]);
    }

    public function saveBilling()
    {
        $patient = trim((string) $this->request->getPost('patient'));
        $session = trim((string) $this->request->getPost('session'));
        $fee = trim((string) $this->request->getPost('fee'));
        $status = trim((string) $this->request->getPost('status'));
        $date = trim((string) $this->request->getPost('date'));

        if ($patient === '' || $session === '' || $fee === '' || $date === '') {
            return redirect()->to(site_url('billing'))->with('error', 'Patient, session, fee, and date are required.');
        }

        $payment = [
            'patient' => $patient,
            'session' => $session,
            'fee' => $this->formatPeso((float) $fee),
            'status' => $status !== '' ? $status : 'Pending',
            'date' => $date,
        ];

        $payments = session()->get('billing_records') ?? $this->getDefaultBillingRecords();
        $payments[] = $payment;
        session()->set('billing_records', $payments);

        return redirect()->to(site_url('billing'))->with('success', 'Payment record added successfully.');
    }

    private function getDefaultInventoryItems(): array
    {
        return [];
    }

    public function inventory()
    {
        $items = session()->get('inventory_items') ?? $this->getDefaultInventoryItems();
        $lowStock = count(array_filter($items, static fn (array $item): bool => (int) ($item['stock'] ?? 0) <= (int) ($item['reorder'] ?? 0)));
        $totalStock = array_sum(array_map(static fn (array $item): int => (int) ($item['stock'] ?? 0), $items));

        return view('dashboard/inventory', [
            'items' => $items,
            'totalStock' => $totalStock,
            'lowStock' => $lowStock,
            'restockNeeded' => 0,
        ]);
    }

    public function saveInventory()
    {
        $item = trim((string) $this->request->getPost('item'));
        $category = trim((string) $this->request->getPost('category'));
        $stock = trim((string) $this->request->getPost('stock'));
        $reorder = trim((string) $this->request->getPost('reorder'));
        $status = trim((string) $this->request->getPost('status'));

        if ($item === '' || $category === '' || $stock === '') {
            return redirect()->to(site_url('inventory'))->with('error', 'Item, category, and stock level are required.');
        }

        $inventoryItem = [
            'item' => $item,
            'category' => $category,
            'stock' => (int) $stock,
            'reorder' => $reorder !== '' ? (int) $reorder : 10,
            'status' => $status !== '' ? $status : ((int) $stock <= 10 ? 'Low' : 'Healthy'),
        ];

        $items = session()->get('inventory_items') ?? $this->getDefaultInventoryItems();
        $items[] = $inventoryItem;
        session()->set('inventory_items', $items);

        return redirect()->to(site_url('inventory'))->with('success', 'Inventory item added successfully.');
    }

    private function getDefaultSchedules(): array
    {
        return [];
    }

    private function getDefaultTimeOff(): array
    {
        return [];
    }

    private function getWeekDays(int $weekOffset = 0): array
    {
        $monday = new \DateTimeImmutable('monday this week');
        $monday = $monday->modify("+{$weekOffset} week");

        $days = [];
        foreach (range(0, 6) as $offset) {
            $date = $monday->modify("+{$offset} day");
            $days[] = [
                'label' => $date->format('l'),
                'date' => $date->format('Y-m-d'),
                'short' => $date->format('M j'),
            ];
        }

        return $days;
    }

    private function getNowForDay(string $dayName): string
    {
        $date = new \DateTimeImmutable('today');
        $existing = $date->format('l');

        if ($existing === $dayName) {
            return $date->format('Y-m-d');
        }

        while ($date->format('l') !== $dayName) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d');
    }

    private function parseShiftRange(string $shift): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string) $shift));

        if (preg_match('/(\d{1,2}:\d{2})\s*(?:-|–|to|until)\s*(\d{1,2}:\d{2})/i', $clean, $matches) === 1) {
            $start = strtotime('1970-01-01 ' . $matches[1]);
            $end = strtotime('1970-01-01 ' . $matches[2]);

            return [$start, $end];
        }

        return [0, 0];
    }

    private function shiftTimesOverlap(array $first, array $second): bool
    {
        return $first[0] < $second[1] && $second[0] < $first[1];
    }

    private function getStaffDirectory(): array
    {
        $users = (new UserModel())
            ->whereIn('role', ['staff', 'manager', 'superadmin'])
            ->orderBy('name', 'ASC')
            ->findAll();

        $staff = [];
        foreach ($users as $user) {
            $name = trim((string) ($user['name'] ?? ''));
            if ($name !== '') {
                $staff[$name] = trim((string) ($user['role'] ?? 'staff'));
            }
        }

        $schedules = session()->get('staff_schedule') ?? [];
        foreach ($schedules as $entry) {
            $name = trim((string) ($entry['staff'] ?? ''));
            if ($name !== '' && ! isset($staff[$name])) {
                $staff[$name] = trim((string) ($entry['role'] ?? 'staff'));
            }
        }

        return $staff;
    }

    private function getStaffAvailability(string $staffName, string $date, array $schedules, array $timeOffEntries): string
    {
        $normalized = strtolower(trim($staffName));
        $staffSchedule = array_values(array_filter($schedules, static fn (array $entry): bool => strtolower(trim((string) ($entry['staff'] ?? ''))) === $normalized && ((string) ($entry['date'] ?? '') !== '' ? (string) $entry['date'] : (string) $entry['day']) === $date));

        if ($this->hasTimeOffForStaff($staffName, $date, $timeOffEntries)) {
            return 'On Leave';
        }

        if ($staffSchedule === []) {
            return 'Unavailable';
        }

        foreach ($staffSchedule as $firstEntry) {
            $firstRange = $this->parseShiftRange((string) ($firstEntry['shift'] ?? ''));
            if ($firstRange[0] === 0 && $firstRange[1] === 0) {
                continue;
            }

            foreach ($staffSchedule as $secondEntry) {
                if ($firstEntry === $secondEntry) {
                    continue;
                }

                $secondRange = $this->parseShiftRange((string) ($secondEntry['shift'] ?? ''));
                if ($secondRange[0] !== 0 || $secondRange[1] !== 0) {
                    if ($this->shiftTimesOverlap($firstRange, $secondRange)) {
                        return 'Fully Booked';
                    }
                }
            }
        }

        return 'Available';
    }

    private function hasTimeOffForStaff(string $staffName, string $date, array $timeOffEntries): bool
    {
        $normalized = strtolower(trim($staffName));

        foreach ($timeOffEntries as $entry) {
            if (strtolower(trim((string) ($entry['staff'] ?? ''))) !== $normalized) {
                continue;
            }

            $entryDate = trim((string) ($entry['date'] ?? ''));
            if ($entryDate === $date) {
                return true;
            }
        }

        return false;
    }

    private function hasScheduleConflict(array $schedules, array $candidate): bool
    {
        $candidateStaff = strtolower(trim((string) ($candidate['staff'] ?? '')));
        $candidateRoom = strtolower(trim((string) ($candidate['room'] ?? 'General Room')));
        $candidateDay = trim((string) ($candidate['day'] ?? ''));
        $candidateRange = $this->parseShiftRange((string) ($candidate['shift'] ?? ''));

        if ($candidateRange[0] === 0 && $candidateRange[1] === 0) {
            return false;
        }

        foreach ($schedules as $existing) {
            $existingDay = trim((string) ($existing['day'] ?? ''));
            if ($existingDay !== $candidateDay) {
                continue;
            }

            $existingRange = $this->parseShiftRange((string) ($existing['shift'] ?? ''));
            if ($existingRange[0] === 0 && $existingRange[1] === 0) {
                continue;
            }

            $existingStaff = strtolower(trim((string) ($existing['staff'] ?? '')));
            $existingRoom = strtolower(trim((string) ($existing['room'] ?? 'General Room')));

            if ($existingStaff === $candidateStaff && $this->shiftTimesOverlap($candidateRange, $existingRange)) {
                return true;
            }

            if ($existingRoom === $candidateRoom && $this->shiftTimesOverlap($candidateRange, $existingRange)) {
                return true;
            }
        }

        return false;
    }

    public function schedule()
    {
        $weekOffset = max(-12, min(12, (int) ($this->request->getGet('week_offset') ?? 0)));
        $weekDays = $this->getWeekDays($weekOffset);
        $schedules = session()->get('staff_schedule') ?? $this->getDefaultSchedules();
        $timeOffEntries = session()->get('staff_time_off') ?? $this->getDefaultTimeOff();
        $staffDirectory = $this->getStaffDirectory();

        $todayLabel = date('l');
        $todayDate = date('Y-m-d');
        $todayEntries = array_values(array_filter($schedules, static fn (array $entry): bool => (string) ($entry['day'] ?? '') === $todayLabel));

        $todayStaffList = [];
        $staffAvailability = [];
        foreach ($staffDirectory as $staffName => $role) {
            $status = $this->getStaffAvailability($staffName, $todayDate, $schedules, $timeOffEntries);
            $staffAvailability[$staffName] = $status;
            $dayEntry = null;
            foreach ($todayEntries as $entry) {
                if (strtolower(trim((string) ($entry['staff'] ?? ''))) === strtolower($staffName)) {
                    $dayEntry = $entry;
                    break;
                }
            }

            $todayStaffList[] = [
                'name' => $staffName,
                'role' => $role,
                'shift' => $dayEntry['shift'] ?? 'Off',
                'room' => $dayEntry['room'] ?? 'Not assigned',
                'status' => $status,
            ];
        }

        $weeklySchedule = [];
        foreach ($staffDirectory as $staffName => $role) {
            foreach ($weekDays as $day) {
                $matches = array_values(array_filter($schedules, static fn (array $entry): bool => strtolower(trim((string) ($entry['staff'] ?? ''))) === strtolower($staffName) && (string) ($entry['day'] ?? '') === $day['label']));
                if ($matches !== []) {
                    $weeklySchedule[$staffName][$day['label']] = [
                        'shift' => $matches[0]['shift'] ?? 'Off',
                        'room' => $matches[0]['room'] ?? 'General Room',
                    ];
                }
            }
        }

        $staffOnDuty = count(array_unique(array_map(static fn (array $entry): string => trim((string) ($entry['staff'] ?? '')), $todayEntries)));
        $activeRooms = count(array_unique(array_filter(array_map(static fn (array $entry): string => trim((string) ($entry['room'] ?? '')), $todayEntries))));

        return view('dashboard/schedule', [
            'staffDirectory' => array_map(static fn (string $name, string $role): array => ['name' => $name, 'role' => $role], array_keys($staffDirectory), array_values($staffDirectory)),
            'schedules' => $schedules,
            'staffCount' => $staffOnDuty,
            'todayShifts' => count($todayEntries),
            'todayStaffList' => $todayStaffList,
            'activeRooms' => $activeRooms,
            'weekDays' => $weekDays,
            'weekOffset' => $weekOffset,
            'weeklySchedule' => $weeklySchedule,
            'timeOffEntries' => $timeOffEntries,
            'staffAvailability' => $staffAvailability,
        ]);
    }

    public function saveSchedule()
    {
        $staff = trim((string) $this->request->getPost('staff'));
        $role = trim((string) $this->request->getPost('role'));
        $day = trim((string) $this->request->getPost('day'));
        $shift = trim((string) $this->request->getPost('shift'));
        $room = trim((string) $this->request->getPost('room'));
        $date = trim((string) $this->request->getPost('date'));

        if ($staff === '' || $role === '' || $day === '' || $shift === '') {
            return redirect()->to(site_url('schedule'))->with('error', 'Staff name, role, day, and shift are required.');
        }

        $entry = [
            'staff' => $staff,
            'role' => $role,
            'day' => $day,
            'date' => $date !== '' ? $date : $this->getNowForDay($day),
            'shift' => $shift,
            'room' => $room !== '' ? $room : 'General Room',
        ];

        $schedules = session()->get('staff_schedule') ?? $this->getDefaultSchedules();
        if ($this->hasScheduleConflict($schedules, $entry)) {
            return redirect()->to(site_url('schedule'))->with('error', 'Schedule conflict detected: this staff member already has a shift at that time, or the room is already assigned to another staff member.');
        }

        $schedules[] = $entry;
        session()->set('staff_schedule', $schedules);

        return redirect()->to(site_url('schedule'))->with('success', 'Staff schedule added successfully.');
    }

    public function saveTimeOff()
    {
        $staff = trim((string) $this->request->getPost('staff'));
        $date = trim((string) $this->request->getPost('date'));
        $reason = trim((string) $this->request->getPost('reason'));
        $type = trim((string) $this->request->getPost('time_off_type'));
        $time = trim((string) $this->request->getPost('time'));

        if ($staff === '' || $date === '' || $reason === '') {
            return redirect()->to(site_url('schedule'))->with('error', 'Staff member, date, and reason are required for time off.');
        }

        $entry = [
            'staff' => $staff,
            'date' => $date,
            'reason' => $reason,
            'type' => $type !== '' ? $type : 'Full day',
            'time' => $time !== '' ? $time : 'Full day',
        ];

        $timeOffEntries = session()->get('staff_time_off') ?? $this->getDefaultTimeOff();
        $timeOffEntries[] = $entry;
        session()->set('staff_time_off', $timeOffEntries);

        return redirect()->to(site_url('schedule'))->with('success', 'Time off entry added successfully.');
    }

    private function getDefaultNotes(): array
    {
        return [];
    }

    public function notes()
    {
        $notes = session()->get('therapy_notes') ?? $this->getDefaultNotes();

        return view('dashboard/notes', [
            'notes' => $notes,
            'recentCount' => count($notes),
            'todayNotes' => 0,
        ]);
    }

    public function saveNotes()
    {
        $patient = trim((string) $this->request->getPost('patient'));
        $therapist = trim((string) $this->request->getPost('therapist'));
        $topic = trim((string) $this->request->getPost('topic'));
        $note = trim((string) $this->request->getPost('note'));
        $date = trim((string) $this->request->getPost('date'));

        if ($patient === '' || $therapist === '' || $topic === '' || $note === '' || $date === '') {
            return redirect()->to(site_url('notes'))->with('error', 'Patient, therapist, topic, note, and date are required.');
        }

        $entry = [
            'patient' => $patient,
            'therapist' => $therapist,
            'topic' => $topic,
            'note' => $note,
            'date' => $date,
        ];

        $notes = session()->get('therapy_notes') ?? $this->getDefaultNotes();
        $notes[] = $entry;
        session()->set('therapy_notes', $notes);

        return redirect()->to(site_url('notes'))->with('success', 'Therapy note added successfully.');
    }

    private function getDefaultAssessments(): array
    {
        return [];
    }

    public function assessments()
    {
        $assessments = session()->get('patient_assessments') ?? $this->getDefaultAssessments();

        return view('dashboard/assessments', [
            'assessments' => $assessments,
            'averageScore' => '0%',
            'goalTracking' => 0,
            'activePatients' => count($assessments),
        ]);
    }

    public function saveAssessments()
    {
        $patient = trim((string) $this->request->getPost('patient'));
        $therapist = trim((string) $this->request->getPost('therapist'));
        $type = trim((string) $this->request->getPost('type'));
        $score = trim((string) $this->request->getPost('score'));
        $goal = trim((string) $this->request->getPost('goal'));
        $date = trim((string) $this->request->getPost('date'));

        if ($patient === '' || $therapist === '' || $type === '' || $score === '' || $goal === '' || $date === '') {
            return redirect()->to(site_url('assessments'))->with('error', 'All assessment fields are required.');
        }

        $entry = [
            'patient' => $patient,
            'therapist' => $therapist,
            'type' => $type,
            'score' => $score,
            'goal' => $goal,
            'date' => $date,
        ];

        $assessments = session()->get('patient_assessments') ?? $this->getDefaultAssessments();
        $assessments[] = $entry;
        session()->set('patient_assessments', $assessments);

        return redirect()->to(site_url('assessments'))->with('success', 'Assessment added successfully.');
    }

    public function reportsAnalytics()
    {
        $patientModel = new PatientModel();
        $appointmentModel = new AppointmentModel();
        $payments = session()->get('billing_records') ?? $this->getDefaultBillingRecords();

        $totalPatients = $patientModel->countAllResults();
        $newPatientsThisMonth = $patientModel
            ->where('created_at >=', date('Y-m-01 00:00:00'))
            ->where('created_at <=', date('Y-m-t 23:59:59'))
            ->countAllResults();

        $activePatients = (int) $appointmentModel->db->query(
            "SELECT COUNT(DISTINCT patient) AS total FROM appointments WHERE status != 'Cancelled'"
        )->getRow()->total;

        $totalAppointments = $appointmentModel->countAllResults();
        $completedAppointments = $appointmentModel->where('status', 'Completed')->countAllResults();
        $pendingAppointments = $appointmentModel->where('status', 'Upcoming')->countAllResults();
        $cancelledAppointments = $appointmentModel->where('status', 'Cancelled')->countAllResults();

        $totalRevenue = 0.0;
        $outstandingPayments = 0.0;
        foreach ($payments as $payment) {
            $amount = $this->normalizeBillingFeeValue($payment['fee'] ?? 0);
            $totalRevenue += $amount;

            $status = strtoupper((string) ($payment['status'] ?? ''));
            if ($status === 'PENDING' || $status === 'PARTIAL') {
                $outstandingPayments += $amount;
            }
        }

        $treatmentCompletionRate = $totalAppointments > 0
            ? round(($completedAppointments / $totalAppointments) * 100, 1)
            : 0;

        return view('dashboard/reports_analytics', [
            'totalRegisteredPatients' => $totalPatients,
            'activePatients' => $activePatients,
            'newPatientsThisMonth' => $newPatientsThisMonth,
            'totalAppointments' => $totalAppointments,
            'completedAppointments' => $completedAppointments,
            'pendingAppointments' => $pendingAppointments,
            'cancelledAppointments' => $cancelledAppointments,
            'totalRevenue' => $this->formatPeso($totalRevenue),
            'outstandingPayments' => $this->formatPeso($outstandingPayments),
            'treatmentCompletionRate' => $treatmentCompletionRate . '%',
        ]);
    }

    public function export()
    {
        $type = strtolower((string) ($this->request->getGet('type') ?? 'patient-summary'));
        $headers = ['id', 'name', 'condition', 'created_at'];
        $records = (new PatientModel())->findAll();

        if ($type === 'appointments') {
            $headers = ['id', 'patient', 'therapist', 'condition', 'date', 'time', 'status'];
            $records = (new \App\Models\AppointmentModel())->findAll();
        } elseif ($type === 'recovery') {
            $headers = ['name', 'condition', 'total_sessions', 'compliance_rate', 'avg_pain', 'recovery_score'];
            $records = (new ExerciseRecordModel())->getPatientStats();
        } elseif ($type === 'compliance') {
            $headers = ['name', 'condition', 'total_sessions', 'compliance_rate'];
            $records = (new ExerciseRecordModel())->getPatientStats();
        }

        $csvFile = fopen('php://temp', 'r+');
        fputcsv($csvFile, $headers);

        foreach ($records as $record) {
            $row = [];
            if ($type === 'appointments') {
                $row = [
                    $record['id'] ?? '',
                    $record['patient'] ?? '',
                    $record['therapist'] ?? '',
                    $record['patient_condition'] ?? '',
                    $record['date'] ?? '',
                    $record['time'] ?? '',
                    $record['status'] ?? '',
                ];
            } elseif ($type === 'recovery') {
                $row = [
                    $record['name'] ?? '',
                    $record['condition'] ?? '',
                    $record['total_sessions'] ?? 0,
                    $record['compliance_rate'] ?? 0,
                    $record['avg_pain'] ?? 0,
                    $record['recovery_score'] ?? 0,
                ];
            } elseif ($type === 'compliance') {
                $row = [
                    $record['name'] ?? '',
                    $record['condition'] ?? '',
                    $record['total_sessions'] ?? 0,
                    $record['compliance_rate'] ?? 0,
                ];
            } else {
                $row = [
                    $record['id'] ?? '',
                    $record['name'] ?? '',
                    $record['condition'] ?? '',
                    $record['created_at'] ?? '',
                ];
            }

            fputcsv($csvFile, $row);
        }

        rewind($csvFile);
        $csv = stream_get_contents($csvFile);
        fclose($csvFile);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="rehabplus-' . $type . '.csv"')
            ->setBody((string) $csv);
    }

}
