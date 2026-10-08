<?php

namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\PatientModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Appointments extends BaseController
{
    protected $appointmentModel;
    protected $patientModel;
    protected $userModel;

    public function __construct()
    {
        $this->appointmentModel = new AppointmentModel();
        $this->patientModel = new PatientModel();
        $this->userModel = new UserModel();
    }

    public function index()
    {
        $today = date('Y-m-d');

        $appointments = $this->appointmentModel
            ->orderBy('date', 'ASC')
            ->orderBy('time', 'ASC')
            ->findAll();

        $todayAppointments = $this->appointmentModel
            ->where('date', $today)
            ->countAllResults();

        $upcomingAppointments = $this->appointmentModel
            ->where('status', 'Upcoming')
            ->countAllResults();

        $activePatients = $this->patientModel
            ->countAllResults();

        $completedToday = $this->appointmentModel->db->query(
            "SELECT COUNT(*) AS total
             FROM appointments
             WHERE status = 'Completed'
             AND ((completed_at IS NOT NULL AND DATE(completed_at) = ?)
                  OR (completed_at IS NULL AND date = ?))",
            [$today, $today]
        )->getRow()->total;

        return view('appointments/index', [
            'appointments' => $appointments,
            'todayAppointments' => $todayAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'activePatients' => $activePatients,
            'completedToday' => $completedToday,
        ]);
    }

    public function create()
    {
        return view('appointments/create', [
            'appointment' => null,
            'therapists' => $this->getTherapists(),
            'errors' => []
        ]);
    }

    public function show(int $id)
    {
        $appointment = $this->appointmentModel->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        return view('appointments/show', [
            'appointment' => $appointment
        ]);
    }

    public function store()
    {
        $rules = [
            'patient' => 'required|min_length[2]|max_length[100]',
            'therapist' => 'required|min_length[2]|max_length[100]',
            'patient_condition' => 'required|min_length[2]|max_length[150]',
            'contact' => 'required|min_length[5]|max_length[30]',
            'date' => 'required|valid_date',
            'time' => 'required',
            'session' => 'required|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return view('appointments/create', [
                'appointment' => null,
                'errors' => $this->validator->getErrors()
            ]);
        }

        $status = $this->request->getPost('status') ?? 'Upcoming';

        $this->appointmentModel->save([
            'patient' => $this->request->getPost('patient'),
            'therapist' => $this->request->getPost('therapist'),
            'patient_condition' => $this->request->getPost('patient_condition'),
            'contact' => $this->request->getPost('contact'),
            'date' => $this->request->getPost('date'),
            'time' => $this->request->getPost('time'),
            'session' => $this->request->getPost('session'),
            'notes' => $this->request->getPost('notes'),
            'status' => $status,
            'completed_at' => $status === 'Completed' ? date('Y-m-d H:i:s') : null
        ]);

        return redirect()->to('/appointments')
            ->with('success', 'Appointment scheduled successfully.');
    }

    public function edit(int $id)
    {
        $appointment = $this->appointmentModel->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        return view('appointments/create', [
            'appointment' => $appointment,
            'therapists' => $this->getTherapists(),
            'errors' => []
        ]);
    }

    public function update(int $id)
    {
        $appointment = $this->appointmentModel->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        $rules = [
            'patient' => 'required|min_length[2]|max_length[100]',
            'therapist' => 'required|min_length[2]|max_length[100]',
            'patient_condition' => 'required|min_length[2]|max_length[150]',
            'contact' => 'required|min_length[5]|max_length[30]',
            'date' => 'required|valid_date',
            'time' => 'required',
            'session' => 'required|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return view('appointments/create', [
                'appointment' => $appointment,
                'therapists' => $this->getTherapists(),
                'errors' => $this->validator->getErrors()
            ]);
        }

        $status = $this->request->getPost('status') ?? $appointment['status'];

        $this->appointmentModel->update($id, [
            'patient' => $this->request->getPost('patient'),
            'therapist' => $this->request->getPost('therapist'),
            'patient_condition' => $this->request->getPost('patient_condition'),
            'contact' => $this->request->getPost('contact'),
            'date' => $this->request->getPost('date'),
            'time' => $this->request->getPost('time'),
            'session' => $this->request->getPost('session'),
            'notes' => $this->request->getPost('notes'),
            'status' => $status,
            'completed_at' => $status === 'Completed'
                ? ($appointment['status'] === 'Completed' && !empty($appointment['completed_at'])
                    ? $appointment['completed_at']
                    : date('Y-m-d H:i:s'))
                : null
        ]);

        return redirect()->to('/appointments')
            ->with('success', 'Appointment updated successfully.');
    }

    public function delete(int $id)
    {
        $appointment = $this->appointmentModel->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        $this->appointmentModel->delete($id);

        return redirect()->to('/appointments')
            ->with('success', 'Appointment deleted successfully.');
    }

    public function changeStatus(int $id)
    {
        $appointment = $this->appointmentModel->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        $status = $this->request->getPost('status');
        $allowed = ['Upcoming', 'Completed', 'Cancelled'];

        if (!in_array($status, $allowed, true)) {
            return redirect()->to('/appointments')->with('error', 'Invalid appointment status.');
        }

        $this->appointmentModel->update($id, [
            'status' => $status,
            'completed_at' => $status === 'Completed' ? date('Y-m-d H:i:s') : null
        ]);

        return redirect()->to('/appointments')->with('success', 'Appointment status updated.');
    }

    private function getTherapists(): array
    {
        return $this->userModel
            ->where('role', 'therapist')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}