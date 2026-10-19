<?php

namespace App\Controllers;

use App\Helpers\AppointmentValidation;
use App\Services\Appointment\AppointmentService;
use CodeIgniter\Exceptions\PageNotFoundException;

class Appointments extends BaseController
{
    protected AppointmentService $appointments;

    public function __construct()
    {
        $this->appointments = new AppointmentService();
    }

    public function index()
    {
        return view('appointments/index', $this->appointments->overview());
    }

    public function create()
    {
        return view('appointments/create', [
            'appointment' => null,
            'therapists' => $this->appointments->therapists(),
            'errors' => []
        ]);
    }

    public function show(int $id)
    {
        return view('appointments/show', [
            'appointment' => $this->findOrFail($id)
        ]);
    }

    public function store()
    {
        if (! $this->validate(AppointmentValidation::RULES)) {
            return view('appointments/create', [
                'appointment' => null,
                'errors' => $this->validator->getErrors()
            ]);
        }

        $this->appointments->create(
            $this->request->getPost(AppointmentValidation::FIELDS),
            $this->request->getPost('status')
        );

        return redirect()->to('/appointments')
            ->with('success', 'Appointment scheduled successfully.');
    }

    public function edit(int $id)
    {
        return view('appointments/create', [
            'appointment' => $this->findOrFail($id),
            'therapists' => $this->appointments->therapists(),
            'errors' => []
        ]);
    }

    public function update(int $id)
    {
        $appointment = $this->findOrFail($id);

        if (! $this->validate(AppointmentValidation::RULES)) {
            return view('appointments/create', [
                'appointment' => $appointment,
                'therapists' => $this->appointments->therapists(),
                'errors' => $this->validator->getErrors()
            ]);
        }

        $this->appointments->update(
            $appointment,
            $this->request->getPost(AppointmentValidation::FIELDS),
            $this->request->getPost('status')
        );

        return redirect()->to('/appointments')
            ->with('success', 'Appointment updated successfully.');
    }

    public function delete(int $id)
    {
        $this->findOrFail($id);
        $this->appointments->delete($id);

        return redirect()->to('/appointments')
            ->with('success', 'Appointment deleted successfully.');
    }

    public function changeStatus(int $id)
    {
        $this->findOrFail($id);

        $status = $this->request->getPost('status');
        if (! AppointmentValidation::isValidStatus($status)) {
            return redirect()->to('/appointments')->with('error', 'Invalid appointment status.');
        }

        $this->appointments->changeStatus($id, $status);

        return redirect()->to('/appointments')->with('success', 'Appointment status updated.');
    }

    private function findOrFail(int $id): array
    {
        $appointment = $this->appointments->find($id);
        if ($appointment === null) {
            throw new PageNotFoundException('Appointment not found.');
        }

        return $appointment;
    }
}
