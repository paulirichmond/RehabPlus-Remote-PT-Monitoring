<?php

namespace App\Http\Controllers;

use App\Helpers\AppointmentValidation;
use App\Services\Appointment\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $appointments)
    {
    }

    public function index(Request $request): View
    {
        return view('appointments.index', $this->appointments->overview($request->user()));
    }

    public function create(): View
    {
        return view('appointments.create', [
            'appointment' => null,
            'therapists' => $this->appointments->therapists(),
            'errors' => [],
        ]);
    }

    public function show(string $id)
    {
        return view('appointments.show', ['appointment' => $this->appointments->find($id)]);
    }

    public function store(Request $request)
    {
        $this->appointments->create(AppointmentValidation::appointment($request));

        return redirect()->route('appointments.index')->with('success', 'Appointment scheduled successfully.');
    }

    public function edit(string $id)
    {
        return view('appointments.create', [
            'appointment' => $this->appointments->find($id),
            'therapists' => $this->appointments->therapists(),
            'errors' => [],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $appointment = $this->appointments->find($id);

        $this->appointments->update($appointment, AppointmentValidation::appointment($request));

        return redirect()->route('appointments.index')->with('success', 'Appointment updated successfully.');
    }

    public function delete(string $id)
    {
        $this->appointments->delete($this->appointments->find($id));

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted successfully.');
    }

    public function changeStatus(Request $request, string $id)
    {
        $appointment = $this->appointments->find($id);
        $status = $request->input('status');

        if (! AppointmentValidation::isValidStatus($status)) {
            return redirect()->route('appointments.index')->with('error', 'Invalid appointment status.');
        }

        $this->appointments->changeStatus($appointment, $status);

        return redirect()->route('appointments.index')->with('success', 'Appointment status updated.');
    }
}
