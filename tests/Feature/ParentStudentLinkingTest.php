<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentStudentLinkingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_can_reuse_a_parent_profile_and_existing_students_can_be_linked_to_it(): void
    {
        $year = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $class = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $year->school_year_id,
        ]);
        $parentUser = User::factory()->create(['role' => 'PARENT']);
        $parent = ParentAccount::query()->create([
            'user_id' => $parentUser->id,
            'first_name' => 'Pierre',
            'last_name' => 'Mukeba',
            'telephone' => '+243810000002',
            'address' => 'Kinshasa',
        ]);
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $this->actingAs($director)
            ->getJson('/api/parents')
            ->assertOk()
            ->assertJsonPath('data.0.account_email', $parentUser->email);

        $this->actingAs($director)
            ->postJson('/api/students', [
                'matricule' => 'E-PARENT-NEW',
                'last_name' => 'Mukeba',
                'first_name' => 'Amani',
                'birth_date' => '2012-03-12',
                'birth_place' => 'Kinshasa',
                'gender' => 'F',
                'address' => 'Kinshasa',
                'class_id' => $class->class_id,
                'school_year_id' => $year->school_year_id,
                'parent_id' => $parent->parent_id,
                'parent' => ['relationship' => 'Père'],
            ])
            ->assertCreated();

        $existingStudent = Student::query()->create([
            'matricule' => 'E-PARENT-EXISTING',
            'last_name' => 'Mukeba',
            'first_name' => 'Beni',
            'birth_date' => '2014-04-20',
            'birth_place' => 'Kinshasa',
            'gender' => 'M',
            'address' => 'Kinshasa',
        ]);
        Enrollment::query()->create([
            'student_id' => $existingStudent->student_id,
            'class_id' => $class->class_id,
            'school_year_id' => $year->school_year_id,
            'enrollment_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($director)
            ->postJson("/api/parents/{$parent->parent_id}/students/{$existingStudent->student_id}")
            ->assertOk();

        $this->assertDatabaseCount('parents', 1);
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->parent_id,
            'student_id' => $existingStudent->student_id,
        ]);

        $this->actingAs($parentUser)
            ->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
