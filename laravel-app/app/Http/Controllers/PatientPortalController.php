<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\ExerciseRecord;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;

class PatientPortalController extends Controller
{
    public function index()
    {
        $patient = Patient::where('user_id', Auth::id())->first();

        if (! $patient) {
            return redirect()->route('login.page')->with('error', 'Patient profile not found.');
        }

        $records = ExerciseRecord::where('patient_id', $patient->id)->orderByDesc('recorded_at')->get();
        $appointments = Appointment::where('patient', $patient->name)->orderBy('date', 'asc')->orderBy('time', 'asc')->get();

        $prescribed = $records->sum('sets_prescribed');
        $completed = $records->sum('sets_completed');
        $compliance = $prescribed ? round(($completed / $prescribed) * 100, 1) : 0;
        $avgPain = $records->count() ? round($records->avg('pain_level'), 1) : 0;

        return view('patient_portal.index', compact('patient', 'records', 'appointments', 'compliance', 'avgPain'));
    }
}
