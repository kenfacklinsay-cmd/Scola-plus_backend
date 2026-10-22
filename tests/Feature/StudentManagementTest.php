<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TuitionFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_update_a_student_profile(): void
    {
        $student = $this->createStudent();
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);

        $response = $this->actingAs($secretary)->patchJson("/api/students/{$student->student_id}", [
            'first_name' => 'Aline',
            'address' => 'Gombe, Kinshasa',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.first_name', 'Aline')
            ->assertJsonPath('data.address', 'Gombe, Kinshasa');
        $this->assertDatabaseHas('students', [
            'student_id' => $student->student_id,
            'first_name' => 'Aline',
            'address' => 'Gombe, Kinshasa',
        ]);
    }

    public function test_director_can_load_school_year_and_class_options_for_registration(): void
    {
        $director = User::factory()->create(['role' => 'DIRECTEUR']);
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

        $this->actingAs($director)
            ->getJson('/api/school-years')
            ->assertOk()
            ->assertJsonPath('data.0.id', $schoolYear->school_year_id)
            ->assertJsonPath('data.0.label', '2026-2027');

        $this->actingAs($director)
            ->getJson("/api/classes?school_year_id={$schoolYear->school_year_id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $schoolClass->class_id)
            ->assertJsonPath('data.0.school_year.id', $schoolYear->school_year_id);
    }

    public function test_accounting_user_can_list_school_years_for_their_dashboard(): void
    {
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);

        $this->actingAs($accountant)
            ->getJson('/api/school-years')
            ->assertOk()
            ->assertJsonPath('data.0.id', $schoolYear->school_year_id);
    }

    public function test_teacher_can_only_list_students_in_assigned_classes(): void
    {
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-ACCESS-01',
            'specialty' => 'Mathématiques',
        ]);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $assignedClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $otherClass = SchoolClass::query()->create([
            'name' => '6ème B',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $subject = Subject::query()->create(['name' => 'Mathématiques', 'coefficient' => 2]);
        Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $assignedClass->class_id,
            'subject_id' => $subject->subject_id,
        ]);
        $assignedStudent = $this->createStudent('E-TEACHER-001');
        $unassignedStudent = $this->createStudent('E-TEACHER-002');
        $this->createEnrollment($assignedStudent, $assignedClass);
        $this->createEnrollment($unassignedStudent, $otherClass);

        $this->actingAs($teacherUser)
            ->getJson('/api/teacher/students?school_year_id='.$schoolYear->school_year_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matricule', 'E-TEACHER-001');

        $this->actingAs($teacherUser)
            ->getJson('/api/teacher/students?class_id='.$otherClass->class_id)
            ->assertForbidden();
    }

    public function test_secretariat_can_create_a_trimester_for_a_school_year(): void
    {
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);

        $this->actingAs($secretary)
            ->postJson('/api/periods', [
                'label' => 'Trimestre 1',
                'start_date' => '2026-09-01',
                'end_date' => '2026-11-30',
                'school_year_id' => $schoolYear->school_year_id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.label', 'Trimestre 1')
            ->assertJsonPath('data.school_year_id', $schoolYear->school_year_id);
    }

    public function test_accounting_user_can_search_only_students_in_the_fee_class(): void
    {
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $feeClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $otherClass = SchoolClass::query()->create([
            'name' => '6ème B',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $fee = TuitionFee::query()->create([
            'class_id' => $feeClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'total_amount' => 200000,
        ]);
        $eligibleStudent = $this->createStudent('E-PAY-001');
        $otherStudent = $this->createStudent('E-PAY-002');
        $this->createEnrollment($eligibleStudent, $feeClass);
        $this->createEnrollment($otherStudent, $otherClass);

        $this->actingAs($accountant)
            ->getJson("/api/tuition-fees/{$fee->tuition_fee_id}/students?search=E-PAY-001")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $eligibleStudent->student_id)
            ->assertJsonPath('data.0.matricule', 'E-PAY-001');

        $this->actingAs($accountant)
            ->getJson("/api/tuition-fees/{$fee->tuition_fee_id}/students?search=E-PAY-002")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_accounting_user_can_record_a_partial_payment_and_overpayment_is_rejected(): void
    {
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);
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
        $student = $this->createStudent('E-PAYMENT-001');
        $this->createEnrollment($student, $schoolClass);
        $fee = TuitionFee::query()->create([
            'class_id' => $schoolClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'total_amount' => 200000,
        ]);
        $installment = Installment::query()->create([
            'tuition_fee_id' => $fee->tuition_fee_id,
            'label' => 'Tranche 1',
            'amount' => 200000,
            'due_date' => '2026-10-31',
        ]);

        $this->actingAs($accountant)
            ->postJson('/api/payments', [
                'student_id' => $student->student_id,
                'installment_id' => $installment->installment_id,
                'amount' => 50000,
                'payment_date' => '2026-10-21',
                'payment_method' => 'Espèces',
            ])
            ->assertCreated()
            ->assertJsonPath('data.student.id', $student->student_id)
            ->assertJsonPath('data.amount', '50000.00')
            ->assertJsonPath('data.receipt_number', 'SP-2026-00000001');

        $this->actingAs($accountant)
            ->postJson('/api/payments', [
                'student_id' => $student->student_id,
                'installment_id' => $installment->installment_id,
                'amount' => 150001,
                'payment_date' => '2026-10-21',
                'payment_method' => 'Espèces',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('amount');
    }

    public function test_teacher_cannot_remove_an_existing_grade_by_saving_a_blank_value(): void
    {
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-GRADE-01',
            'specialty' => 'Mathématiques',
        ]);
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
        $student = $this->createStudent('E-GRADE-001');
        $this->createEnrollment($student, $schoolClass);
        $subject = Subject::query()->create(['name' => 'Mathématiques', 'coefficient' => 2]);
        $assignment = Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $schoolClass->class_id,
            'subject_id' => $subject->subject_id,
        ]);
        $period = Period::query()->create([
            'label' => 'Trimestre 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $assessment = Assessment::query()->create([
            'assignment_id' => $assignment->assignment_id,
            'period_id' => $period->period_id,
            'label' => 'Devoir 1',
            'assessment_date' => '2026-10-01',
        ]);
        Grade::query()->create([
            'student_id' => $student->student_id,
            'assessment_id' => $assessment->assessment_id,
            'value' => 15,
        ]);

        $this->actingAs($teacherUser)
            ->putJson("/api/assessments/{$assessment->assessment_id}/grades", [
                'grades' => [[
                    'student_id' => $student->student_id,
                    'value' => null,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('missing_count', 1);

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->student_id,
            'assessment_id' => $assessment->assessment_id,
            'value' => 15,
        ]);
    }

    public function test_director_can_create_a_school_year_and_its_first_class(): void
    {
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $yearResponse = $this->actingAs($director)->postJson('/api/school-years', [
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $yearResponse->assertCreated()->assertJsonPath('data.label', '2026-2027');
        $yearId = $yearResponse->json('data.id');

        $this->postJson('/api/classes', [
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $yearId,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', '6ème A')
            ->assertJsonPath('data.school_year.id', $yearId);
    }

    public function test_parent_cannot_update_a_student_profile(): void
    {
        $student = $this->createStudent();
        $parent = User::factory()->create(['role' => 'PARENT']);

        $this->actingAs($parent)
            ->patchJson("/api/students/{$student->student_id}", ['first_name' => 'Aline'])
            ->assertForbidden();

        $this->assertDatabaseHas('students', [
            'student_id' => $student->student_id,
            'first_name' => 'Amani',
        ]);
    }

    public function test_student_profile_update_rejects_a_duplicate_matricule(): void
    {
        $student = $this->createStudent('E-001');
        $this->createStudent('E-002');
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $this->actingAs($director)
            ->patchJson("/api/students/{$student->student_id}", ['matricule' => 'E-002'])
            ->assertJsonValidationErrors('matricule')
            ->assertJsonPath('errors.matricule.0', 'Ce matricule est déjà utilisé.');

        $this->assertDatabaseHas('students', ['student_id' => $student->student_id, 'matricule' => 'E-001']);
    }

    public function test_director_can_transfer_an_enrollment_when_no_school_records_exist(): void
    {
        [$student, $schoolYear, $sourceClass, $targetClass, $enrollment] = $this->createEnrollmentFixture();
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $response = $this->actingAs($director)->patchJson(
            "/api/students/{$student->student_id}/enrollments/{$enrollment->enrollment_id}",
            ['class_id' => $targetClass->class_id],
        );

        $response->assertOk()->assertJsonPath('data.class.id', $targetClass->class_id);
        $this->assertDatabaseHas('enrollments', [
            'enrollment_id' => $enrollment->enrollment_id,
            'class_id' => $targetClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $this->assertDatabaseMissing('enrollments', [
            'enrollment_id' => $enrollment->enrollment_id,
            'class_id' => $sourceClass->class_id,
        ]);
    }

    public function test_transfer_is_rejected_when_the_target_class_is_full(): void
    {
        [$student, , , $targetClass, $enrollment] = $this->createEnrollmentFixture(targetCapacity: 1);
        $this->createEnrollment($this->createStudent('E-OTHER'), $targetClass);
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);

        $this->actingAs($secretary)->patchJson(
            "/api/students/{$student->student_id}/enrollments/{$enrollment->enrollment_id}",
            ['class_id' => $targetClass->class_id],
        )->assertJsonValidationErrors('class_id');

        $this->assertDatabaseHas('enrollments', [
            'enrollment_id' => $enrollment->enrollment_id,
            'class_id' => $enrollment->class_id,
        ]);
    }

    public function test_transfer_is_rejected_when_the_student_has_grades_for_the_school_year(): void
    {
        [$student, $schoolYear, , $targetClass, $enrollment] = $this->createEnrollmentFixture();
        $this->createGradeForStudent($student, $schoolYear, $enrollment->schoolClass);
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $this->actingAs($director)->patchJson(
            "/api/students/{$student->student_id}/enrollments/{$enrollment->enrollment_id}",
            ['class_id' => $targetClass->class_id],
        )->assertJsonValidationErrors('class_id');

        $this->assertDatabaseHas('enrollments', [
            'enrollment_id' => $enrollment->enrollment_id,
            'class_id' => $enrollment->class_id,
        ]);
    }

    public function test_transfer_is_rejected_when_the_student_has_payments_for_the_school_year(): void
    {
        [$student, $schoolYear, $sourceClass, $targetClass, $enrollment] = $this->createEnrollmentFixture();
        $fee = TuitionFee::query()->create([
            'class_id' => $sourceClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'total_amount' => 100000,
        ]);
        $installment = Installment::query()->create([
            'tuition_fee_id' => $fee->tuition_fee_id,
            'label' => 'Tranche 1',
            'amount' => 100000,
            'due_date' => '2026-10-01',
        ]);
        Payment::query()->create([
            'student_id' => $student->student_id,
            'installment_id' => $installment->installment_id,
            'receipt_number' => 'SP-2026-00000001',
            'amount' => 10000,
            'payment_date' => '2026-10-01 10:00:00',
            'payment_method' => 'CASH',
            'status' => 'PAID',
        ]);
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $this->actingAs($director)->patchJson(
            "/api/students/{$student->student_id}/enrollments/{$enrollment->enrollment_id}",
            ['class_id' => $targetClass->class_id],
        )->assertJsonValidationErrors('class_id');

        $this->assertDatabaseHas('enrollments', [
            'enrollment_id' => $enrollment->enrollment_id,
            'class_id' => $sourceClass->class_id,
        ]);
    }

    public function test_enrollment_cannot_be_transferred_for_another_student(): void
    {
        [$student, , , $targetClass, $enrollment] = $this->createEnrollmentFixture();
        $otherStudent = $this->createStudent('E-OTHER');
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);

        $this->actingAs($secretary)->patchJson(
            "/api/students/{$otherStudent->student_id}/enrollments/{$enrollment->enrollment_id}",
            ['class_id' => $targetClass->class_id],
        )->assertNotFound();

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->student_id,
            'enrollment_id' => $enrollment->enrollment_id,
        ]);
    }

    /** @return array{Student, SchoolYear, SchoolClass, SchoolClass, Enrollment} */
    private function createEnrollmentFixture(?int $targetCapacity = null): array
    {
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $sourceClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $targetClass = SchoolClass::query()->create([
            'name' => '6ème B',
            'level' => 'Collège',
            'capacity' => $targetCapacity,
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $student = $this->createStudent();
        $enrollment = $this->createEnrollment($student, $sourceClass);

        return [$student, $schoolYear, $sourceClass, $targetClass, $enrollment];
    }

    private function createStudent(string $matricule = 'E-TEST-001'): Student
    {
        return Student::query()->create([
            'matricule' => $matricule,
            'last_name' => 'Mukeba',
            'first_name' => 'Amani',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
    }

    private function createEnrollment(Student $student, SchoolClass $schoolClass): Enrollment
    {
        return Enrollment::query()->create([
            'student_id' => $student->student_id,
            'class_id' => $schoolClass->class_id,
            'school_year_id' => $schoolClass->school_year_id,
            'enrollment_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);
    }

    private function createGradeForStudent(Student $student, SchoolYear $schoolYear, SchoolClass $schoolClass): Grade
    {
        $period = Period::query()->create([
            'label' => 'Séquence 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-001',
            'specialty' => 'Mathématiques',
        ]);
        $subject = Subject::query()->create(['name' => 'Mathématiques', 'coefficient' => 2]);
        $assignment = Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $schoolClass->class_id,
            'subject_id' => $subject->subject_id,
        ]);
        $assessment = Assessment::query()->create([
            'assignment_id' => $assignment->assignment_id,
            'period_id' => $period->period_id,
            'label' => 'Interrogation 1',
            'assessment_date' => '2026-10-01',
        ]);

        return Grade::query()->create([
            'student_id' => $student->student_id,
            'assessment_id' => $assessment->assessment_id,
            'value' => 16,
        ]);
    }
}
