<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Installment;
use App\Models\ParentAccount;
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

class ParentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_can_view_child_results_finance_and_receipt_but_not_an_unlinked_child(): void
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
            'label' => 'Trimestre 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $student = $this->createStudent('E-PARENT-001', $schoolClass);
        $teacherUser = User::factory()->create([
            'role' => 'ENSEIGNANT',
            'firstname' => 'Teacher',
            'lastname' => 'User',
        ]);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-PARENT-001',
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
        Grade::query()->create([
            'student_id' => $student->student_id,
            'assessment_id' => $assessment->assessment_id,
            'value' => 15,
        ]);
        $fee = TuitionFee::query()->create([
            'class_id' => $schoolClass->class_id,
            'school_year_id' => $schoolYear->school_year_id,
            'total_amount' => 150000,
        ]);
        $installment = Installment::query()->create([
            'tuition_fee_id' => $fee->tuition_fee_id,
            'label' => 'Tranche 1',
            'amount' => 150000,
            'due_date' => '2026-10-31',
        ]);
        $payment = Payment::query()->create([
            'student_id' => $student->student_id,
            'installment_id' => $installment->installment_id,
            'receipt_number' => 'SP-2026-00000001',
            'amount' => 75000,
            'payment_date' => '2026-10-21 10:00:00',
            'payment_method' => 'Espèces',
            'status' => 'PAID',
        ]);
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
            ->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonPath('data.0.enrollments.0.class.name', '6ème A')
            ->assertJsonPath('data.0.enrollments.0.period.id', $period->period_id)
            ->assertJsonPath('data.0.enrollments.0.average', 15)
            ->assertJsonPath('data.0.enrollments.0.rank', 1);

        $this->actingAs($parentUser)
            ->getJson("/api/parent/children/{$student->student_id}/periods/{$period->period_id}")
            ->assertOk()
            ->assertJsonPath('data.subjects.0.name', 'Mathématiques')
            ->assertJsonPath('data.subjects.0.teacher', 'Teacher User')
            ->assertJsonPath('data.subjects.0.assessments.0.value', 15);

        $this->actingAs($parentUser)
            ->getJson("/api/parent/children/{$student->student_id}/tuition")
            ->assertOk()
            ->assertJsonPath('data.balances.0.paid_total', '75000.00')
            ->assertJsonPath('data.payments.0.receipt_number', 'SP-2026-00000001');

        $this->actingAs($parentUser)
            ->get("/api/parent/payments/{$payment->payment_id}/receipt/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $unrelatedParent = User::factory()->create(['role' => 'PARENT']);
        ParentAccount::query()->create([
            'user_id' => $unrelatedParent->id,
            'first_name' => 'Autre',
            'last_name' => 'Parent',
            'telephone' => '+243810000003',
            'address' => 'Kinshasa',
        ]);
        $this->actingAs($unrelatedParent)
            ->getJson("/api/parent/children/{$student->student_id}/periods/{$period->period_id}")
            ->assertForbidden();
        $this->get("/api/parent/payments/{$payment->payment_id}/receipt/pdf")
            ->assertNotFound();
    }

    private function createStudent(string $matricule, SchoolClass $schoolClass): Student
    {
        $student = Student::query()->create([
            'matricule' => $matricule,
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
            'school_year_id' => $schoolClass->school_year_id,
            'enrollment_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);

        return $student;
    }
}
