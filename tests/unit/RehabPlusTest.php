<?php

namespace Tests\Unit;

use App\Models\PatientModel;
use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class RehabPlusTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $seed        = 'UserSeeder';

    // Test 1: Password hashing
    public function testPasswordIsHashed(): void
    {
        $plain  = 'secret123';
        $hashed = password_hash($plain, PASSWORD_DEFAULT);

        $this->assertTrue(password_verify($plain, $hashed));
        $this->assertNotEquals($plain, $hashed);
    }

    // Test 2: UserModel finds user by email
    public function testFindUserByEmail(): void
    {
        $model = new UserModel();
        $user  = $model->findByEmail('superadmin@rehabplus.com');

        $this->assertNotNull($user);
        $this->assertEquals('superadmin', $user['role']);
    }

    // Test 3: UserModel returns users by role
    public function testGetUsersByRole(): void
    {
        $model   = new UserModel();
        $managers = $model->getByRole('manager');

        $this->assertIsArray($managers);
        $this->assertCount(1, $managers);
        $this->assertEquals('manager', $managers[0]['role']);
    }

    // Test 4: PatientModel validation rejects empty name
    public function testPatientValidationFailsWithEmptyName(): void
    {
        $model  = new PatientModel();
        $result = $model->insert(['name' => '', 'condition' => 'Test Condition']);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $model->errors());
    }

    // Test 5: Staff account roles exclude patient
    public function testStaffRoleValidationRejectsPatientRole(): void
    {
        $this->assertSame('staff', UserModel::normalizeRoleForStaffAccount('patient'));
        $this->assertSame('therapist', UserModel::normalizeRoleForStaffAccount('therapist'));
        $this->assertSame('manager', UserModel::normalizeRoleForStaffAccount('manager'));
        $this->assertSame('superadmin', UserModel::normalizeRoleForStaffAccount('superadmin'));
    }

    // Test 6: Compliance rate calculation
    public function testComplianceRateCalculation(): void
    {
        $prescribed = 10;
        $completed  = 8;
        $compliance = $prescribed > 0 ? round($completed / $prescribed * 100, 1) : 0;

        $this->assertEquals(80.0, $compliance);
    }
}
