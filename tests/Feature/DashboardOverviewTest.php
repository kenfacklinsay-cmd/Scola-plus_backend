<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_receives_school_finance_and_class_average_summary(): void
    {
        [$schoolYear, $period, $schoolClass] = $this->createSchoolSetup();
        $firstStudent = $this->createStudent('E-001', 'Amani', $schoolClass);
        $secondStudent = $this->createStudent('E-002', 'Béatrice', $schoolClass);
        $this->createAssessmentGrades($schoolClass, $period, [
            [$firstStudent, 16],
            [$secondStudent, 12],
        ]);
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $response = $this->actingAs($director)->getJson(
            "/api/dashboard/overview?school_year_id={$schoolYear->school_year_id}&period_id={$period->period_id}",
        );

        $response->assertOk()
            ->assertJsonPath('data.school_year.label', '2026-2027')
            ->assertJsonPath('data.period.label', 'Séquence 1')
            ->assertJsonPath('data.metrics.students_enrolled', 2)
            ->assertJsonPath('data.metrics.classes_active', 1)
            ->assertJsonPath('data.metrics.teachers', 1)
            ->assertJsonPath('data.class_averages.0.class_name', '6ème A')
            ->assertJsonPath('data.class_averages.0.students_count', 2)
            ->assertJsonPath('data.class_averages.0.students_with_average_count', 2)
            ->assertJsonPath('data.class_averages.0.average', 14);
    }

    public function test_non_director_cannot_access_the_dashboard_overview(): void
    {
        [$schoolYear, $period] = $this->createSchoolSetup();
        $accountant = User::factory()->create(['role' => 'COMPTABILITE']);

        $this->actingAs($accountant)->getJson(
            "/api/dashboard/overview?school_year_id={$schoolYear->school_year_id}&period_id={$period->period_id}",
        )->assertForbidden();
    }

    public function test_dashboard_rejects_a_period_from_a_different_school_year(): void
    {
        [$schoolYear] = $this->createSchoolSetup();
        $otherYear = SchoolYear::query()->create([
            'label' => '2027-2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'status' => 'UPCOMING',
        ]);
        $otherPeriod = Period::query()->create([
            'label' => 'Séquence 1',
            'start_date' => '2027-09-01',
            'end_date' => '2027-11-30',
            'school_year_id' => $otherYear->school_year_id,
        ]);
        $director = User::factory()->create(['role' => 'DIRECTEUR']);

        $this->actingAs($director)->getJson(
            "/api/dashboard/overview?school_year_id={$schoolYear->school_year_id}&period_id={$otherPeriod->period_id}",
        )->assertJsonValidationErrors('period_id')
            ->assertJsonPath(
                'errors.period_id.0',
                'La période doit appartenir à l’année scolaire sélectionnée.',
            );
    }

    /** @return array{SchoolYear, Period, SchoolClass} */
    private function createSchoolSetup(): array
    {
        $schoolYear = SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
        $period = Period::query()->create([
            'label' => 'Séquence 1',
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'school_year_id' => $schoolYear->school_year_id,
        ]);
        $schoolClass = SchoolClass::query()->create([
            'name' => '6ème A',
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $schoolYear->school_year_id,
        ]);

        return [$schoolYear, $period, $schoolClass];
    }

    private function createStudent(string $matricule, string $firstName, SchoolClass $schoolClass): Student
    {
        $student = Student::query()->create([
            'matricule' => $matricule,
            'last_name' => 'Mukeba',
            'first_name' => $firstName,
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

    /** @param array<int, array{Student, float}> $studentGrades */
    private function createAssessmentGrades(SchoolClass $schoolClass, Period $period, array $studentGrades): void
    {
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-GRADE-001',
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

        foreach ($studentGrades as [$student, $value]) {
            Grade::query()->create([
                'student_id' => $student->student_id,
                'assessment_id' => $assessment->assessment_id,
                'value' => $value,
            ]);
        }
    }
}
