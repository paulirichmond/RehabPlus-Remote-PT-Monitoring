<?php

namespace App\Controllers;

use App\Models\PatientModel;
use App\Models\ExerciseRecordModel;

class PatientController extends BaseController
{
    protected PatientModel $patients;
    protected ExerciseRecordModel $records;

    public function __construct()
    {
        $this->patients = new PatientModel();
        $this->records = new ExerciseRecordModel();
    }

    /**
     * Display all patients
     */
    public function index()
    {
        $search = trim((string) $this->request->getGet('search'));
        $condition = trim((string) $this->request->getGet('condition'));
        $query = $this->patients->orderBy('name', 'ASC');

        if ($search !== '') {
            $query->groupStart()
                ->like('name', $search)
                ->orLike('condition', $search)
                ->groupEnd();
        }

        if ($condition !== '') {
            $query->where('condition', $condition);
        }

        $data = $query->findAll();
        $conditions = $this->patients
            ->select('condition')
            ->distinct()
            ->orderBy('condition', 'ASC')
            ->findColumn('condition');

        return view('patients/index', [
            'data' => $data,
            'conditions' => $conditions,
            'search' => $search,
            'selectedCondition' => $condition,
        ]);
    }

    /**
     * Display one patient
     */
    public function show(int $id)
    {
        $patient = $this->patients->find($id);
        if ($patient === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Patient not found.');
        }

        $records = $this->records
            ->where('patient_id', $id)
            ->orderBy('recorded_at', 'DESC')
            ->findAll();

        $compliance = 0;
        $avgPain = 0;

        if (!empty($records)) {

            $prescribed = array_sum(
                array_column($records, 'sets_prescribed')
            );

            $completed = array_sum(
                array_column($records, 'sets_completed')
            );

            $compliance = $prescribed
                ? round(($completed / $prescribed) * 100, 1)
                : 0;

            $avgPain = round(
                array_sum(array_column($records, 'pain_level'))
                / count($records),
                1
            );
        }

        $trend = $this->records->db->query(
            "
            SELECT
                DATE(recorded_at) AS day,
                ROUND(AVG(pain_level), 1) AS avg_pain,
                ROUND(SUM(sets_completed) / NULLIF(SUM(sets_prescribed), 0) * 100, 1) AS compliance_rate
            FROM exercise_records
            WHERE patient_id = ?
            GROUP BY day
            ORDER BY day DESC
            LIMIT 14
            ",
            [$id]
        )->getResultArray();

        $trend = array_reverse($trend);

        return view('patients/show', [
            'patient'    => $patient,
            'records'    => $records,
            'compliance' => $compliance,
            'avgPain'    => $avgPain,
            'trend'      => $trend
        ]);
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
        $rules = [
            'name'      => 'required|min_length[2]|max_length[100]',
            'condition' => 'required|min_length[2]|max_length[150]',
            'medical_summary' => 'permit_empty|max_length[1000]',
            'therapy_plan' => 'permit_empty|max_length[1000]',
            'avatar'    => 'if_exist|is_image[avatar]|max_size[avatar,2048]',
        ];

        if (!$this->validate($rules)) {
            return view('patients/form', [
                'patient' => null,
                'errors'  => $this->validator->getErrors()
            ]);
        }

        $data = [
            'name'      => $this->request->getPost('name'),
            'condition' => $this->request->getPost('condition'),
            'medical_summary' => $this->request->getPost('medical_summary'),
            'therapy_plan' => $this->request->getPost('therapy_plan'),
        ];

        /*
         * Handle profile picture
         */
        $file = $this->request->getFile('avatar');

        if ($file && $file->isValid() && !$file->hasMoved()) {

            $uploadPath = FCPATH . 'uploads/avatars';

            /*
             * Create upload directory if it doesn't exist
             */
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $newName = $file->getRandomName();

            $file->move($uploadPath, $newName);

            /*
             * Resize image
             */
            try {
                \Config\Services::image()
                    ->withFile($uploadPath . '/' . $newName)
                    ->resize(200, 200, true)
                    ->save($uploadPath . '/' . $newName);

                $data['avatar'] = $newName;

            } catch (\Throwable $e) {
                // Keep the uploaded image even if resizing fails
                $data['avatar'] = $newName;
            }
        }

        /*
         * Insert patient into database
         */
        $this->patients->insert($data);

        /*
         * Clear dashboard patient statistics cache
         */
        \Config\Services::cache()->delete('patient_stats');

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient added successfully.');
    }

    /**
     * Show Edit Patient form
     */
    public function edit(int $id)
    {
        $patient = $this->patients->find($id);
        if ($patient === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Patient not found.');
        }

        return view('patients/form', [
            'patient' => $patient,
            'errors'  => []
        ]);
    }

    /**
     * Update patient
     */
    public function update(int $id)
    {
        $patient = $this->patients->find($id);
        if ($patient === null) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Patient not found.');
        }

        $rules = [
            'name'      => 'required|min_length[2]|max_length[100]',
            'condition' => 'required|min_length[2]|max_length[150]',
            'medical_summary' => 'permit_empty|max_length[1000]',
            'therapy_plan' => 'permit_empty|max_length[1000]',
            'avatar'    => 'if_exist|is_image[avatar]|max_size[avatar,2048]',
        ];

        if (!$this->validate($rules)) {
            return view('patients/form', [
                'patient' => $patient,
                'errors'  => $this->validator->getErrors()
            ]);
        }

        $data = [
            'name'      => $this->request->getPost('name'),
            'condition' => $this->request->getPost('condition'),
            'medical_summary' => $this->request->getPost('medical_summary'),
            'therapy_plan' => $this->request->getPost('therapy_plan'),
        ];

        /*
         * Handle new profile picture
         */
        $file = $this->request->getFile('avatar');

        if ($file && $file->isValid() && !$file->hasMoved()) {

            $uploadPath = FCPATH . 'uploads/avatars';

            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0777, true);
            }

            $newName = $file->getRandomName();

            $file->move($uploadPath, $newName);

            try {
                \Config\Services::image()
                    ->withFile($uploadPath . '/' . $newName)
                    ->resize(200, 200, true)
                    ->save($uploadPath . '/' . $newName);

                $data['avatar'] = $newName;

            } catch (\Throwable $e) {
                $data['avatar'] = $newName;
            }
        }

        /*
         * Update database
         */
        $this->patients->update($id, $data);

        /*
         * Clear dashboard cache
         */
        \Config\Services::cache()->delete('patient_stats');

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient updated successfully.');
    }

    /**
     * Delete patient
     */
    public function delete(int $id)
    {
        $patient = $this->patients->find($id);

        if (!$patient) {
            return redirect()
                ->to(site_url('patients'))
                ->with('error', 'Patient not found.');
        }

        $this->patients->delete($id);

        /*
         * Clear dashboard cache
         */
        \Config\Services::cache()->delete('patient_stats');

        return redirect()
            ->to(site_url('patients'))
            ->with('success', 'Patient deleted successfully.');
    }
}