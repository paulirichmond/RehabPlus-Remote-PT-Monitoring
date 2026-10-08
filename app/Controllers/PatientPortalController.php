<?php

namespace App\Controllers;

use App\Models\AppointmentModel;
use App\Models\ExerciseRecordModel;
use App\Models\PatientModel;

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

        $prescribed = array_sum(array_column($records, 'sets_prescribed'));
        $completed = array_sum(array_column($records, 'sets_completed'));
        $compliance = $prescribed ? round(($completed / $prescribed) * 100, 1) : 0;
        $avgPain = $records ? round(array_sum(array_column($records, 'pain_level')) / count($records), 1) : 0;

        return view('patient_portal/index', compact(
            'patient',
            'records',
            'appointments',
            'compliance',
            'avgPain'
        ));
    }
}