<?php

namespace App\Helpers;

use Illuminate\Http\Request;

class StaffScheduleValidation
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    public static function shift(Request $request): array
    {
        $validated = $request->validate([
            'staff' => ['required', 'string', 'max:150'],
            'role' => ['required', 'string', 'max:100'],
            'day' => ['required', 'in:' . implode(',', self::DAYS)],
            'shift' => ['required', 'string', 'max:50'],
            'room' => ['nullable', 'string', 'max:100'],
        ]);
        $validated['room'] = trim((string) ($validated['room'] ?? '')) ?: 'General Room';

        return $validated;
    }

    public static function timeOff(Request $request): array
    {
        $validated = $request->validate([
            'staff' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'time_off_type' => ['nullable', 'in:Full day,Specific time'],
            'time' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        return [
            'staff' => $validated['staff'],
            'date' => $validated['date'],
            'type' => $validated['time_off_type'] ?? 'Full day',
            'time' => trim((string) ($validated['time'] ?? '')) ?: 'Full day',
            'reason' => $validated['reason'],
        ];
    }
}
