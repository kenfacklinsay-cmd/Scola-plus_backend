<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_can_create_staff_account_with_required_password_change(): void
    {
        $director = $this->createUser('DIRECTEUR');

        $response = $this->actingAs($director)->postJson('/api/staff-accounts', [
            'firstname' => 'Marie',
            'lastname' => 'Kabeya',
            'email' => 'marie.kabeya@example.test',
            'telephone' => '+243810000001',
            'role' => 'COMPTABILITE',
            'initial_password' => 'Temporaire2026!',
            'initial_password_confirmation' => 'Temporaire2026!',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', 'COMPTABILITE')
            ->assertJsonPath('data.must_change_password', true)
            ->assertJsonMissingPath('data.password');

        $this->assertDatabaseHas('users', [
            'email' => 'marie.kabeya@example.test',
            'role' => 'COMPTABILITE',
            'must_change_password' => true,
        ]);

        $account = User::query()->where('email', 'marie.kabeya@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('Temporaire2026!', $account->password));
    }

    public function test_secretariat_can_create_staff_account(): void
    {
        $secretary = $this->createUser('SECRETARIAT');

        $response = $this->actingAs($secretary)->postJson('/api/staff-accounts', [
            'firstname' => 'Jean',
            'lastname' => 'Mbuyi',
            'email' => 'jean.mbuyi@example.test',
            'role' => 'COMPTABILITE',
            'initial_password' => 'Temporaire2026!',
            'initial_password_confirmation' => 'Temporaire2026!',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'COMPTABILITE');
        $this->assertDatabaseHas('users', [
            'email' => 'jean.mbuyi@example.test',
            'role' => 'COMPTABILITE',
            'must_change_password' => true,
        ]);
    }

    public function test_director_can_issue_a_one_time_temporary_password_and_target_must_change_it(): void
    {
        $director = $this->createUser('DIRECTEUR');
        $account = User::factory()->create([
            'role' => 'ENSEIGNANT',
            'must_change_password' => false,
        ]);

        $response = $this->actingAs($director)
            ->postJson("/api/accounts/{$account->id}/temporary-password")
            ->assertOk()
            ->assertJsonPath('data.account_id', $account->id)
            ->assertJsonPath('message', 'Un mot de passe provisoire a été créé. Il ne sera affiché qu’une seule fois.');

        $temporaryPassword = $response->json('data.temporary_password');
        $this->assertIsString($temporaryPassword);
        $this->assertGreaterThanOrEqual(18, strlen($temporaryPassword));
        $this->assertTrue(Hash::check($temporaryPassword, $account->fresh()->password));
        $this->assertTrue($account->fresh()->must_change_password);
        $response->assertJsonMissingPath('data.password_hash');
    }

    public function test_account_administrator_cannot_reset_own_password_or_another_directors_password(): void
    {
        $secretary = $this->createUser('SECRETARIAT');
        $director = $this->createUser('DIRECTEUR');

        $this->actingAs($secretary)
            ->postJson("/api/accounts/{$secretary->id}/temporary-password")
            ->assertForbidden();

        $this->postJson("/api/accounts/{$director->id}/temporary-password")
            ->assertForbidden();
    }

    public function test_secretariat_can_create_teacher_and_parent_accounts(): void
    {
        $secretary = $this->createUser('SECRETARIAT');

        $this->actingAs($secretary)
            ->postJson('/api/teachers', [
                'firstname' => 'Aline',
                'lastname' => 'Ilunga',
                'email' => 'aline.secretariat@example.test',
                'teacher_registration_code' => 'ENS-SEC-001',
                'specialty' => 'Français',
                'initial_password' => 'Temporaire2026!',
                'initial_password_confirmation' => 'Temporaire2026!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.firstname', 'Aline');

        $parentProfile = ParentAccount::query()->create([
            'first_name' => 'Jean',
            'last_name' => 'Mbuyi',
            'telephone' => '+243810000009',
        ]);
        $student = Student::query()->create([
            'matricule' => 'E-SECRETARIAT-001',
            'first_name' => 'Mina',
            'last_name' => 'Mbuyi',
            'birth_date' => '2014-02-10',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $parentProfile->students()->attach($student->student_id, ['relationship' => 'Père']);

        $this->actingAs($secretary)
            ->postJson("/api/parents/{$parentProfile->parent_id}/account", [
                'email' => 'jean.parent@example.test',
                'initial_password' => 'Temporaire2026!',
                'initial_password_confirmation' => 'Temporaire2026!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'PARENT');
    }

    public function test_teacher_cannot_create_other_accounts(): void
    {
        $teacher = $this->createUser('ENSEIGNANT');

        $this->actingAs($teacher)
            ->postJson('/api/staff-accounts', [
                'firstname' => 'Jean',
                'lastname' => 'Mbuyi',
                'email' => 'teacher.created@example.test',
                'role' => 'SECRETARIAT',
                'initial_password' => 'Temporaire2026!',
                'initial_password_confirmation' => 'Temporaire2026!',
            ])
            ->assertForbidden();
    }

    public function test_secretariat_can_list_and_suspend_accounts_and_director_can_reactivate_them(): void
    {
        $secretary = $this->createUser('SECRETARIAT');
        $teacher = $this->createUser('ENSEIGNANT');
        $director = $this->createUser('DIRECTEUR');

        $this->actingAs($secretary)
            ->getJson('/api/accounts')
            ->assertOk()
            ->assertJsonFragment(['id' => $teacher->id, 'status' => 'ACTIF'])
            ->assertJsonMissing(['id' => $director->id]);

        $this->actingAs($secretary)
            ->patchJson("/api/accounts/{$teacher->id}/status", ['status' => 'INACTIF'])
            ->assertOk()
            ->assertJsonPath('data.status', 'INACTIF');

        $this->actingAs($teacher)
            ->getJson('/api/teacher/assignments')
            ->assertForbidden();

        $this->actingAs($director)
            ->patchJson("/api/accounts/{$teacher->id}/status", ['status' => 'ACTIF'])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIF');
    }

    public function test_secretariat_cannot_suspend_their_own_account_or_a_director(): void
    {
        $secretary = $this->createUser('SECRETARIAT');
        $director = $this->createUser('DIRECTEUR');

        $this->actingAs($secretary)
            ->patchJson("/api/accounts/{$secretary->id}/status", ['status' => 'INACTIF'])
            ->assertForbidden();

        $this->actingAs($secretary)
            ->patchJson("/api/accounts/{$director->id}/status", ['status' => 'INACTIF'])
            ->assertForbidden();
    }

    public function test_provisional_account_must_change_password_before_using_protected_api(): void
    {
        $parent = $this->createUser('PARENT', mustChangePassword: true);

        $this->actingAs($parent)
            ->getJson('/api/parent/children')
            ->assertStatus(423)
            ->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

        $this->putJson('/api/account/password', [
            'current_password' => 'MotDePasseTemporaire!',
            'password' => 'NouveauMotDePasse2026!',
            'password_confirmation' => 'NouveauMotDePasse2026!',
        ])->assertOk();

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.must_change_password', false);

        $this->assertFalse($parent->refresh()->must_change_password);
        $this->assertTrue(Hash::check('NouveauMotDePasse2026!', $parent->password));
    }

    public function test_director_can_create_teacher_and_link_teacher_profile(): void
    {
        $director = $this->createUser('DIRECTEUR');

        $response = $this->actingAs($director)->postJson('/api/teachers', [
            'firstname' => 'Aline',
            'lastname' => 'Ilunga',
            'email' => 'aline.ilunga@example.test',
            'teacher_registration_code' => 'ENS-001',
            'specialty' => 'Mathématiques',
            'initial_password' => 'Temporaire2026!',
            'initial_password_confirmation' => 'Temporaire2026!',
        ]);

        $response->assertCreated()->assertJsonPath('data.firstname', 'Aline');
        $this->assertDatabaseHas('users', [
            'email' => 'aline.ilunga@example.test',
            'role' => 'ENSEIGNANT',
            'must_change_password' => true,
        ]);
        $this->assertDatabaseHas('teachers', [
            'teacher_registration_code' => 'ENS-001',
            'specialty' => 'Mathématiques',
        ]);
    }

    public function test_director_can_create_parent_account_for_existing_family_profile(): void
    {
        $director = $this->createUser('DIRECTEUR');
        $parentProfile = ParentAccount::query()->create([
            'first_name' => 'Pierre',
            'last_name' => 'Mukeba',
            'telephone' => '+243810000002',
            'address' => 'Kinshasa',
        ]);
        $student = Student::query()->create([
            'matricule' => 'E-TEST-001',
            'first_name' => 'Amani',
            'last_name' => 'Mukeba',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $parentProfile->students()->attach($student->student_id, ['relationship' => 'Père']);

        $response = $this->actingAs($director)->postJson(
            "/api/parents/{$parentProfile->parent_id}/account",
            [
                'email' => 'pierre.mukeba@example.test',
                'initial_password' => 'Temporaire2026!',
                'initial_password_confirmation' => 'Temporaire2026!',
            ],
        );

        $response->assertCreated()->assertJsonPath('data.role', 'PARENT');
        $this->assertDatabaseHas('parents', [
            'parent_id' => $parentProfile->parent_id,
            'user_id' => User::query()->where('email', 'pierre.mukeba@example.test')->value('id'),
        ]);
    }

    public function test_director_can_list_parents_with_their_linked_students(): void
    {
        $director = $this->createUser('DIRECTEUR');
        $parent = ParentAccount::query()->create([
            'first_name' => 'Marie',
            'last_name' => 'Ilunga',
            'telephone' => '+243810000003',
        ]);
        $student = Student::query()->create([
            'matricule' => 'E-TEST-002',
            'first_name' => 'Aline',
            'last_name' => 'Ilunga',
            'birth_date' => '2013-04-15',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $parent->students()->attach($student->student_id, ['relationship' => 'Mère']);

        $this->actingAs($director)
            ->getJson('/api/parents')
            ->assertOk()
            ->assertJsonPath('data.0.first_name', 'Marie')
            ->assertJsonPath('data.0.students.0.first_name', 'Aline');
    }

    private function createUser(string $role, bool $mustChangePassword = false): User
    {
        return User::factory()->create([
            'password' => 'MotDePasseTemporaire!',
            'role' => $role,
            'must_change_password' => $mustChangePassword,
        ]);
    }
}
