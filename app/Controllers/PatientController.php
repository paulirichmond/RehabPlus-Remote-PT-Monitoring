<?php

namespace App\Controllers;

use App\Helpers\PatientValidation;
use App\Services\Patient\PatientService;
use CodeIgniter\Exceptions\PageNotFoundException;

class PatientController extends BaseController
{
    protected PatientService $patients;

    public function __construct()
    {
        $this->patients = new PatientService();
    }

    /**
     * Display all patients
     */
    public function index()
    {
        return view('patients/index', $this->patients->search(
            trim((string) $this->request->getGet('search')),
            trim((string) $this->request->getGet('condition'))
        ));
    }

    /**
     * Display one patient
     */
    public function show(int $id)
    {
        return view('patients/show', $this->patients->profile($this->findOrFail($id)));
    }

    /**
     * Show Add Patient form
     */
    public function create()
    {
        return view('patients/form', [
            'patient' => null,
            'errors'  => []
        ]);
    }

    /**
     * Save new patient
     */
    public function store()
    {
        if (! $this->validate(PatientValidation::RULES)) {
            return view('patients/form', [
                'patient' => null,
                'errors'  => $this->validator->getErrors()
            ]);
        }

        $this->patients->create($this->request->getPost(PatientValidation::FIELDS), $this->request->getFile('avatar'));

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient added successfully.');
    }

    /**
     * Show Edit Patient form
     */
    public function edit(int $id)
    {
        return view('patients/form', [
            'patient' => $this->findOrFail($id),
            'errors'  => []
        ]);
    }

    /**
     * Update patient
     */
    public function update(int $id)
    {
        $patient = $this->findOrFail($id);

        if (! $this->validate(PatientValidation::RULES)) {
            return view('patients/form', [
                'patient' => $patient,
                'errors'  => $this->validator->getErrors()
            ]);
        }

        $this->patients->update($id, $this->request->getPost(PatientValidation::FIELDS), $this->request->getFile('avatar'));

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient updated successfully.');
    }

    /**
     * Delete patient
     */
    public function delete(int $id)
    {
        if (! $this->patients->delete($id)) {
            return redirect()
                ->to(site_url('patients'))
                ->with('error', 'Patient not found.');
        }

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient deleted successfully.');
    }

    private function findOrFail(int $id): array
    {
        $patient = $this->patients->find($id);
        if ($patient === null) {
            throw new PageNotFoundException('Patient not found.');
        }

        return $patient;
    }
}
