<?php

namespace App\Services\StaffSchedule;

use App\Models\UserModel;

class StaffScheduleService
{
    private ShiftConflictService $shifts;

    public function __construct()
    {
        $this->shifts = new ShiftConflictService();
    }

    public function schedules(): array
    {
        return session()->get('staff_schedule') ?? [];
    }

    public function timeOffEntries(): array
    {
        return session()->get('staff_time_off') ?? [];
    }

    public function overview(int $weekOffset): array
    {
        $weekOffset = max(-12, min(12, $weekOffset));
        $weekDays = $this->getWeekDays($weekOffset);
        $schedules = $this->schedules();
        $timeOffEntries = $this->timeOffEntries();
        $staffDirectory = $this->getStaffDirectory();

        $todayLabel = date('l');
        $todayDate = date('Y-m-d');
        $todayEntries = array_values(array_filter($schedules, static fn (array $entry): bool => (string) ($entry['day'] ?? '') === $todayLabel));

        $todayStaffList = [];
        $staffAvailability = [];
        foreach ($staffDirectory as $staffName => $role) {
            $status = $this->getStaffAvailability($staffName, $todayDate, $schedules, $timeOffEntries);
            $staffAvailability[$staffName] = $status;
            $dayEntry = null;
            foreach ($todayEntries as $entry) {
                if (strtolower(trim((string) ($entry['staff'] ?? ''))) === strtolower($staffName)) {
                    $dayEntry = $entry;
                    break;
                }
            }

            $todayStaffList[] = [
                'name' => $staffName,
                'role' => $role,
                'shift' => $dayEntry['shift'] ?? 'Off',
                'room' => $dayEntry['room'] ?? 'Not assigned',
                'status' => $status,
            ];
        }

        $weeklySchedule = [];
        foreach ($staffDirectory as $staffName => $role) {
            foreach ($weekDays as $day) {
                $matches = array_values(array_filter($schedules, static fn (array $entry): bool => strtolower(trim((string) ($entry['staff'] ?? ''))) === strtolower($staffName) && (string) ($entry['day'] ?? '') === $day['label']));
                if ($matches !== []) {
                    $weeklySchedule[$staffName][$day['label']] = [
                        'shift' => $matches[0]['shift'] ?? 'Off',
                        'room' => $matches[0]['room'] ?? 'General Room',
                    ];
                }
            }
        }

        $staffOnDuty = count(array_unique(array_map(static fn (array $entry): string => trim((string) ($entry['staff'] ?? '')), $todayEntries)));
        $activeRooms = count(array_unique(array_filter(array_map(static fn (array $entry): string => trim((string) ($entry['room'] ?? '')), $todayEntries))));

        return [
            'staffDirectory' => array_map(static fn (string $name, string $role): array => ['name' => $name, 'role' => $role], array_keys($staffDirectory), array_values($staffDirectory)),
            'schedules' => $schedules,
            'staffCount' => $staffOnDuty,
            'todayShifts' => count($todayEntries),
            'todayStaffList' => $todayStaffList,
            'activeRooms' => $activeRooms,
            'weekDays' => $weekDays,
            'weekOffset' => $weekOffset,
            'weeklySchedule' => $weeklySchedule,
            'timeOffEntries' => $timeOffEntries,
            'staffAvailability' => $staffAvailability,
        ];
    }

    /**
     * Save a shift. Returns false when it conflicts with an existing shift.
     */
    public function addShift(array $input): bool
    {
        $entry = [
            'staff' => $input['staff'],
            'role' => $input['role'],
            'day' => $input['day'],
            'date' => $input['date'] !== '' ? $input['date'] : $this->getNowForDay($input['day']),
            'shift' => $input['shift'],
            'room' => $input['room'] !== '' ? $input['room'] : 'General Room',
        ];

        $schedules = $this->schedules();
        if ($this->shifts->hasScheduleConflict($schedules, $entry)) {
            return false;
        }

        $schedules[] = $entry;
        session()->set('staff_schedule', $schedules);

        return true;
    }

    public function addTimeOff(array $input): void
    {
        $timeOffEntries = $this->timeOffEntries();
        $timeOffEntries[] = [
            'staff' => $input['staff'],
            'date' => $input['date'],
            'reason' => $input['reason'],
            'type' => $input['time_off_type'] !== '' ? $input['time_off_type'] : 'Full day',
            'time' => $input['time'] !== '' ? $input['time'] : 'Full day',
        ];
        session()->set('staff_time_off', $timeOffEntries);
    }

    private function getWeekDays(int $weekOffset = 0): array
    {
        $monday = new \DateTimeImmutable('monday this week');
        $monday = $monday->modify("+{$weekOffset} week");

        $days = [];
        foreach (range(0, 6) as $offset) {
            $date = $monday->modify("+{$offset} day");
            $days[] = [
                'label' => $date->format('l'),
                'date' => $date->format('Y-m-d'),
                'short' => $date->format('M j'),
            ];
        }

        return $days;
    }

    private function getNowForDay(string $dayName): string
    {
        $date = new \DateTimeImmutable('today');

        while ($date->format('l') !== $dayName) {
            $date = $date->modify('+1 day');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Staff, manager and superadmin accounts, plus anyone already on the schedule.
     */
    private function getStaffDirectory(): array
    {
        $users = (new UserModel())
            ->whereIn('role', ['staff', 'manager', 'superadmin'])
            ->orderBy('name', 'ASC')
            ->findAll();

        $staff = [];
        foreach ($users as $user) {
            $name = trim((string) ($user['name'] ?? ''));
            if ($name !== '') {
                $staff[$name] = trim((string) ($user['role'] ?? 'staff'));
            }
        }

        foreach ($this->schedules() as $entry) {
            $name = trim((string) ($entry['staff'] ?? ''));
            if ($name !== '' && ! isset($staff[$name])) {
                $staff[$name] = trim((string) ($entry['role'] ?? 'staff'));
            }
        }

        return $staff;
    }

    private function getStaffAvailability(string $staffName, string $date, array $schedules, array $timeOffEntries): string
    {
        $normalized = strtolower(trim($staffName));
        $staffSchedule = array_values(array_filter($schedules, static fn (array $entry): bool => strtolower(trim((string) ($entry['staff'] ?? ''))) === $normalized && ((string) ($entry['date'] ?? '') !== '' ? (string) $entry['date'] : (string) $entry['day']) === $date));

        if ($this->hasTimeOffForStaff($staffName, $date, $timeOffEntries)) {
            return 'On Leave';
        }

        if ($staffSchedule === []) {
            return 'Unavailable';
        }

        foreach ($staffSchedule as $firstEntry) {
            $firstRange = $this->shifts->parseShiftRange((string) ($firstEntry['shift'] ?? ''));
            if ($firstRange[0] === 0 && $firstRange[1] === 0) {
                continue;
            }

            foreach ($staffSchedule as $secondEntry) {
                if ($firstEntry === $secondEntry) {
                    continue;
                }

                $secondRange = $this->shifts->parseShiftRange((string) ($secondEntry['shift'] ?? ''));
                if (($secondRange[0] !== 0 || $secondRange[1] !== 0) && $this->shifts->shiftTimesOverlap($firstRange, $secondRange)) {
                    return 'Fully Booked';
                }
            }
        }

        return 'Available';
    }

    private function hasTimeOffForStaff(string $staffName, string $date, array $timeOffEntries): bool
    {
        $normalized = strtolower(trim($staffName));

        foreach ($timeOffEntries as $entry) {
            if (strtolower(trim((string) ($entry['staff'] ?? ''))) === $normalized
                && trim((string) ($entry['date'] ?? '')) === $date) {
                return true;
            }
        }

        return false;
    }
}
