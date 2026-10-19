<?php

namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;
use App\Services\Patient\PatientService;

class PatientPortalController extends BaseController
{
    public function index()
    {
        $patient = (new PatientModel())
            ->where('user_id', session()->get('user_id'))
            ->first();

        if ($patient === null) {
            return redirect()->to(site_url('login'))
                ->with('error', 'Patient profile not found.');
        }

        $records = (new ExerciseRecordModel())
            ->where('patient_id', $patient['id'])
            ->orderBy('recorded_at', 'DESC')
            ->findAll();

        $appointments = (new AppointmentModel())
            ->where('patient', $patient['name'])
            ->orderBy('date', 'ASC')
            ->orderBy('time', 'ASC')
            ->findAll();

        return view('patient_portal/index', compact('patient', 'records', 'appointments')
            + (new PatientService())->progress($records));
    }
}
