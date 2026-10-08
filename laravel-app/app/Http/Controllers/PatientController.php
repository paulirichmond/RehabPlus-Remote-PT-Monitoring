<?php

namespace App\Http\Controllers;

use App\Models\ExerciseRecord;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $condition = trim((string) $request->query('condition', ''));

        $query = Patient::query()->orderBy('name', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('condition', 'like', "%{$search}%");
            });
        }

        if ($condition !== '') {
            $query->where('condition', $condition);
        }

        $patients = $query->get();
        $conditions = Patient::query()->select('condition')->distinct()->orderBy('condition', 'asc')->pluck('condition');

        return view('patients.index', [
            'data' => $patients,
            'conditions' => $conditions,
            'search' => $search,
            'selectedCondition' => $condition,
        ]);
    }

    public function show(int $id)
    {
        $patient = Patient::findOrFail($id);
        $records = ExerciseRecord::where('patient_id', $id)->orderByDesc('recorded_at')->get();

        $prescribed = $records->sum('sets_prescribed');
        $completed = $records->sum('sets_completed');
        $compliance = $prescribed ? round(($completed / $prescribed) * 100, 1) : 0;
        $avgPain = $records->count() ? round($records->avg('pain_level'), 1) : 0;

        $trend = ExerciseRecord::where('patient_id', $id)
            ->selectRaw('DATE(recorded_at) as day, ROUND(AVG(pain_level), 1) as avg_pain, ROUND(SUM(sets_completed) / NULLIF(SUM(sets_prescribed), 0) * 100, 1) as compliance_rate')
            ->groupBy('day')
            ->orderBy('day', 'desc')
            ->limit(14)
            ->get()
            ->toArray();

        $trend = array_reverse($trend);

        return view('patients.show', [
            'patient' => $patient,
            'records' => $records,
            'compliance' => $compliance,
            'avgPain' => $avgPain,
            'trend' => $trend,
        ]);
    }

    public function create(): View
    {
        return view('patients.form', ['patient' => null, 'errors' => []]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|min:2|max:100',
            'condition' => 'required|min:2|max:150',
            'medical_summary' => 'nullable|max:1000',
            'therapy_plan' => 'nullable|max:1000',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        Patient::create($validated);

        return redirect()->route('patients.index')->with('success', 'Patient added successfully.');
    }

    public function edit(int $id)
    {
        $patient = Patient::findOrFail($id);

        return view('patients.form', ['patient' => $patient, 'errors' => []]);
    }

    public function update(Request $request, int $id)
    {
        $patient = Patient::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|min:2|max:100',
            'condition' => 'required|min:2|max:150',
            'medical_summary' => 'nullable|max:1000',
            'therapy_plan' => 'nullable|max:1000',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($patient->avatar && Storage::disk('public')->exists($patient->avatar)) {
                Storage::disk('public')->delete($patient->avatar);
            }

            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $patient->update($validated);

        return redirect()->route('patients.index')->with('success', 'Patient updated successfully.');
    }

    public function delete(int $id)
    {
        $patient = Patient::findOrFail($id);
        $patient->delete();

        return redirect()->route('patients.index')->with('success', 'Patient deleted.');
    }
}
