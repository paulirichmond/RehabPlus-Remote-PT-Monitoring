<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\Support\FakeFirebase;
use Tests\TestCase;

/** Assessments, goals and therapy notes, stored under each patient in Firestore. */
class ClinicalRecordTest extends TestCase
{
    use FakeFirebase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->patientDoc('pOne', ['name' => 'Olive One', 'therapistId' => 'thA', 'therapistName' => 'Dr. Ana']);
        $this->patientDoc('pTwo', ['name' => 'Tom Two', 'therapistId' => 'thB']);
        $this->docs['patients/pTwo/assessments/a9'] = ['type' => 'Secret ROM', 'score' => 50, 'assessedAt' => CarbonImmutable::now()->subDay(), 'therapistName' => 'Dr. Ben'];
        $this->docs['patients/pTwo/notes/n9'] = ['topic' => 'Private topic', 'text' => 'x', 'noteDate' => CarbonImmutable::now()];
        $this->docs['patients/pTwo/goals/g9'] = ['title' => 'Hidden goal', 'status' => 'active', 'progress' => 10, 'createdAt' => CarbonImmutable::now()];
    }

    private function therapist()
    {
        return $this->staff('therapist', 'thA', 'Dr. Ana');
    }

    public function test_assessment_is_saved_under_the_patient(): void
    {
        $this->actingAs($this->therapist())->from('/assessments')->post('/assessments', [
            'patient_uid' => 'pOne', 'type' => 'Range of motion', 'score' => 82, 'date' => '2026-10-15', 'notes' => 'Flexion 110°',
        ])->assertRedirect('/assessments')->assertSessionHas('success');

        $a = array_values($this->docsLike('patients/pOne/assessments/*'))[0];
        $this->assertSame('Range of motion', $a['type']);
        $this->assertSame(82, $a['score']);
        $this->assertSame('Flexion 110°', $a['notes']);
        $this->assertSame('thA', $a['therapistId']);
        $this->assertSame('Dr. Ana', $a['therapistName']);
        $this->assertSame('2026-10-15 00:00', $a['assessedAt']->setTimezone(config('app.timezone'))->format('Y-m-d H:i'));
        $this->assertSame(['exists' => false], $this->commits[0][0]['currentDocument']);
    }

    public function test_goal_lifecycle(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->from('/assessments')->post('/goals', [
            'patient_uid' => 'pOne', 'title' => 'Climb stairs unaided', 'progress' => 20, 'target_date' => '2026-12-01',
        ])->assertRedirect('/assessments');

        [$path, $goal] = [array_key_first($this->docsLike('patients/pOne/goals/*')), array_values($this->docsLike('patients/pOne/goals/*'))[0]];
        $this->assertSame('active', $goal['status']);
        $this->assertSame(20, $goal['progress']);
        $this->assertNull($goal['achievedAt']);
        $id = basename($path);

        $this->actingAs($user)->put("/patients/pOne/goals/{$id}", ['status' => 'achieved', 'progress' => 70])->assertSessionHas('success');
        $goal = $this->docs[$path];
        $this->assertSame('achieved', $goal['status']);
        $this->assertSame(100, $goal['progress'], 'achieved means 100%');
        $this->assertInstanceOf(CarbonImmutable::class, $goal['achievedAt']);

        $this->actingAs($user)->put("/patients/pOne/goals/{$id}", ['status' => 'active', 'progress' => 90]);
        $this->assertNull($this->docs[$path]['achievedAt']);

        $this->actingAs($user)->delete("/patients/pOne/goals/{$id}")->assertSessionHas('success');
        $this->assertArrayNotHasKey($path, $this->docs);
    }

    public function test_note_is_saved_and_listed(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->from('/notes')->post('/notes', [
            'patient_uid' => 'pOne', 'topic' => 'Mobility progress', 'text' => "Walks 200m.\nLess limp.", 'date' => now()->format('Y-m-d'),
        ])->assertRedirect('/notes');

        $note = array_values($this->docsLike('patients/pOne/notes/*'))[0];
        $this->assertSame('Mobility progress', $note['topic']);
        $this->assertSame("Walks 200m.\nLess limp.", $note['text']);

        $this->actingAs($user)->get('/notes')->assertOk()
            ->assertSee('Mobility progress')
            ->assertSee('Olive One')
            ->assertDontSee('Private topic')
            ->assertSee('Notes dated today');
    }

    public function test_therapist_only_sees_and_writes_for_their_patients(): void
    {
        $user = $this->therapist();

        $this->actingAs($user)->get('/assessments')->assertOk()->assertDontSee('Secret ROM')->assertDontSee('Hidden goal')->assertDontSee('Tom Two');

        $this->actingAs($user)->post('/assessments', ['patient_uid' => 'pTwo', 'type' => 'X', 'score' => 1, 'date' => '2026-10-01'])->assertNotFound();
        $this->actingAs($user)->post('/notes', ['patient_uid' => 'pTwo', 'topic' => 'X', 'text' => 'Y', 'date' => '2026-10-01'])->assertNotFound();
        $this->actingAs($user)->put('/patients/pTwo/goals/g9', ['status' => 'achieved', 'progress' => 100])->assertNotFound();
        $this->actingAs($user)->delete('/patients/pTwo/notes/n9')->assertNotFound();
        $this->actingAs($user)->post('/assessments', ['patient_uid' => '../users', 'type' => 'X', 'score' => 1, 'date' => '2026-10-01'])->assertNotFound();

        $this->assertSame([], $this->commits);
        $this->assertSame([], $this->deleted);
    }

    public function test_assessments_page_can_show_one_patient(): void
    {
        $this->docs['patients/pOne/assessments/a1'] = ['type' => 'Olive ROM', 'score' => 70, 'assessedAt' => CarbonImmutable::now(), 'notes' => str_repeat('Long finding. ', 10).'END'];
        $this->docs['patients/pOne/goals/g1'] = ['title' => 'Olive goal', 'status' => 'active', 'progress' => 20, 'createdAt' => CarbonImmutable::now()];
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get('/assessments?patient=pOne')->assertOk()
            ->assertSee('Olive ROM')->assertSee('Olive goal')->assertSee('END')
            ->assertDontSee('Secret ROM')->assertDontSee('Hidden goal')
            ->assertSee('70%');

        $this->actingAs($admin)->get('/assessments?patient=pTwo')->assertOk()
            ->assertSee('Secret ROM')->assertDontSee('Olive ROM');
    }

    public function test_small_patient_lists_read_each_patient_instead_of_the_whole_clinic(): void
    {
        $this->actingAs($this->therapist())->get('/assessments')->assertOk();

        $this->assertSame([], $this->collectionGroupReads());
    }

    public function test_large_patient_lists_use_one_collection_group_read(): void
    {
        for ($i = 0; $i <= \App\Services\Patient\ClinicalRecordService::PER_PATIENT_READ_LIMIT; $i++) {
            $this->patientDoc("bulk{$i}");
        }

        $this->actingAs($this->staff('admin'))->get('/assessments')->assertOk()->assertSee('Secret ROM');

        $this->assertEqualsCanonicalizing(['assessments', 'goals'], $this->collectionGroupReads());
    }

    public function test_array_query_values_do_not_crash_pages(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get('/assessments?patient[]=pOne')->assertOk()->assertSee('Secret ROM');
        $this->actingAs($admin)->get('/notes?patient[]=pOne')->assertOk();
        $this->actingAs($admin)->get('/patients?search[]=x&condition[]=y')->assertOk();
        $this->actingAs($admin)->get('/schedule?week_offset[]=1')->assertOk();
    }

    public function test_therapist_cannot_filter_to_someone_elses_patient(): void
    {
        $this->actingAs($this->therapist())->get('/assessments?patient=pTwo')->assertOk()
            ->assertDontSee('Secret ROM')->assertDontSee('Hidden goal')->assertDontSee('Tom Two');
    }

    public function test_admin_sees_everything_and_stats(): void
    {
        $this->docs['patients/pOne/assessments/a1'] = ['type' => 'Strength', 'score' => 70, 'assessedAt' => CarbonImmutable::now()];
        $this->docs['patients/pOne/goals/g1'] = ['title' => 'Done goal', 'status' => 'achieved', 'progress' => 100, 'createdAt' => CarbonImmutable::now()];

        $this->actingAs($this->staff('admin'))->get('/assessments')->assertOk()
            ->assertSee('Secret ROM')->assertSee('Strength')->assertSee('Hidden goal')
            ->assertSee('60%')   // average score (50 + 70) / 2
            ->assertSee('50%');  // 1 of 2 goals achieved
    }

    public function test_front_desk_staff_have_no_clinical_pages(): void
    {
        $user = $this->staff('staff');
        $this->actingAs($user)->get('/assessments')->assertForbidden();
        $this->actingAs($user)->get('/notes')->assertForbidden();
        $this->actingAs($user)->post('/notes', ['patient_uid' => 'pOne', 'topic' => 'X', 'text' => 'Y', 'date' => '2026-10-01'])->assertForbidden();
    }

    public function test_validation(): void
    {
        $user = $this->therapist();
        $this->actingAs($user)->post('/assessments', ['patient_uid' => 'pOne', 'type' => 'ROM', 'score' => 101, 'date' => now()->addDay()->format('Y-m-d')])
            ->assertSessionHasErrors(['score', 'date']);
        $this->docs['patients/pOne/goals/g1'] = ['title' => 'Goal', 'status' => 'active', 'progress' => 0];
        $this->actingAs($user)->put('/patients/pOne/goals/g1', ['status' => 'done', 'progress' => 5])
            ->assertSessionHasErrors('status');
    }
}
