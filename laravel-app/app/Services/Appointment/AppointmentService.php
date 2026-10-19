<?php

namespace App\Services\Appointment;

use App\Auth\StaffUser;
use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;
use App\Services\Firebase\FirebaseTransport;
use App\Services\Patient\PatientService;
use App\Services\User\UserService;
use Carbon\CarbonImmutable;

/** Clinic appointments (Firestore `appointments`, see ClinicStore). */
class AppointmentService
{
    public function __construct(
        private ClinicStore $store,
        private PatientService $patients,
        private UserService $users,
        private FirebaseTransport $firebase,
    ) {
    }

    public function overview(StaffUser $user): array
    {
        $today = now()->toDateString();

        [$appointments, $visiblePatients] = $this->firebase->concurrently([
            fn () => $this->store->all(ClinicStore::APPOINTMENTS),
            fn () => $this->patients->visible($user),
        ]);
        $appointments = $appointments->sortBy(fn (ClinicRecord $a) => $a->date.' '.$a->time)->values();

        return [
            'appointments' => $appointments,
            'todayAppointments' => $appointments->where('date', $today)->count(),
            'upcomingAppointments' => $appointments->where('status', 'Upcoming')->count(),
            'activePatients' => count($visiblePatients),
            'completedToday' => $appointments->filter(fn (ClinicRecord $a) => $a->status === 'Completed' && (
                $a->completed_at
                    ? CarbonImmutable::instance($a->completed_at)->setTimezone(config('app.timezone'))->toDateString() === $today
                    : $a->date === $today
            ))->count(),
        ];
    }

    /** Active therapists from Firebase (appointments store the therapist's name). */
    public function therapists(): array
    {
        return $this->users->therapists();
    }

    public function find(string $id): ClinicRecord
    {
        return $this->store->findOrFail(ClinicStore::APPOINTMENTS, $id);
    }

    public function create(array $data): string
    {
        $data['status'] = $data['status'] ?? 'Upcoming';
        $data['completed_at'] = $data['status'] === 'Completed' ? CarbonImmutable::now() : null;

        return $this->store->create(ClinicStore::APPOINTMENTS, $this->fields($data));
    }

    public function update(ClinicRecord $appointment, array $data): void
    {
        $data['status'] = $data['status'] ?? $appointment->status;
        $data['completed_at'] = $data['status'] === 'Completed'
            ? ($appointment->status === 'Completed' && $appointment->completed_at ? $appointment->completed_at : CarbonImmutable::now())
            : null;

        $this->store->update(ClinicStore::APPOINTMENTS, $appointment->id, $this->fields($data));
    }

    public function changeStatus(ClinicRecord $appointment, string $status): void
    {
        $this->store->update(ClinicStore::APPOINTMENTS, $appointment->id, [
            'status' => $status,
            'completed_at' => $status === 'Completed' ? CarbonImmutable::now() : null,
        ]);
    }

    public function delete(ClinicRecord $appointment): void
    {
        $this->store->delete(ClinicStore::APPOINTMENTS, $appointment->id);
    }

    /** The stored shape: every form field, the date as Y-m-d text. */
    private function fields(array $data): array
    {
        return [
            'patient' => $data['patient'],
            'therapist' => $data['therapist'],
            'patient_condition' => $data['patient_condition'],
            'contact' => $data['contact'],
            'date' => CarbonImmutable::parse($data['date'])->toDateString(),
            'time' => (string) $data['time'],
            'session' => $data['session'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'],
            'completed_at' => $data['completed_at'],
        ];
    }
}
