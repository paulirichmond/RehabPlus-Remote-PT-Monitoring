<?php

namespace App\Controllers;

use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;

class ExerciseController extends BaseController
{
    protected ExerciseRecordModel $records;
    protected PatientModel $patients;

    public function __construct()
    {
        $this->records  = new ExerciseRecordModel();
        $this->patients = new PatientModel();
    }

    public function create(int $patientId): string
    {
        $patient = $this->patients->find($patientId);
        if ($patient === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Patient not found.');
        }

        return view('exercises/form', [
            'record'    => null,
            'patient'   => $patient,
            'patientId' => $patientId,
        ]);
    }

    public function store(int $patientId)
    {
        $rules = [
            'exercise_name'   => 'required|max_length[150]',
            'sets_prescribed' => 'required|integer|greater_than[0]',
            'sets_completed'  => 'required|integer|greater_than_equal_to[0]',
            'pain_level'      => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
            'recorded_at'     => 'required|valid_date',
        ];
        if (!$this->validate($rules)) {
            return view('exercises/form', [
                'record'    => null,
                'patient'   => $this->patients->find($patientId),
                'patientId' => $patientId,
                'errors'    => $this->validator->getErrors(),
            ]);
        }
        $data = $this->request->getPost(['exercise_name', 'sets_prescribed', 'sets_completed', 'pain_level', 'notes', 'recorded_at']);
        $data['patient_id'] = $patientId;
        $this->records->insert($data);
        return redirect()->to("/patients/{$patientId}")->with('success', 'Record added.');
    }

    public function edit(int $patientId, int $id): string
    {
        $record = $this->records->find($id);
        if ($record === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Exercise record not found.');
        }

        $patient = $this->patients->find($patientId);
        if ($patient === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Patient not found.');
        }

        return view('exercises/form', [
            'record'    => $record,
            'patient'   => $patient,
            'patientId' => $patientId,
        ]);
    }

    public function update(int $patientId, int $id)
    {
        $rules = [
            'exercise_name'   => 'required|max_length[150]',
            'sets_prescribed' => 'required|integer|greater_than[0]',
            'sets_completed'  => 'required|integer|greater_than_equal_to[0]',
            'pain_level'      => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10]',
            'recorded_at'     => 'required|valid_date',
        ];
        if (!$this->validate($rules)) {
            return view('exercises/form', [
                'record'    => $this->records->find($id),
                'patient'   => $this->patients->find($patientId),
                'patientId' => $patientId,
                'errors'    => $this->validator->getErrors(),
            ]);
        }
        $this->records->update($id, $this->request->getPost(['exercise_name', 'sets_prescribed', 'sets_completed', 'pain_level', 'notes', 'recorded_at']));
        return redirect()->to("/patients/{$patientId}")->with('success', 'Record updated.');
    }

    public function delete(int $patientId, int $id)
    {
        $this->records->delete($id);
        return redirect()->to("/patients/{$patientId}")->with('success', 'Record deleted.');
    }
}
