<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */


// ======================================================
// AUTH ROUTES
// ======================================================

$routes->get('/', 'AuthController::login');

$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::attempt');
$routes->get('patient-login', 'AuthController::patientLogin');
$routes->post('patient-login', 'AuthController::attemptPatient');

$routes->get('logout', 'AuthController::logout');
$routes->post('logout', 'AuthController::doLogout');

$routes->get('patient-portal', 'PatientPortalController::index', ['filter' => ['auth', 'role:patient']]);


// ======================================================
// PROTECTED ROUTES
// ======================================================

$routes->group('', ['filter' => 'auth'], function ($routes) {

    // ==================================================
    // DASHBOARD
    // ==================================================

    $routes->get('dashboard', 'DashboardController::index');


    // ==================================================
    // PATIENTS
    // ==================================================

    $routes->get('patients', 'PatientController::index');
    $routes->get('patients/create', 'PatientController::create');
    $routes->get('patients/new', 'PatientController::create');
    $routes->get('patients/(:num)', 'PatientController::show/$1');
    $routes->get('patients/(:num)/edit', 'PatientController::edit/$1');
    $routes->post('patients', 'PatientController::store');
    $routes->post('patients/(:num)', 'PatientController::update/$1');
    $routes->post('patients/(:num)/delete', 'PatientController::delete/$1');
    $routes->patch('patients/(:num)', 'PatientController::update/$1');
    $routes->put('patients/(:num)', 'PatientController::update/$1');
    $routes->delete('patients/(:num)', 'PatientController::delete/$1');


    // ==================================================
    // EXERCISE RECORDS
    // ==================================================

    $routes->get(
        'patients/(:num)/exercises/create',
        'ExerciseController::create/$1'
    );

    $routes->post(
        'patients/(:num)/exercises',
        'ExerciseController::store/$1'
    );

    $routes->get(
        'patients/(:num)/exercises/(:num)/edit',
        'ExerciseController::edit/$1/$2'
    );

    $routes->post(
        'patients/(:num)/exercises/(:num)',
        'ExerciseController::update/$1/$2'
    );

    $routes->post(
        'patients/(:num)/exercises/(:num)/delete',
        'ExerciseController::delete/$1/$2'
    );


    // ==================================================
    // APPOINTMENTS
    // ==================================================

    $routes->get(
        'appointments',
        'Appointments::index'
    );

    $routes->get(
        'appointments/create',
        'Appointments::create'
    );

    $routes->get(
        'appointments/(:num)',
        'Appointments::show/$1'
    );

    $routes->get(
        'appointments/(:num)/edit',
        'Appointments::edit/$1'
    );

    $routes->post(
        'appointments',
        'Appointments::store'
    );

    $routes->post(
        'appointments/store',
        'Appointments::store'
    );

    $routes->post(
        'appointments/(:num)',
        'Appointments::update/$1'
    );

    $routes->post(
        'appointments/(:num)/delete',
        'Appointments::delete/$1'
    );

    $routes->post(
        'appointments/(:num)/status',
        'Appointments::changeStatus/$1'
    );


    // ==================================================
    // ANALYTICS
    // ==================================================

    $routes->get(
        'analytics',
        'DashboardController::index'
    );

    $routes->get(
        'reports-analytics',
        'DashboardController::reportsAnalytics'
    );

    $routes->get(
        'reports',
        'DashboardController::reports'
    );

    $routes->get(
        'reports/export',
        'DashboardController::export'
    );

    $routes->get(
        'billing',
        'DashboardController::billing'
    );

    $routes->post(
        'billing/save',
        'DashboardController::saveBilling'
    );

    $routes->get(
        'inventory',
        'DashboardController::inventory'
    );

    $routes->post(
        'inventory/save',
        'DashboardController::saveInventory'
    );

    $routes->get(
        'schedule',
        'DashboardController::schedule'
    );

    $routes->post(
        'schedule/save',
        'DashboardController::saveSchedule'
    );

    $routes->post(
        'schedule/time-off/save',
        'DashboardController::saveTimeOff'
    );

    $routes->get(
        'notes',
        'DashboardController::notes'
    );

    $routes->post(
        'notes/save',
        'DashboardController::saveNotes'
    );

    $routes->get(
        'assessments',
        'DashboardController::assessments'
    );

    $routes->post(
        'assessments/save',
        'DashboardController::saveAssessments'
    );

    // ==================================================
    // USER MANAGEMENT
    // ==================================================

    $routes->group('users', ['filter' => 'role:superadmin'], function ($routes) {

        // USERS PAGE

        $routes->get(
            '/',
            'UserController::index'
        );

        // CREATE STAFF ACCOUNT PAGE

        $routes->get(
            'create',
            'UserController::create'
        );

        // STORE STAFF ACCOUNT

        $routes->post(
            '/',
            'UserController::store'
        );

        // CREATE PATIENT ACCOUNT

        $routes->post(
            'create-patient',
            'UserController::createPatient'
        );

        // EDIT USER

        $routes->get(
            '(:num)/edit',
            'UserController::edit/$1'
        );

        $routes->get(
            '(:num)',
            'UserController::show/$1'
        );

        // UPDATE USER

        $routes->post(
            '(:num)',
            'UserController::update/$1'
        );

        // DELETE USER

        $routes->post(
            '(:num)/delete',
            'UserController::delete/$1'
        );

    });

});


// ======================================================
// API ROUTES
// ======================================================

$routes->group('api/v1', ['filter' => 'apiauth'], function ($routes) {

    $routes->resource('patients', [
        'controller' => 'Api\PatientApiController'
    ]);

});