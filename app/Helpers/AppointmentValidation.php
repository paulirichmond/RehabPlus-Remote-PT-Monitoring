<?php

namespace App\Helpers;

class AppointmentValidation
{
    public const RULES = [
        'patient'           => 'required|min_length[2]|max_length[100]',
        'therapist'         => 'required|min_length[2]|max_length[100]',
        'patient_condition' => 'required|min_length[2]|max_length[150]',
        'contact'           => 'required|min_length[5]|max_length[30]',
        'date'              => 'required|valid_date',
        'time'              => 'required',
        'session'           => 'required|max_length[100]',
    ];

    public const FIELDS = ['patient', 'therapist', 'patient_condition', 'contact', 'date', 'time', 'session', 'notes'];

    public const STATUSES = ['Upcoming', 'Completed', 'Cancelled'];

    public static function isValidStatus(mixed $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }
}
