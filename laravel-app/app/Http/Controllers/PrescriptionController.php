<?php

namespace App\Http\Controllers;

use App\Helpers\PrescriptionValidation;
use App\Services\Patient\ExerciseCatalog;
use App\Services\Patient\PatientService;
use App\Services\Patient\PrescriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Assigning exercises to a patient (patients/{uid}/prescriptions). */
class PrescriptionController extends Controller
{
    public function __construct(private PatientService $patients, private PrescriptionService $prescriptions)
    {
    }

    public function create(Request $request, string $uid): View
    {
        return view('prescriptions.form', [
            'patient' => $this->patient($request, $uid),
            'prescription' => null,
            'catalog' => ExerciseCatalog::TYPES,
        ]);
    }

    public function store(Request $request, string $uid): RedirectResponse
    {
        $patient = $this->patient($request, $uid);
        $this->prescriptions->create($request->user(), $patient, PrescriptionValidation::prescription($request));

        return redirect()->route('patients.show', $uid)->with('success', 'Exercise assigned. It now shows in the patient\'s app.');
    }

    public function edit(Request $request, string $uid, string $id): View
    {
        $patient = $this->patient($request, $uid);

        return view('prescriptions.form', [
            'patient' => $patient,
            'prescription' => $this->prescription($uid, $id),
            'catalog' => ExerciseCatalog::TYPES,
        ]);
    }

    public function update(Request $request, string $uid, string $id): RedirectResponse
    {
        $patient = $this->patient($request, $uid);
        $this->prescriptions->update($patient, $this->prescription($uid, $id), PrescriptionValidation::prescription($request));

        return redirect()->route('patients.show', $uid)->with('success', 'Exercise updated.');
    }

    /** Stop (active=false) or resume an exercise. */
    public function toggle(Request $request, string $uid, string $id): RedirectResponse
    {
        $patient = $this->patient($request, $uid);
        $active = $request->boolean('active');
        $this->prescriptions->setActive($patient, $this->prescription($uid, $id), $active);

        return redirect()->route('patients.show', $uid)->with('success', $active ? 'Exercise resumed.' : 'Exercise stopped. It no longer shows in the app; past sessions are kept.');
    }

    private function patient(Request $request, string $uid): array
    {
        $patient = $this->patients->find($request->user(), $uid);
        abort_unless($patient, 404);
        abort_unless($this->patients->canManage($request->user(), $patient), 403);

        return $patient;
    }

    private function prescription(string $uid, string $id): array
    {
        $prescription = $this->prescriptions->find($uid, $id);
        abort_unless($prescription, 404);

        return $prescription;
    }
}
