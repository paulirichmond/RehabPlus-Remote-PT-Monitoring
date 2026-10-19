<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class AppointmentValidation
{
    public const STATUSES = ['Upcoming', 'Completed', 'Cancelled'];

    public static function appointment(Request $request): array
    {
        return $request->validate([
            'patient' => 'required|min:2|max:100',
            'therapist' => 'required|min:2|max:100',
            'patient_condition' => 'required|min:2|max:150',
            'contact' => 'required|min:5|max:30',
            'date' => 'required|date',
            'time' => 'required',
            'session' => 'required|max:100',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ]);
    }

    public static function isValidStatus(mixed $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
