<?php

namespace App\Http\Controllers;

use App\Models\ExerciseRecord;
use App\Models\Patient;
use Illuminate\Http\Request;

class ExerciseController extends Controller
{
    public function create(int $patientId)
    {
        $patient = Patient::findOrFail($patientId);

        return view('exercises.form', [
            'record' => null,
            'patient' => $patient,
            'patientId' => $patientId,
        ]);
    }

    public function store(Request $request, int $patientId)
    {
        $validated = $request->validate([
            'exercise_name' => 'required|max:150',
            'sets_prescribed' => 'required|integer|min:1',
            'sets_completed' => 'required|integer|min:0',
            'pain_level' => 'required|integer|min:0|max:10',
            'recorded_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $validated['patient_id'] = $patientId;
        ExerciseRecord::create($validated);

        return redirect()->route('patients.show', $patientId)->with('success', 'Record added.');
    }

    public function edit(int $patientId, int $id)
    {
        $record = ExerciseRecord::findOrFail($id);
        $patient = Patient::findOrFail($patientId);

        return view('exercises.form', [
            'record' => $record,
            'patient' => $patient,
            'patientId' => $patientId,
        ]);
    }

    public function update(Request $request, int $patientId, int $id)
    {
        $record = ExerciseRecord::findOrFail($id);

        $validated = $request->validate([
            'exercise_name' => 'required|max:150',
            'sets_prescribed' => 'required|integer|min:1',
            'sets_completed' => 'required|integer|min:0',
            'pain_level' => 'required|integer|min:0|max:10',
            'recorded_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $record->update($validated);

        return redirect()->route('patients.show', $patientId)->with('success', 'Record updated.');
    }

    public function delete(int $patientId, int $id)
    {
        $record = ExerciseRecord::findOrFail($id);
        $record->delete();

        return redirect()->route('patients.show', $patientId)->with('success', 'Record deleted.');
    }
}
