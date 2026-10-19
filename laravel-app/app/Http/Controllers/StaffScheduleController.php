<?php

namespace App\Http\Controllers;

use App\Helpers\StaffScheduleValidation;
use App\Services\StaffSchedule\ShiftConflictService;
use App\Services\StaffSchedule\StaffScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffScheduleController extends Controller
{
    private const NOT_SCHEDULABLE = 'Only active therapists and front desk staff can be put on the schedule.';

    public function __construct(
        private StaffScheduleService $schedule,
        private ShiftConflictService $shifts,
    ) {
    }

    public function index(Request $request): View
    {
        return view('admin.schedule', $this->schedule->overview((int) $request->queryText('week_offset', '0')));
    }

    public function store(Request $request): RedirectResponse
    {
        $shift = StaffScheduleValidation::shift($request);

        if (! $this->schedule->isSchedulable($shift['staff'])) {
            return redirect()->route('schedule')->withInput()->with('error', self::NOT_SCHEDULABLE);
        }

        if ($this->shifts->hasConflict($shift)) {
            return redirect()->route('schedule')->withInput()->with('error', 'Schedule conflict detected: this staff member already has a shift at that time, or the room is already assigned to another staff member.');
        }

        $this->schedule->addShift($shift);

        return redirect()->route('schedule')->with('success', 'Staff schedule added successfully.');
    }

    public function storeTimeOff(Request $request): RedirectResponse
    {
        $timeOff = StaffScheduleValidation::timeOff($request);

        if (! $this->schedule->isSchedulable($timeOff['staff'])) {
            return redirect()->route('schedule')->withInput()->with('error', self::NOT_SCHEDULABLE);
        }

        $this->schedule->addTimeOff($timeOff);

        return redirect()->route('schedule')->with('success', 'Time off entry added successfully.');
    }
}
