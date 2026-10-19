<?php

namespace App\Services\Dashboard;

use App\Models\AppointmentModel;
use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;
use App\Services\Billing\BillingService;

class ReportsService
{
    public const REPORT_TYPES = [
        'Patient Summary' => 'patient-summary',
        'Appointment Overview' => 'appointments',
        'Recovery Analytics' => 'recovery',
        'Therapy Compliance' => 'compliance',
    ];

    public function analytics(): array
    {
        $billing = new BillingService();
        $patientModel = new PatientModel();
        $appointmentModel = new AppointmentModel();

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
        foreach ($billing->records() as $payment) {
            $amount = $billing->normalizeFee($payment['fee'] ?? 0);
            $totalRevenue += $amount;

            $status = strtoupper((string) ($payment['status'] ?? ''));
            if ($status === 'PENDING' || $status === 'PARTIAL') {
                $outstandingPayments += $amount;
            }
        }

        $treatmentCompletionRate = $totalAppointments > 0
            ? round(($completedAppointments / $totalAppointments) * 100, 1)
            : 0;

        return [
            'totalRegisteredPatients' => $totalPatients,
            'activePatients' => $activePatients,
            'newPatientsThisMonth' => $newPatientsThisMonth,
            'totalAppointments' => $totalAppointments,
            'completedAppointments' => $completedAppointments,
            'pendingAppointments' => $pendingAppointments,
            'cancelledAppointments' => $cancelledAppointments,
            'totalRevenue' => $billing->formatPeso($totalRevenue),
            'outstandingPayments' => $billing->formatPeso($outstandingPayments),
            'treatmentCompletionRate' => $treatmentCompletionRate . '%',
        ];
    }

    /**
     * Build the CSV text for one report type.
     */
    public function exportCsv(string $type): string
    {
        [$headers, $rows] = $this->reportRows($type);

        $csvFile = fopen('php://temp', 'r+');
        fputcsv($csvFile, $headers);
        foreach ($rows as $row) {
            fputcsv($csvFile, $row);
        }

        rewind($csvFile);
        $csv = stream_get_contents($csvFile);
        fclose($csvFile);

        return (string) $csv;
    }

    private function reportRows(string $type): array
    {
        if ($type === 'appointments') {
            return [
                ['id', 'patient', 'therapist', 'condition', 'date', 'time', 'status'],
                array_map(static fn (array $r): array => [
                    $r['id'] ?? '',
                    $r['patient'] ?? '',
                    $r['therapist'] ?? '',
                    $r['patient_condition'] ?? '',
                    $r['date'] ?? '',
                    $r['time'] ?? '',
                    $r['status'] ?? '',
                ], (new AppointmentModel())->findAll()),
            ];
        }

        if ($type === 'recovery') {
            return [
                ['name', 'condition', 'total_sessions', 'compliance_rate', 'avg_pain', 'recovery_score'],
                array_map(static fn (array $r): array => [
                    $r['name'] ?? '',
                    $r['condition'] ?? '',
                    $r['total_sessions'] ?? 0,
                    $r['compliance_rate'] ?? 0,
                    $r['avg_pain'] ?? 0,
                    $r['recovery_score'] ?? 0,
                ], (new ExerciseRecordModel())->getPatientStats()),
            ];
        }

        if ($type === 'compliance') {
            return [
                ['name', 'condition', 'total_sessions', 'compliance_rate'],
                array_map(static fn (array $r): array => [
                    $r['name'] ?? '',
                    $r['condition'] ?? '',
                    $r['total_sessions'] ?? 0,
                    $r['compliance_rate'] ?? 0,
                ], (new ExerciseRecordModel())->getPatientStats()),
            ];
        }

        return [
            ['id', 'name', 'condition', 'created_at'],
            array_map(static fn (array $r): array => [
                $r['id'] ?? '',
                $r['name'] ?? '',
                $r['condition'] ?? '',
                $r['created_at'] ?? '',
            ], (new PatientModel())->findAll()),
        ];
    }
}
