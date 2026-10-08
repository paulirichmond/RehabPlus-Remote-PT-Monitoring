<?php

namespace App\Controllers\Api;

use App\Models\PatientModel;
use App\Models\ExerciseRecordModel;
use CodeIgniter\RESTful\ResourceController;

class PatientApiController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model   = new PatientModel();
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = 10;
        $data    = $model->paginate($perPage, 'default', $page);

        return $this->respond([
            'status' => 'success',
            'data'   => $data,
            'pager'  => [
                'current_page' => $page,
                'per_page'     => $perPage,
                'total'        => $model->pager->getTotal(),
            ],
        ]);
    }

    public function show($id = null)
    {
        $patient = (new PatientModel())->find($id);
        if (! $patient) {
            return $this->failNotFound('Patient not found.');
        }

        $records = (new ExerciseRecordModel())
            ->where('patient_id', $id)
            ->orderBy('recorded_at', 'DESC')
            ->findAll();

        return $this->respond(['status' => 'success', 'data' => $patient, 'records' => $records]);
    }

    public function create()
    {
        $model = new PatientModel();
        $data  = $this->request->getJSON(true);

        if (! $model->insert($data)) {
            return $this->failValidationErrors($model->errors());
        }

        return $this->respondCreated(['status' => 'success', 'message' => 'Patient created.', 'id' => $model->getInsertID()]);
    }

    public function update($id = null)
    {
        $model = new PatientModel();
        if (! $model->update($id, $this->request->getJSON(true))) {
            return $this->failValidationErrors($model->errors());
        }
        return $this->respond(['status' => 'success', 'message' => 'Patient updated.']);
    }

    public function delete($id = null)
    {
        $model = new PatientModel();
        if (! $model->find($id)) {
            return $this->failNotFound('Patient not found.');
        }
        $model->delete($id);
        return $this->respondDeleted(['status' => 'success', 'message' => 'Patient deleted.']);
    }
}
