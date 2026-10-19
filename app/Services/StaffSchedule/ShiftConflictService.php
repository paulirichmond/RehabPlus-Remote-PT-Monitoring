<?php

namespace App\Services\StaffSchedule;

class ShiftConflictService
{
    /**
     * Parse a shift like "08:00 - 15:00" into [start, end] timestamps, or [0, 0] when it has no time range.
     */
    public function parseShiftRange(string $shift): array
    {
        $clean = trim(preg_replace('/\s+/', ' ', (string) $shift));

        if (preg_match('/(\d{1,2}:\d{2})\s*(?:-|–|to|until)\s*(\d{1,2}:\d{2})/i', $clean, $matches) === 1) {
            $start = strtotime('1970-01-01 ' . $matches[1]);
            $end = strtotime('1970-01-01 ' . $matches[2]);

            return [$start, $end];
        }

        return [0, 0];
    }

    public function shiftTimesOverlap(array $first, array $second): bool
    {
        return $first[0] < $second[1] && $second[0] < $first[1];
    }

    /**
     * True when the candidate overlaps a shift for the same staff member or in the same room on that day.
     */
    public function hasScheduleConflict(array $schedules, array $candidate): bool
    {
        $candidateStaff = strtolower(trim((string) ($candidate['staff'] ?? '')));
        $candidateRoom = strtolower(trim((string) ($candidate['room'] ?? 'General Room')));
        $candidateDay = trim((string) ($candidate['day'] ?? ''));
        $candidateRange = $this->parseShiftRange((string) ($candidate['shift'] ?? ''));

        if ($candidateRange[0] === 0 && $candidateRange[1] === 0) {
            return false;
        }

        foreach ($schedules as $existing) {
            $existingDay = trim((string) ($existing['day'] ?? ''));
            if ($existingDay !== $candidateDay) {
                continue;
            }

            $existingRange = $this->parseShiftRange((string) ($existing['shift'] ?? ''));
            if ($existingRange[0] === 0 && $existingRange[1] === 0) {
                continue;
            }

            $existingStaff = strtolower(trim((string) ($existing['staff'] ?? '')));
            $existingRoom = strtolower(trim((string) ($existing['room'] ?? 'General Room')));

            if ($existingStaff === $candidateStaff && $this->shiftTimesOverlap($candidateRange, $existingRange)) {
                return true;
            }

            if ($existingRoom === $candidateRoom && $this->shiftTimesOverlap($candidateRange, $existingRange)) {
                return true;
            }
        }

        return false;
    }
}
