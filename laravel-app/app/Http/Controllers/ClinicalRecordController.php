<?php

namespace App\Http\Controllers;

use App\Helpers\ClinicalValidation;
use App\Services\Firebase\FirebaseTransport;
use App\Services\Patient\ClinicalRecordService;
use App\Services\Patient\PatientService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Assessments & Goals and Therapy Notes pages. Records are stored under each
 * patient in Firestore; therapists only see and add records for their own
 * patients, admins for everyone.
 */
class ClinicalRecordController extends Controller
{
    public function __construct(
        private PatientService $patients,
        private ClinicalRecordService $records,
        private FirebaseTransport $firebase,
    ) {
    }

    public function assessments(Request $request): View
    {
        $patients = $this->patients->visible($request->user());

        // ?patient=uid narrows the page to one patient the user may see.
        $selected = $request->queryText('patient');
        $focus = array_values(array_filter($patients, fn ($p) => $p['uid'] === $selected));
        $shown = $focus ?: $patients;

        [$assessments, $goals] = $this->firebase->concurrently([
            fn () => $this->records->list(ClinicalRecordService::ASSESSMENTS, $shown),
            fn () => $this->records->list(ClinicalRecordService::GOALS, $shown),
        ]);
        $achievement = ClinicalRecordService::goalAchievement($goals);

        return view('admin.assessments', [
            'patients' => $patients,
            'selectedPatient' => $focus ? $selected : '',
            'focusPatient' => $focus[0] ?? null,
            'assessments' => $assessments,
            'goals' => $goals,
            'averageScore' => $assessments ? round(array_sum(array_column($assessments, 'score')) / count($assessments)).'%' : '—',
            'goalTracking' => $achievement === null ? '—' : $achievement.'%',
            'activeGoals' => count(array_filter($goals, fn ($g) => $g['status'] === 'active')),
            'activePatients' => count($patients),
            'assessmentCount' => count($assessments),
        ]);
    }

    public function notes(Request $request): View
    {
        $patients = $this->patients->visible($request->user());
        $notes = $this->records->list(ClinicalRecordService::NOTES, $patients);
        $today = CarbonImmutable::now(config('app.timezone'))->format('Y-m-d');
        $weekAgo = CarbonImmutable::now()->subDays(7);

        return view('admin.notes', [
            'patients' => $patients,
            'selectedPatient' => $request->queryText('patient'),
            'notes' => $notes,
            'recentCount' => count(array_filter($notes, fn ($n) => $n['createdAt']?->greaterThanOrEqualTo($weekAgo))),
            'todayNotes' => count(array_filter($notes, fn ($n) => $n['noteDate']?->setTimezone(config('app.timezone'))->format('Y-m-d') === $today)),
        ]);
    }

    public function storeAssessment(Request $request): RedirectResponse
    {
        $data = ClinicalValidation::assessment($request);
        $this->records->createAssessment($request->user(), $this->patient($request, $data['patient_uid']), $data);

        return back()->with('success', 'Assessment saved.');
    }

    public function storeGoal(Request $request): RedirectResponse
    {
        $data = ClinicalValidation::goal($request);
        $this->records->createGoal($request->user(), $this->patient($request, $data['patient_uid']), $data);

        return back()->with('success', 'Goal added.');
    }

    public function updateGoal(Request $request, string $uid, string $id): RedirectResponse
    {
        $patient = $this->patient($request, $uid);
        $goal = $this->records->find(ClinicalRecordService::GOALS, $uid, $id);
        abort_unless($goal, 404);

        $this->records->updateGoal($patient, $goal, ClinicalValidation::goalUpdate($request));

        return back()->with('success', 'Goal updated.');
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $data = ClinicalValidation::note($request);
        $this->records->createNote($request->user(), $this->patient($request, $data['patient_uid']), $data);

        return back()->with('success', 'Note saved.');
    }

    /** Delete an assessment, goal or note (kind is constrained by the route). */
    public function destroy(Request $request, string $uid, string $kind, string $id): RedirectResponse
    {
        $patient = $this->patient($request, $uid);
        abort_unless($this->records->find($kind, $uid, $id), 404);

        $this->records->delete($kind, $patient, $id);

        return back()->with('success', ucfirst(rtrim($kind, 's')).' deleted.');
    }

    /** A patient this user may add records for: admin, or their own therapist. */
    private function patient(Request $request, string $uid): array
    {
        $patient = $this->patients->find($request->user(), $uid);
        abort_unless($patient && $this->patients->canManage($request->user(), $patient), 404);

        return $patient;
    }
}
