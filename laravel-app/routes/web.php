<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PatientPortalController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->role === 'patient'
            ? redirect()->route('patient.portal')
            : redirect()->route('dashboard');
    }

    return redirect()->route('login.page');
});

Route::get('/login', [AuthController::class, 'login'])->name('login.page');
Route::post('/login', [AuthController::class, 'attempt'])->name('login.submit');
Route::get('/patient-login', [AuthController::class, 'patientLogin'])->name('patient.login');
Route::post('/patient-login', [AuthController::class, 'attemptPatient'])->name('patient.login.submit');
Route::post('/logout', [AuthController::class, 'doLogout'])->name('logout');

Route::middleware(['auth.rehab'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
    Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
    Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
    Route::get('/patients/{id}', [PatientController::class, 'show'])->name('patients.show');
    Route::get('/patients/{id}/edit', [PatientController::class, 'edit'])->name('patients.edit');
    Route::put('/patients/{id}', [PatientController::class, 'update'])->name('patients.update');
    Route::delete('/patients/{id}', [PatientController::class, 'delete'])->name('patients.delete');

    Route::get('/patients/{patientId}/exercises/create', [ExerciseController::class, 'create'])->name('exercises.create');
    Route::post('/patients/{patientId}/exercises', [ExerciseController::class, 'store'])->name('exercises.store');
    Route::get('/patients/{patientId}/exercises/{id}/edit', [ExerciseController::class, 'edit'])->name('exercises.edit');
    Route::put('/patients/{patientId}/exercises/{id}', [ExerciseController::class, 'update'])->name('exercises.update');
    Route::delete('/patients/{patientId}/exercises/{id}', [ExerciseController::class, 'delete'])->name('exercises.delete');

    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{id}', [AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{id}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::put('/appointments/{id}', [AppointmentController::class, 'update'])->name('appointments.update');
    Route::delete('/appointments/{id}', [AppointmentController::class, 'delete'])->name('appointments.delete');
    Route::post('/appointments/{id}/status', [AppointmentController::class, 'changeStatus'])->name('appointments.status');

    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');

    Route::middleware(['role:superadmin'])->group(function () {
        Route::get('/patient-statistics', [DashboardController::class, 'patientStatistics'])->name('patient-statistics');
        Route::get('/patient-statistics/export/csv', [DashboardController::class, 'exportPatientStatisticsCsv'])->name('patient-statistics.export.csv');
        Route::get('/patient-statistics/export/pdf', [DashboardController::class, 'exportPatientStatisticsPdf'])->name('patient-statistics.export.pdf');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::post('/users/create-patient', [UserController::class, 'createPatient'])->name('users.createPatient');
        Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{id}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{id}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{id}', [UserController::class, 'delete'])->name('users.delete');
    });
});

Route::middleware(['auth.rehab'])->group(function () {
    Route::get('/patient-portal', [PatientPortalController::class, 'index'])->name('patient.portal');
});
