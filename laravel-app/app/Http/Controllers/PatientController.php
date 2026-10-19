<?php

namespace App\Http\Controllers;

use App\Helpers\PatientValidation;
use App\Services\Firebase\FirebaseTransport;
use App\Services\Patient\ClinicalRecordService;
use App\Services\Patient\PatientProgress;
use App\Services\Patient\PatientService;
use App\Services\Patient\PrescriptionService;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Patients (Firestore patients/{uid}). Creating one also creates their app
 * login. Firebase errors are turned into friendly pages/flash messages by the
 * exception handler (bootstrap/app.php).
 */
class PatientController extends Controller
{
    public function __construct(
        private PatientService $patients,
        private PatientProgress $progress,
        private PrescriptionService $prescriptions,
        private ClinicalRecordService $records,
        private UserService $users,
        private FirebaseTransport $firebase,
    ) {
    }

    public function index(Request $request): View
    {
        return view('patients.index', $this->patients->search(
            $request->user(),
            $request->queryText('search'),
            $request->queryText('condition'),
            $request->boolean('archived'),
        ));
    }

    public function show(Request $request, string $uid): View
    {
        $user = $request->user();
        $patient = $this->findOr404($request, $uid);
        $data = [
            'patient' => $patient,
            'canManage' => $this->patients->canManage($user, $patient),
            'clinical' => $user->isClinician(),
        ];

        if ($data['clinical']) {
            // Five independent reads, fetched in one parallel round trip.
            $clinical = $this->firebase->concurrently([
                'sessions' => fn () => $this->progress->sessions($uid),
                'prescriptions' => fn () => $this->prescriptions->forPatient($uid),
                'assessments' => fn () => $this->records->list(ClinicalRecordService::ASSESSMENTS, [$patient]),
                'goals' => fn () => $this->records->list(ClinicalRecordService::GOALS, [$patient]),
                'notes' => fn () => $this->records->list(ClinicalRecordService::NOTES, [$patient]),
            ]);

            $data += $clinical + [
                'stats' => PatientProgress::stats($clinical['sessions']),
                'adherence' => PatientProgress::adherence($clinical['sessions'], $clinical['prescriptions']),
                'painTrend' => PatientProgress::painTrend($clinical['sessions']),
                'goalAchievement' => ClinicalRecordService::goalAchievement($clinical['goals']),
            ];
        }

        return view('patients.show', $data);
    }

    public function create(Request $request): View
    {
        return view('patients.form', [
            'patient' => null,
            'therapists' => $request->user()->isAdmin() ? $this->users->therapists() : [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $uid = $this->patients->create($request->user(), PatientValidation::create($request));

        return redirect()->route('patients.show', $uid)->with(
            'success',
            'Patient added. Give them their email and temporary password to sign in to the RehabPlus app; they can change the password with "Forgot password".',
        );
    }

    public function edit(Request $request, string $uid): View
    {
        $patient = $this->manageableOr403($request, $uid);

        return view('patients.form', [
            'patient' => $patient,
            'therapists' => $request->user()->isAdmin() ? $this->users->therapists() : [],
        ]);
    }

    public function update(Request $request, string $uid): RedirectResponse
    {
        $patient = $this->manageableOr403($request, $uid);
        $data = PatientValidation::profile($request);
        if (! $request->user()->isAdmin()) {
            unset($data['therapist_id']); // only admins reassign patients
        }

        $this->patients->update($request->user(), $patient, $data);

        return redirect()->route('patients.show', $uid)->with('success', 'Patient updated.');
    }

    public function archive(Request $request, string $uid): RedirectResponse
    {
        $this->patients->setArchived($this->manageableOr403($request, $uid), true);

        return redirect()->route('patients.index')->with('success', 'Patient archived. Their app login is disabled; their records are kept.');
    }

    public function restore(Request $request, string $uid): RedirectResponse
    {
        $this->patients->setArchived($this->manageableOr403($request, $uid), false);

        return redirect()->route('patients.show', $uid)->with('success', 'Patient restored. They can sign in to the app again.');
    }

    public function password(Request $request, string $uid): RedirectResponse
    {
        $patient = $this->manageableOr403($request, $uid);
        $this->patients->resetPassword($patient, PatientValidation::password($request)['password']);

        return redirect()->route('patients.show', $uid)->with('success', 'Temporary password set. Give it to the patient; they can change it in the app.');
    }

    /** 404 for patients that don't exist or that this user can't see. */
    private function findOr404(Request $request, string $uid): array
    {
        $patient = $this->patients->find($request->user(), $uid);
        abort_unless($patient, 404);

        return $patient;
    }

    private function manageableOr403(Request $request, string $uid): array
    {
        $patient = $this->findOr404($request, $uid);
        abort_unless($this->patients->canManage($request->user(), $patient), 403, 'Only an administrator or the patient\'s therapist can change this patient.');

        return $patient;
    }
}
