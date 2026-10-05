<?php $pageTitle = 'Staff Schedule – RehabPlus'; ?>
<?= view('layouts/header') ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <p class="page-title mb-1">Staff Schedule</p>
        <p class="page-subtitle mb-0">Plan staffing coverage, shift assignments, and clinic room usage.</p>
    </div>
</div>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger border-0 rounded-4 mb-4">
        <?= esc(session()->getFlashdata('error')) ?>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success border-0 rounded-4 mb-4">
        <?= esc(session()->getFlashdata('success')) ?>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add staff shift</h5>
        <div class="d-flex gap-2">
            <a href="<?= site_url('schedule') ?>?week_offset=<?= (int) ($weekOffset - 1) ?>" class="btn btn-secondary btn-sm">Previous Week</a>
            <a href="<?= site_url('schedule') ?>?week_offset=<?= (int) ($weekOffset + 1) ?>" class="btn btn-secondary btn-sm">Next Week</a>
        </div>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('schedule/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Staff member</label>
                    <select name="staff" class="form-select" required>
                        <option value="">Select staff</option>
                        <?php foreach ($staffDirectory as $staff): ?>
                            <option value="<?= esc($staff['name']) ?>"><?= esc($staff['name']) ?> (<?= esc($staff['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Role</label>
                    <input type="text" name="role" class="form-control" placeholder="Therapist" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Day</label>
                    <select name="day" class="form-select" required>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                        <option value="Sunday">Sunday</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Shift</label>
                    <input type="text" name="shift" class="form-control" placeholder="08:00 - 15:00" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Room</label>
                    <input type="text" name="room" class="form-control" placeholder="Room 3">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary">Add shift</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Add time off</h5>
    </div>
    <div class="card-body">
        <form method="post" action="<?= site_url('schedule/time-off/save') ?>">
            <?= csrf_field() ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Staff member</label>
                    <select name="staff" class="form-select" required>
                        <option value="">Select staff</option>
                        <?php foreach ($staffDirectory as $staff): ?>
                            <option value="<?= esc($staff['name']) ?>"><?= esc($staff['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Date</label>
                    <input type="date" name="date" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Type</label>
                    <select name="time_off_type" class="form-select">
                        <option value="Full day">Full day</option>
                        <option value="Specific time">Specific time</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Time</label>
                    <input type="text" name="time" class="form-control" placeholder="09:00 - 12:00">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Reason</label>
                    <input type="text" name="reason" class="form-control" placeholder="Medical leave" required>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-secondary">Add Time Off</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Staff on duty</span>
                    <i class="bi bi-people text-primary"></i>
                </div>
                <h4 class="mb-0"><?= esc($staffCount) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Today shifts</span>
                    <i class="bi bi-calendar-check text-success"></i>
                </div>
                <h4 class="mb-0"><?= esc($todayShifts) ?></h4>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted small">Active rooms</span>
                    <i class="bi bi-door-open text-info"></i>
                </div>
                <h4 class="mb-0"><?= esc($activeRooms) ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Staff availability</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($staffDirectory as $staff): ?>
                <?php $status = $staffAvailability[$staff['name']] ?? 'Unavailable'; ?>
                <div class="col-md-6 col-xl-4">
                    <div class="border rounded-4 p-3 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><?= esc($staff['name']) ?></strong>
                            <span class="badge bg-light text-dark"><?= esc($staff['role']) ?></span>
                        </div>
                        <div class="small text-muted mb-2">
                            <?= $status === 'Available' ? 'Ready for assignment' : ($status === 'On Leave' ? 'Time off recorded' : ($status === 'Fully Booked' ? 'No room available' : 'Not scheduled')) ?>
                        </div>
                        <span class="badge <?= $status === 'Available' ? 'bg-success-subtle text-success' : ($status === 'On Leave' ? 'bg-warning-subtle text-warning' : ($status === 'Fully Booked' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary')) ?>">
                            <?= esc($status) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Weekly schedule</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <?php foreach ($weekDays as $day): ?>
                            <th><?= esc($day['label']) ?><br><small class="text-muted"><?= esc($day['short']) ?></small></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffDirectory as $staff): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= esc($staff['name']) ?></div>
                                <small class="text-muted"><?= esc($staff['role']) ?></small>
                            </td>
                            <?php foreach ($weekDays as $day): ?>
                                <td>
                                    <?php $entry = $weeklySchedule[$staff['name']][$day['label']] ?? null; ?>
                                    <?php if ($entry): ?>
                                        <div class="fw-semibold text-primary"><?= esc($entry['shift']) ?></div>
                                        <small class="text-muted"><?= esc($entry['room']) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Off</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0">Today's staff list</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Shift</th>
                        <th>Room</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayStaffList as $entry): ?>
                        <tr>
                            <td><?= esc($entry['name']) ?></td>
                            <td><?= esc($entry['role']) ?></td>
                            <td><?= esc($entry['shift']) ?></td>
                            <td><?= esc($entry['room']) ?></td>
                            <td>
                                <span class="badge <?= $entry['status'] === 'Available' ? 'bg-success-subtle text-success' : ($entry['status'] === 'On Leave' ? 'bg-warning-subtle text-warning' : ($entry['status'] === 'Fully Booked' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary')) ?>"><?= esc($entry['status']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= view('layouts/footer') ?>
