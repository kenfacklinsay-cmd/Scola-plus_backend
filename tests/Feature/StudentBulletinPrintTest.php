<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\ParentAccount;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBulletinPrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_print_a_child_bulletin_with_weighted_average_and_missing_grade(): void
    {
        [$student, $period] = $this->createStudentWithGrades();
        $parentUser = User::factory()->create(['role' => 'PARENT']);
        $parent = ParentAccount::query()->create([
            'user_id' => $parentUser->id,
            'first_name' => 'Pierre',
            'last_name' => 'Mukeba',
            'telephone' => '+243810000002',
            'address' => 'Kinshasa',
        ]);
        $parent->students()->attach($student->student_id, ['relationship' => 'Père']);

        $this->actingAs($parentUser)
            ->get("/api/students/{$student->student_id}/periods/{$period->period_id}/bulletin/print")
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8')
            ->assertSee('Bulletin de notes')
            ->assertSee('Amani Mukeba')
            ->assertSee('Mathématiques')
            ->assertSee('16,00')
            ->assertSee('14,67')
            ->assertSee('—')
            ->assertSee('Bien')
            ->assertSee('Imprimer / Enregistrer en PDF');
    }

    public function test_parent_cannot_print_a_bulletin_for_an_unlinked_student(): void
    {
        [$student, $period] = $this->createStudentWithGrades();
        $parentUser = User::factory()->create(['role' => 'PARENT']);

        $this->actingAs($parentUser)
            ->get("/api/students/{$student->student_id}/periods/{$period->period_id}/bulletin/print")
            ->assertNotFound();

        $this->get("/api/students/{$student->student_id}/periods/{$period->period_id}/bulletin/pdf")
            ->assertNotFound();
    }

    public function test_unauthenticated_bulletin_requests_return_json_unauthorized_instead_of_redirecting_to_a_missing_login_route(): void
    {
        [$student, $period] = $this->createStudentWithGrades();

        $this->get("/api/students/{$student->student_id}/periods/{$period->period_id}/bulletin/print")
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_parent_can_download_a_child_bulletin_as_a_pdf(): void
    {
        [$student, $period] = $this->createStudentWithGrades();
        $parentUser = User::factory()->create(['role' => 'PARENT']);
        $parent = ParentAccount::query()->create([
            'user_id' => $parentUser->id,
            'first_name' => 'Pierre',
            'last_name' => 'Mukeba',
            'telephone' => '+243810000002',
            'address' => 'Kinshasa',
        ]);
        $parent->students()->attach($student->student_id, ['relationship' => 'Père']);

        $response = $this->actingAs($parentUser)
            ->get("/api/students/{$student->student_id}/periods/{$period->period_id}/bulletin/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('bulletin-e-test-001-sequence-1.pdf', $response->headers->get('content-disposition'));
    }

    /** @return array{Student, Period} */
    private function createStudentWithGrades(): array
    {
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $schoolClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $period = Period::query()->create([
            'label' => 'Séquence 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $student = Student::query()->create([
            'matricule' => 'E-TEST-001',
            'last_name' => 'Mukeba',
            'first_name' => 'Amani',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        Enrollment::query()->create([
            'student_id' => $student->student_id,
            'class_id' => $schoolClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'enrollment_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-001',
            'specialty' => 'Lettres',
        ]);

        $maths = Subject::query()->create(['name' => 'Mathématiques', 'coefficient' => 2]);
        $mathAssignment = Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $schoolClass->class_id,
            'subject_id' => $maths->subject_id,
        ]);
        $mathAssessment = Assessment::query()->create([
            'assignment_id' => $mathAssignment->assignment_id,
            'period_id' => $period->period_id,
            'label' => 'Interrogation 1',
            'assessment_date' => '2026-10-01',
        ]);
        Assessment::query()->create([
            'assignment_id' => $mathAssignment->assignment_id,
            'period_id' => $period->period_id,
            'label' => 'Interrogation 2',
            'assessment_date' => '2026-10-15',
        ]);
        Grade::query()->create([
            'student_id' => $student->student_id,
            'assessment_id' => $mathAssessment->assessment_id,
            'value' => 16,
            'appreciation' => 'Très bon travail',
        ]);

        $french = Subject::query()->create(['name' => 'Français', 'coefficient' => 1]);
        $frenchAssignment = Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $schoolClass->class_id,
            'subject_id' => $french->subject_id,
        ]);
        $frenchAssessment = Assessment::query()->create([
            'assignment_id' => $frenchAssignment->assignment_id,
            'period_id' => $period->period_id,
            'label' => 'Dictée',
            'assessment_date' => '2026-10-05',
        ]);
        Grade::query()->create([
            'student_id' => $student->student_id,
            'assessment_id' => $frenchAssessment->assessment_id,
            'value' => 12,
        ]);

        return [$student, $period];
    }
}
