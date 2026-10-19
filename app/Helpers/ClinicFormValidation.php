<?php

namespace App\Helpers;

use CodeIgniter\HTTP\IncomingRequest;

/**
 * Required-field checks for the session-based clinic forms
 * (billing, inventory, staff schedule, time off, therapy notes, assessments).
 *
 * Each method returns ['data' => trimmed input, 'error' => message or null].
 */
class ClinicFormValidation
{
    public static function billing(IncomingRequest $request): array
    {
        return self::check($request, ['patient', 'session', 'fee', 'status', 'date'], ['patient', 'session', 'fee', 'date'],
            'Patient, session, fee, and date are required.');
    }

    public static function inventory(IncomingRequest $request): array
    {
        return self::check($request, ['item', 'category', 'stock', 'reorder', 'status'], ['item', 'category', 'stock'],
            'Item, category, and stock level are required.');
    }

    public static function shift(IncomingRequest $request): array
    {
        return self::check($request, ['staff', 'role', 'day', 'shift', 'room', 'date'], ['staff', 'role', 'day', 'shift'],
            'Staff name, role, day, and shift are required.');
    }

    public static function timeOff(IncomingRequest $request): array
    {
        return self::check($request, ['staff', 'date', 'reason', 'time_off_type', 'time'], ['staff', 'date', 'reason'],
            'Staff member, date, and reason are required for time off.');
    }

    public static function therapyNote(IncomingRequest $request): array
    {
        $fields = ['patient', 'therapist', 'topic', 'note', 'date'];

        return self::check($request, $fields, $fields, 'Patient, therapist, topic, note, and date are required.');
    }

    public static function assessment(IncomingRequest $request): array
    {
        $fields = ['patient', 'therapist', 'type', 'score', 'goal', 'date'];

        return self::check($request, $fields, $fields, 'All assessment fields are required.');
    }

    private static function check(IncomingRequest $request, array $fields, array $required, string $message): array
    {
        $data = FormInput::trimmed($request, $fields);

        return [
            'data' => $data,
            'error' => FormInput::missing($data, $required) ? $message : null,
        ];
    }
}
