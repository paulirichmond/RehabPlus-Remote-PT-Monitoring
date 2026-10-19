<?php

namespace App\Services\StaffSchedule;

use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;

class ShiftConflictService
{
    public function __construct(private ClinicStore $store)
    {
    }

    /**
     * True when the shift overlaps another shift for the same staff member or in the same room on that day.
     */
    public function hasConflict(array $candidate): bool
    {
        $range = $this->shiftRange($candidate['shift']);
        if (! $range) {
            return false;
        }

        return $this->store->all(ClinicStore::SHIFTS)->where('day', $candidate['day'])->contains(function (ClinicRecord $existing) use ($candidate, $range) {
            $existingRange = $this->shiftRange((string) $existing->shift);
            $sameStaff = (string) $existing->staff === $candidate['staff'];
            $sameRoom = $this->normalize($existing->room) === $this->normalize($candidate['room']);

            return $existingRange && ($sameStaff || $sameRoom) && $this->overlaps($range, $existingRange);
        });
    }

    /**
     * Parse a shift like "08:00 - 15:00" into [start, end] minutes, or null when it has no time range.
     */
    public function shiftRange(string $shift): ?array
    {
        if (preg_match('/(\d{1,2}):(\d{2})\s*(?:-|–|to|until)\s*(\d{1,2}):(\d{2})/i', $shift, $m) !== 1) {
            return null;
        }

        return [(int) $m[1] * 60 + (int) $m[2], (int) $m[3] * 60 + (int) $m[4]];
    }

    public function overlaps(array $first, array $second): bool
    {
        return $first[0] < $second[1] && $second[0] < $first[1];
    }

    public function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
