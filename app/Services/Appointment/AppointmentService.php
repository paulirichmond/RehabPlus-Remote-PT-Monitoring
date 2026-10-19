<?php

namespace App\Services\Appointment;

use App\Models\AppointmentModel;
use App\Models\PatientModel;
use App\Models\UserModel;

class AppointmentService
{
    private AppointmentModel $appointments;

    public function __construct()
    {
        $this->appointments = new AppointmentModel();
    }

    public function find(int $id): ?array
    {
        return $this->appointments->find($id);
    }

    public function overview(): array
    {
        $today = date('Y-m-d');

        return [
            'appointments' => $this->appointments->orderBy('date', 'ASC')->orderBy('time', 'ASC')->findAll(),
            'todayAppointments' => $this->appointments->where('date', $today)->countAllResults(),
            'upcomingAppointments' => $this->appointments->where('status', 'Upcoming')->countAllResults(),
            'activePatients' => (new PatientModel())->countAllResults(),
            'completedToday' => $this->appointments->db->query(
                "SELECT COUNT(*) AS total
                 FROM appointments
                 WHERE status = 'Completed'
                 AND ((completed_at IS NOT NULL AND DATE(completed_at) = ?)
                      OR (completed_at IS NULL AND date = ?))",
                [$today, $today]
            )->getRow()->total,
        ];
    }

    public function therapists(): array
    {
        return (new UserModel())
            ->where('role', 'therapist')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function create(array $data, ?string $status): void
    {
        $status ??= 'Upcoming';

        $this->appointments->save($data + [
            'status' => $status,
            'completed_at' => $status === 'Completed' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function update(array $appointment, array $data, ?string $status): void
    {
        $status ??= $appointment['status'];

        $this->appointments->update($appointment['id'], $data + [
            'status' => $status,
            'completed_at' => $status === 'Completed'
                ? ($appointment['status'] === 'Completed' && ! empty($appointment['completed_at'])
                    ? $appointment['completed_at']
                    : date('Y-m-d H:i:s'))
                : null,
        ]);
    }

    public function changeStatus(int $id, string $status): void
    {
        $this->appointments->update($id, [
            'status' => $status,
            'completed_at' => $status === 'Completed' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    public function delete(int $id): void
    {
        $this->appointments->delete($id);
    }
}
