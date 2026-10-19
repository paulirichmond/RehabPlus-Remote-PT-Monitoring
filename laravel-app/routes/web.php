<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicalRecordController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Firebase uids / Firestore ids in URLs: checked before they reach a document path.
Route::pattern('uid', '[A-Za-z0-9_-]{1,128}');
Route::pattern('id', '[A-Za-z0-9_-]{1,128}');
Route::pattern('patientUid', '[A-Za-z0-9_-]{1,128}');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login.page');
});

Route::get('/login', [AuthController::class, 'login'])->name('login.page');
Route::post('/login', [AuthController::class, 'attempt'])->middleware('throttle:10,1')->name('login.submit');
Route::post('/logout', [AuthController::class, 'doLogout'])->name('logout');

// Forgot password: emailed 6-digit code, then a new password (see PasswordResetService).
// Each throttle has its own prefix so it doesn't share a counter with the login one.
Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'send'])->middleware('throttle:5,1,pwreset-send')->name('password.email');
Route::get('/forgot-password/verify', [PasswordResetController::class, 'verifyForm'])->name('password.verify');
Route::post('/forgot-password/verify', [PasswordResetController::class, 'verify'])->middleware('throttle:10,1,pwreset-verify')->name('password.verify.submit');
Route::post('/forgot-password/resend', [PasswordResetController::class, 'resend'])->middleware('throttle:5,1,pwreset-send')->name('password.resend');
Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1,pwreset-reset')->name('password.update');

// Content-Security-Policy violation reports from browsers (logged, see ContentSecurityPolicy).
Route::post('/csp-report', CspReportController::class)->middleware('throttle:60,1')->name('csp.report');

// Signed-in staff: admin, therapist or front-desk staff (see App\Auth\StaffUser).
Route::middleware(['auth.rehab'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reports & Analytics: one page, sections by role (see ReportService).
    Route::get('/analytics', [ReportController::class, 'index'])->name('analytics');
    Route::middleware(['role:admin'])->group(function () {
        Route::get('/analytics/export/csv', [ReportController::class, 'csv'])->name('analytics.export.csv');
        Route::get('/analytics/export/pdf', [ReportController::class, 'pdf'])->name('analytics.export.pdf');
    });

    // Old addresses of pages merged into Reports & Analytics; filters are kept.
    $toReports = fn (string $route, string $anchor = '') => fn (Request $request) => redirect()->to(
        route($route).($request->getQueryString() ? '?'.$request->getQueryString() : '').$anchor,
        301,
    );
    Route::get('/reports', $toReports('analytics'))->name('reports');
    Route::get('/patient-statistics', $toReports('analytics', '#patients'))->name('patient-statistics');
    Route::get('/patient-statistics/export/csv', $toReports('analytics.export.csv'))->name('patient-statistics.export.csv');
    Route::get('/patient-statistics/export/pdf', $toReports('analytics.export.pdf'))->name('patient-statistics.export.pdf');

    // Patients (Firestore). Everyone sees the list (therapists: their own);
    // the controller checks who may change each patient.
    Route::middleware(['role:admin,therapist'])->group(function () {
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    });
    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/{uid}', [PatientController::class, 'show'])->name('patients.show');
    Route::get('/patients/{uid}/edit', [PatientController::class, 'edit'])->name('patients.edit');
    Route::put('/patients/{uid}', [PatientController::class, 'update'])->name('patients.update');
    Route::post('/patients/{uid}/archive', [PatientController::class, 'archive'])->name('patients.archive');
    Route::post('/patients/{uid}/restore', [PatientController::class, 'restore'])->name('patients.restore');
    Route::post('/patients/{uid}/password', [PatientController::class, 'password'])->name('patients.password');

    // Clinical records: admins and therapists only.
    Route::middleware(['role:admin,therapist'])->group(function () {
        Route::get('/patients/{uid}/prescriptions/create', [PrescriptionController::class, 'create'])->name('prescriptions.create');
        Route::post('/patients/{uid}/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::get('/patients/{uid}/prescriptions/{id}/edit', [PrescriptionController::class, 'edit'])->name('prescriptions.edit');
        Route::put('/patients/{uid}/prescriptions/{id}', [PrescriptionController::class, 'update'])->name('prescriptions.update');
        Route::post('/patients/{uid}/prescriptions/{id}/active', [PrescriptionController::class, 'toggle'])->name('prescriptions.toggle');

        Route::get('/assessments', [ClinicalRecordController::class, 'assessments'])->name('assessments');
        Route::post('/assessments', [ClinicalRecordController::class, 'storeAssessment'])->name('assessments.store');
        Route::post('/goals', [ClinicalRecordController::class, 'storeGoal'])->name('goals.store');
        Route::put('/patients/{uid}/goals/{id}', [ClinicalRecordController::class, 'updateGoal'])->name('goals.update');
        Route::get('/notes', [ClinicalRecordController::class, 'notes'])->name('notes');
        Route::post('/notes', [ClinicalRecordController::class, 'storeNote'])->name('notes.store');
        Route::delete('/patients/{uid}/{kind}/{id}', [ClinicalRecordController::class, 'destroy'])
            ->whereIn('kind', ['assessments', 'goals', 'notes'])
            ->name('records.delete');
    });

    // Patient messaging (Firestore, shared with the app). Therapists answer the
    // "therapist" thread of their own patients; admin/staff the "clinic" thread.
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/{patientUid}', [MessageController::class, 'show'])->name('messages.show');
    Route::get('/messages/{patientUid}/feed', [MessageController::class, 'feed'])->name('messages.feed');
    Route::get('/messages/{patientUid}/status', [MessageController::class, 'status'])->name('messages.status');
    Route::post('/messages/{patientUid}', [MessageController::class, 'reply'])->name('messages.reply');

    // Clinic operations (Firestore top-level collections, see ClinicStore).
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{id}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{id}', [AppointmentController::class, 'delete'])->name('appointments.delete');
    Route::post('/appointments/{id}/status', [AppointmentController::class, 'changeStatus'])->name('appointments.status');

    Route::get('/billing', [PaymentController::class, 'index'])->name('billing');
    Route::post('/billing', [PaymentController::class, 'store'])->name('billing.store');
    Route::patch('/billing/{id}/status', [PaymentController::class, 'updateStatus'])->name('billing.status');
    Route::delete('/billing/{id}', [PaymentController::class, 'delete'])->name('billing.delete');
    Route::patch('/inventory/{inventory}/status', [InventoryController::class, 'updateStatus'])->name('inventory.status');
    Route::resource('inventory', InventoryController::class);
    Route::get('/schedule', [StaffScheduleController::class, 'index'])->name('schedule');
    Route::post('/schedule/save', [StaffScheduleController::class, 'store'])->name('schedule.store');
    Route::post('/schedule/time-off/save', [StaffScheduleController::class, 'storeTimeOff'])->name('schedule.timeoff.store');

    Route::middleware(['role:admin'])->group(function () {
        // Staff accounts (Firebase Auth + users/{uid}).
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{uid}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{uid}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{uid}/status', [UserController::class, 'status'])->name('users.status');
        Route::delete('/users/{uid}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
