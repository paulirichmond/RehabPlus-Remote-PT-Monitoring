<?php

namespace App\Helpers;

use App\Services\Patient\ClinicalRecordService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClinicalValidation
{
    public static function assessment(Request $request): array
    {
        return $request->validate([
            'patient_uid' => 'required|string|max:128',
            'type' => 'required|string|max:60',
            'score' => 'required|integer|min:0|max:100',
            'date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);
    }

    public static function goal(Request $request): array
    {
        return $request->validate([
            'patient_uid' => 'required|string|max:128',
            'title' => 'required|string|max:150',
            'details' => 'nullable|string|max:1000',
            'target_date' => 'nullable|date',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);
    }

    public static function goalUpdate(Request $request): array
    {
        return $request->validate([
            'status' => ['required', Rule::in(array_keys(ClinicalRecordService::GOAL_STATUSES))],
            'progress' => 'required|integer|min:0|max:100',
        ]);
    }

    public static function note(Request $request): array
    {
        return $request->validate([
            'patient_uid' => 'required|string|max:128',
            'topic' => 'required|string|max:100',
            'text' => 'required|string|max:2000',
            'date' => 'required|date|before_or_equal:today',
        ]);
    }
}
