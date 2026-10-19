<?php

namespace App\Services\Assessment;

class AssessmentService
{
    public function overview(): array
    {
        $assessments = session()->get('patient_assessments') ?? [];

        return [
            'assessments' => $assessments,
            'averageScore' => '0%',
            'goalTracking' => 0,
            'activePatients' => count($assessments),
        ];
    }

    public function add(array $input): void
    {
        $assessments = session()->get('patient_assessments') ?? [];
        $assessments[] = [
            'patient' => $input['patient'],
            'therapist' => $input['therapist'],
            'type' => $input['type'],
            'score' => $input['score'],
            'goal' => $input['goal'],
            'date' => $input['date'],
        ];
        session()->set('patient_assessments', $assessments);
    }
}
