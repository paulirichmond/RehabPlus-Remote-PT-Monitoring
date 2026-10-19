<?php

namespace Tests\Feature;

use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Assigning exercises writes exactly the prescription shape the app parses. */
class PrescriptionTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patientDoc('pOne', ['name' => 'Olive One', 'therapistId' => 'thA', 'therapistName' => 'Dr. Ana']);
        $this->patientDoc('pTwo', ['name' => 'Tom Two', 'therapistId' => 'thB']);
        $this->docs['patients/pOne/devices/tok1'] = ['token' => 'tok1'];
    }

    private function therapist()
    {
        return $this->staff('therapist', 'thA', 'Dr. Ana');
    }

    public function test_assigning_an_exercise_writes_prescription_and_notification_then_pushes(): void
    {
        $this->actingAs($this->therapist())->post('/patients/pOne/prescriptions', [
            'exercise_type' => 'kneeExtension',
            'target_reps' => 12,
            'hold_seconds' => 30, // ignored for a reps exercise
            'frequency_per_week' => 4,
            'days' => ['3', '1', '5'],
            'start_hour' => 8,
            'end_hour' => 20,
            'notes' => '  Slow and controlled.  ',
        ])->assertRedirect('/patients/pOne')->assertSessionHas('success');

        [$rxWrite, $notification] = $this->commits[0];
        $this->assertSame(['exists' => false], $rxWrite['currentDocument']);
        $this->assertSame([['fieldPath' => 'createdAt', 'setToServerValue' => 'REQUEST_TIME']], $rxWrite['updateTransforms']);

        $rx = array_values($this->docsLike('patients/pOne/prescriptions/*'))[0];
        $this->assertSame('kneeExtension', $rx['exerciseType']);
        $this->assertSame(12, $rx['targetReps']);
        $this->assertNull($rx['holdSeconds']);
        $this->assertSame(4, $rx['frequencyPerWeek']);
        $this->assertSame(['days' => [1, 3, 5], 'startHour' => 8, 'endHour' => 20], $rx['schedule']);
        $this->assertSame('Slow and controlled.', $rx['notes']);
        $this->assertTrue($rx['active']);
        $this->assertSame('thA', $rx['assignedBy']);

        $fields = $notification['update']['fields'];
        $this->assertSame('exercise_assigned', $fields['type']['stringValue']);
        $this->assertSame('New Exercise Assigned', $fields['title']['stringValue']);
        $this->assertSame('Knee Extension: 12 reps, 4x per week.', $fields['body']['stringValue']);
        $this->assertFalse($fields['read']['booleanValue']);

        $this->assertCount(1, $this->pushes);
        $this->assertSame(['type' => 'exercise_assigned'], $this->pushes[0]['data']);
        $this->assertSame('chat_messages', $this->pushes[0]['android']['notification']['channel_id']);
    }

    public function test_no_push_when_the_patient_turned_exercise_notifications_off(): void
    {
        $this->docs['patients/pOne/preferences/notifications'] = ['messages' => true, 'exercises' => false];

        $this->actingAs($this->therapist())->post('/patients/pOne/prescriptions', [
            'exercise_type' => 'kneeExtension',
            'target_reps' => 10,
            'frequency_per_week' => 3,
        ])->assertRedirect('/patients/pOne');

        $this->assertCount(1, $this->docsLike('patients/pOne/prescriptions/*'));
        $this->assertCount(1, $this->docsLike('patients/pOne/notifications/*'), 'still on the Notifications page');
        $this->assertCount(0, $this->pushes);
    }

    public function test_hold_exercise_keeps_hold_length_and_no_schedule_means_any_time(): void
    {
        $this->actingAs($this->therapist())->post('/patients/pOne/prescriptions', [
            'exercise_type' => 'wallSit', 'target_reps' => 3, 'hold_seconds' => 25, 'frequency_per_week' => 3,
        ])->assertRedirect('/patients/pOne');

        $rx = array_values($this->docsLike('patients/pOne/prescriptions/*'))[0];
        $this->assertSame(25, $rx['holdSeconds']);
        $this->assertNull($rx['schedule']);
        $this->assertNull($rx['notes']);
    }

    public function test_second_batch_exercises_can_be_assigned(): void
    {
        // Must match the app's ExerciseType (lib/models/exercise_model.dart).
        $expected = [
            'miniSquat' => 'Mini Squat',
            'hipAbduction' => 'Standing Hip Abduction',
            'hamstringCurl' => 'Standing Hamstring Curl',
            'marching' => 'Marching in Place',
            'overheadRaise' => 'Overhead Arm Raise',
        ];

        foreach ($expected as $code => $name) {
            $this->actingAs($this->therapist())->post('/patients/pOne/prescriptions', [
                'exercise_type' => $code, 'target_reps' => 10, 'frequency_per_week' => 3,
            ])->assertRedirect('/patients/pOne')->assertSessionHasNoErrors();
        }

        $assigned = array_column($this->docsLike('patients/pOne/prescriptions/*'), 'exerciseType');
        sort($assigned);
        $codes = array_keys($expected);
        sort($codes);
        $this->assertSame($codes, $assigned);

        $bodies = array_map(
            fn ($doc) => $doc['body'],
            $this->docsLike('patients/pOne/notifications/*'),
        );
        foreach ($expected as $name) {
            $this->assertContains("{$name}: 10 reps, 3x per week.", $bodies);
        }
    }

    public function test_validation(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->post('/patients/pOne/prescriptions', ['exercise_type' => 'jumpingJacks', 'target_reps' => 10, 'frequency_per_week' => 3])
            ->assertSessionHasErrors('exercise_type');
        $this->actingAs($user)->post('/patients/pOne/prescriptions', ['exercise_type' => 'bicepCurl', 'target_reps' => 10, 'frequency_per_week' => 3, 'start_hour' => 18, 'end_hour' => 9])
            ->assertSessionHasErrors('end_hour');
        $this->actingAs($user)->post('/patients/pOne/prescriptions', ['exercise_type' => 'bicepCurl', 'target_reps' => 0, 'frequency_per_week' => 3, 'days' => [8]])
            ->assertSessionHasErrors(['target_reps', 'days.0']);
        $this->assertSame([], $this->commits);
    }

    public function test_edit_and_stop_resume(): void
    {
        $this->docs['patients/pOne/prescriptions/rx1'] = ['exerciseType' => 'bicepCurl', 'targetReps' => 10, 'frequencyPerWeek' => 3, 'schedule' => ['days' => [1], 'startHour' => 8, 'endHour' => 12], 'notes' => 'old', 'active' => true, 'assignedBy' => 'thA'];
        $user = $this->therapist();

        $this->actingAs($user)->get('/patients/pOne/prescriptions/rx1/edit')->assertOk()->assertSee('Edit Bicep Curl');
        $this->actingAs($user)->put('/patients/pOne/prescriptions/rx1', ['exercise_type' => 'bicepCurl', 'target_reps' => 15, 'frequency_per_week' => 5])
            ->assertRedirect('/patients/pOne');

        $rx = $this->docs['patients/pOne/prescriptions/rx1'];
        $this->assertSame(15, $rx['targetReps']);
        $this->assertNull($rx['schedule'], 'clearing the schedule is saved');
        $this->assertNull($rx['notes']);
        $this->assertTrue($rx['active']);
        $this->assertSame('thA', $rx['assignedBy'], 'merge keeps other fields');
        $this->assertCount(0, $this->pushes, 'edits do not notify');

        $this->actingAs($user)->post('/patients/pOne/prescriptions/rx1/active', ['active' => 0])->assertRedirect('/patients/pOne');
        $this->assertFalse($this->docs['patients/pOne/prescriptions/rx1']['active']);
        $this->assertArrayHasKey('patients/pOne/prescriptions/rx1', $this->docs, 'never deleted');

        $this->actingAs($user)->post('/patients/pOne/prescriptions/rx1/active', ['active' => 1]);
        $this->assertTrue($this->docs['patients/pOne/prescriptions/rx1']['active']);
    }

    public function test_only_the_patients_therapist_or_an_admin_can_prescribe(): void
    {
        $this->actingAs($this->therapist())->post('/patients/pTwo/prescriptions', ['exercise_type' => 'bicepCurl', 'target_reps' => 10, 'frequency_per_week' => 3])
            ->assertNotFound();
        $this->actingAs($this->staff('staff'))->get('/patients/pOne/prescriptions/create')->assertForbidden();
        $this->assertSame([], $this->commits);

        $this->actingAs($this->staff('admin'))->post('/patients/pTwo/prescriptions', ['exercise_type' => 'bicepCurl', 'target_reps' => 10, 'frequency_per_week' => 3])
            ->assertRedirect('/patients/pTwo');
    }
}
