<?php

namespace App\Services\StaffSchedule;

use App\Auth\StaffUser;
use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;
use App\Services\Firebase\FirebaseTransport;
use App\Services\User\UserService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Staff shifts and time off (Firestore `staffShifts` / `staffTimeOff`, see ClinicStore). */
class StaffScheduleService
{
    /** Roles that work shifts: therapists and front desk staff. Admins don't. */
    public const SCHEDULABLE_ROLES = [StaffUser::THERAPIST, StaffUser::STAFF];

    /** Labels on the schedule page, in the order above when someone holds both roles. */
    private const ROLE_LABELS = [StaffUser::THERAPIST => 'Therapist', StaffUser::STAFF => 'Front Desk Staff'];

    public function __construct(
        private ClinicStore $store,
        private ShiftConflictService $shifts,
        private UserService $users,
        private FirebaseTransport $firebase,
    ) {
    }

    public function overview(int $weekOffset): array
    {
        $weekOffset = max(-12, min(12, $weekOffset));
        $monday = CarbonImmutable::now()->startOfWeek()->addWeeks($weekOffset);
        $weekDays = collect(range(0, 6))->map(fn (int $i) => [
            'label' => $monday->addDays($i)->format('l'),
            'short' => $monday->addDays($i)->format('M j'),
        ])->all();

        // Shifts, time off and staff accounts are independent reads.
        [$schedules, $timeOff, $staffAccounts] = $this->firebase->concurrently([
            fn () => $this->store->all(ClinicStore::SHIFTS),
            fn () => $this->store->all(ClinicStore::TIME_OFF),
            fn () => $this->users->staff(),
        ]);

        $staffDirectory = $this->staffDirectory($staffAccounts);

        // Only people in the directory are on the schedule, so an old entry under an
        // admin's (or a removed account's) name doesn't count towards today's numbers.
        // Names are matched exactly: any difference in the full name is a different person.
        $scheduledNames = collect($staffDirectory)->pluck('name');
        $schedules = $schedules
            ->filter(fn (ClinicRecord $s) => $scheduledNames->containsStrict((string) $s->staff))
            ->sortBy(fn (ClinicRecord $s) => (string) $s->shift)
            ->values();

        $today = CarbonImmutable::today();
        $todayEntries = $schedules->where('day', $today->format('l'));
        $timeOffToday = $timeOff
            ->where('date', $today->toDateString())
            ->map(fn (ClinicRecord $off) => (string) $off->staff);

        $staffAvailability = [];
        $todayStaffList = [];
        foreach ($staffDirectory as $staff) {
            $name = $staff['name'];
            $entries = $todayEntries->filter(fn (ClinicRecord $entry) => (string) $entry->staff === $name);
            $status = $this->availability($entries, $timeOffToday->containsStrict($name));
            $staffAvailability[$staff['name']] = $status;
            $todayStaffList[] = [
                'name' => $staff['name'],
                'role' => $staff['role'],
                'shift' => $entries->first()?->shift ?? 'Off',
                'room' => $entries->first()?->room ?? 'Not assigned',
                'status' => $status,
            ];
        }

        $weeklySchedule = [];
        foreach ($schedules as $entry) {
            $weeklySchedule[$entry->staff][$entry->day] ??= ['shift' => $entry->shift, 'room' => $entry->room];
        }

        return [
            'weekOffset' => $weekOffset,
            'staffDirectory' => $staffDirectory,
            'weekDays' => $weekDays,
            'staffAvailability' => $staffAvailability,
            'weeklySchedule' => $weeklySchedule,
            'todayStaffList' => $todayStaffList,
            'staffCount' => $todayEntries->pluck('staff')->unique()->count(),
            'todayShifts' => $todayEntries->count(),
            'activeRooms' => $todayEntries->pluck('room')->filter()->unique()->count(),
        ];
    }

    public function addShift(array $shift): string
    {
        return $this->store->create(ClinicStore::SHIFTS, [
            'staff' => $shift['staff'],
            'role' => $shift['role'],
            'day' => $shift['day'],
            'shift' => $shift['shift'],
            'room' => $shift['room'],
        ]);
    }

    public function addTimeOff(array $timeOff): string
    {
        return $this->store->create(ClinicStore::TIME_OFF, [
            'staff' => $timeOff['staff'],
            'date' => CarbonImmutable::parse($timeOff['date'])->toDateString(),
            'type' => $timeOff['type'],
            'time' => $timeOff['time'],
            'reason' => $timeOff['reason'],
        ]);
    }

    /** Whether shifts / time off may be saved for this name (an active therapist or front desk account). */
    public function isSchedulable(string $name): bool
    {
        return collect($this->staffDirectory($this->users->staff()))->pluck('name')->containsStrict($name);
    }

    /**
     * Active therapist and front desk accounts from Firebase. Roles are an allow-list, so
     * admins (including any admin created later) and any future role never appear here.
     * The schedule is keyed by the exact full name, so one person with both a therapist and
     * a front desk account under the same name is a single row labelled with both roles
     * ("Therapist & Front Desk Staff"). Any difference in the name (case, spacing, spelling)
     * keeps them as separate people.
     */
    private function staffDirectory(array $staffAccounts): array
    {
        return collect($staffAccounts)
            ->filter(fn (array $user) => $user['active'] && in_array($user['role'], self::SCHEDULABLE_ROLES, true))
            ->groupBy(fn (array $user) => $user['name'])
            ->map(fn (Collection $accounts) => [
                'name' => $accounts->first()['name'],
                'role' => collect(self::SCHEDULABLE_ROLES)
                    ->filter(fn (string $role) => $accounts->contains('role', $role))
                    ->map(fn (string $role) => self::ROLE_LABELS[$role])
                    ->implode(' & '),
            ])
            ->values()
            ->all();
    }

    private function availability(Collection $entries, bool $onLeave): string
    {
        if ($onLeave) {
            return 'On Leave';
        }

        if ($entries->isEmpty()) {
            return 'Unavailable';
        }

        $ranges = $entries->map(fn (ClinicRecord $entry) => $this->shifts->shiftRange((string) $entry->shift))->filter()->values();
        foreach ($ranges as $i => $first) {
            foreach ($ranges->slice($i + 1) as $second) {
                if ($this->shifts->overlaps($first, $second)) {
                    return 'Fully Booked';
                }
            }
        }

        return 'Available';
    }
}
