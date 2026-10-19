<?php

namespace App\Helpers;

use App\Services\Patient\ExerciseCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrescriptionValidation
{
    public static function prescription(Request $request): array
    {
        return $request->validate([
            'exercise_type' => ['required', Rule::in(ExerciseCatalog::codes())],
            'target_reps' => 'required|integer|min:1|max:100',
            'hold_seconds' => 'nullable|integer|min:1|max:300',
            'frequency_per_week' => 'required|integer|min:1|max:14',
            'days' => 'nullable|array',
            'days.*' => 'integer|between:1,7',
            // Both or neither: an hour window, end exclusive (0-24).
            'start_hour' => 'nullable|required_with:end_hour|integer|between:0,23',
            'end_hour' => 'nullable|required_with:start_hour|integer|between:1,24|gt:start_hour',
            'notes' => 'nullable|string|max:500',
        ]);
    }
}
