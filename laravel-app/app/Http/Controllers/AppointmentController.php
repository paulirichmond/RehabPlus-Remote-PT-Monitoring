<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(): View
    {
        $today = now()->toDateString();

        $appointments = Appointment::orderBy('date', 'asc')->orderBy('time', 'asc')->get();
        $todayAppointments = Appointment::where('date', $today)->count();
        $upcomingAppointments = Appointment::where('status', 'Upcoming')->count();
        $activePatients = Patient::count();
        $completedToday = Appointment::where('status', 'Completed')
            ->where(function ($query) use ($today) {
                $query->whereNotNull('completed_at')->whereDate('completed_at', $today)
                    ->orWhere(function ($q) use ($today) {
                        $q->whereNull('completed_at')->whereDate('date', $today);
                    });
            })
            ->count();

        return view('appointments.index', compact(
            'appointments',
            'todayAppointments',
            'upcomingAppointments',
            'activePatients',
            'completedToday'
        ));
    }

    public function create(): View
    {
        return view('appointments.create', [
            'appointment' => null,
            'therapists' => $this->getTherapists(),
            'errors' => [],
        ]);
    }

    public function show(int $id)
    {
        $appointment = Appointment::findOrFail($id);

        return view('appointments.show', ['appointment' => $appointment]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient' => 'required|min:2|max:100',
            'therapist' => 'required|min:2|max:100',
            'patient_condition' => 'required|min:2|max:150',
            'contact' => 'required|min:5|max:30',
            'date' => 'required|date',
            'time' => 'required',
            'session' => 'required|max:100',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $status = $validated['status'] ?? 'Upcoming';
        $validated['status'] = $status;
        $validated['completed_at'] = $status === 'Completed' ? now() : null;

        Appointment::create($validated);

        return redirect()->route('appointments.index')->with('success', 'Appointment scheduled successfully.');
    }

    public function edit(int $id)
    {
        $appointment = Appointment::findOrFail($id);

        return view('appointments.create', [
            'appointment' => $appointment,
            'therapists' => $this->getTherapists(),
            'errors' => [],
        ]);
    }

    public function update(Request $request, int $id)
    {
        $appointment = Appointment::findOrFail($id);

        $validated = $request->validate([
            'patient' => 'required|min:2|max:100',
            'therapist' => 'required|min:2|max:100',
            'patient_condition' => 'required|min:2|max:150',
            'contact' => 'required|min:5|max:30',
            'date' => 'required|date',
            'time' => 'required',
            'session' => 'required|max:100',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        $status = $validated['status'] ?? $appointment->status;
        $validated['status'] = $status;
        $validated['completed_at'] = $status === 'Completed'
            ? ($appointment->status === 'Completed' && ! empty($appointment->completed_at) ? $appointment->completed_at : now())
            : null;

        $appointment->update($validated);

        return redirect()->route('appointments.index')->with('success', 'Appointment updated successfully.');
    }

    public function delete(int $id)
    {
        $appointment = Appointment::findOrFail($id);
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted successfully.');
    }

    public function changeStatus(Request $request, int $id)
    {
        $appointment = Appointment::findOrFail($id);
        $status = $request->input('status');
        $allowed = ['Upcoming', 'Completed', 'Cancelled'];

        if (! in_array($status, $allowed, true)) {
            return redirect()->route('appointments.index')->with('error', 'Invalid appointment status.');
        }

        $appointment->update([
            'status' => $status,
            'completed_at' => $status === 'Completed' ? now() : null,
        ]);

        return redirect()->route('appointments.index')->with('success', 'Appointment status updated.');
    }

    private function getTherapists(): array
    {
        return User::query()->where('role', 'therapist')->where('is_active', 1)->orderBy('name', 'asc')->get()->toArray();
    }
}
