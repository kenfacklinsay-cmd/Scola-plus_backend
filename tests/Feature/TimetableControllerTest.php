<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_secretariat_can_create_a_schedule_entry_and_overlaps_are_rejected(): void
    {
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);
        $teacher = $this->createTeacher('ENS-TIME-01');
        $year = $this->createSchoolYear();
        $firstClass = $this->createClass($year, '6ème A');
        $secondClass = $this->createClass($year, '6ème B');
        $subject = Subject::query()->create(['name' => 'Mathématiques', 'coefficient' => 2]);
        $otherSubject = Subject::query()->create(['name' => 'Français', 'coefficient' => 1]);
        $firstAssignment = $this->createAssignment($teacher, $firstClass, $subject);
        $secondAssignment = $this->createAssignment($teacher, $secondClass, $otherSubject);

        $response = $this->actingAs($secretary)->postJson('/api/schedules', [
            'assignment_id' => $firstAssignment->assignment_id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room' => 'Salle 2',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.assignment.class.name', '6ème A')
            ->assertJsonPath('data.start_time', '08:00');

        $this->actingAs($secretary)->postJson('/api/schedules', [
            'assignment_id' => $secondAssignment->assignment_id,
            'day_of_week' => 1,
            'start_time' => '08:30',
            'end_time' => '09:30',
        ])->assertUnprocessable();
    }

    public function test_teacher_can_only_read_their_own_schedule(): void
    {
        $teacher = $this->createTeacher('ENS-TIME-02');
        $otherTeacher = $this->createTeacher('ENS-TIME-03');
        $year = $this->createSchoolYear();
        $schoolClass = $this->createClass($year, '5ème A');
        $subject = Subject::query()->create(['name' => 'Histoire', 'coefficient' => 1]);
        $assignment = $this->createAssignment($teacher, $schoolClass, $subject);
        $otherAssignment = $this->createAssignment(
            $otherTeacher,
            $this->createClass($year, '5ème B'),
            $subject,
        );
        TimetableEntry::query()->create([
            'assignment_id' => $assignment->assignment_id,
            'day_of_week' => 2,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
        TimetableEntry::query()->create([
            'assignment_id' => $otherAssignment->assignment_id,
            'day_of_week' => 2,
            'start_time' => '11:00',
            'end_time' => '12:00',
        ]);

        $this->actingAs($teacher->user)
            ->getJson('/api/teacher/schedules')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignment.class.name', '5ème A');
    }

    public function test_teacher_schedule_can_be_filtered_by_school_year(): void
    {
        $teacher = $this->createTeacher('ENS-TIME-04');
        $firstYear = $this->createSchoolYear();
        $secondYear = SchoolYear::query()->create([
            'label' => '2027-2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'status' => 'UPCOMING',
        ]);
        $subject = Subject::query()->create(['name' => 'Sciences', 'coefficient' => 1]);

        foreach ([
            [$firstYear, '6ème A'],
            [$secondYear, '6ème B'],
        ] as [$year, $className]) {
            $assignment = $this->createAssignment(
                $teacher,
                $this->createClass($year, $className),
                $subject,
            );
            TimetableEntry::query()->create([
                'assignment_id' => $assignment->assignment_id,
                'day_of_week' => 3,
                'start_time' => '08:00',
                'end_time' => '09:00',
            ]);
        }

        $this->actingAs($teacher->user)
            ->getJson('/api/teacher/schedules?school_year_id='.$firstYear->school_year_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.assignment.class.name', '6ème A');
    }

    public function test_overlapping_classes_in_different_school_years_are_allowed(): void
    {
        $secretary = User::factory()->create(['role' => 'SECRETARIAT']);
        $teacher = $this->createTeacher('ENS-TIME-05');
        $firstYear = $this->createSchoolYear();
        $secondYear = SchoolYear::query()->create([
            'label' => '2027-2028',
            'start_date' => '2027-09-01',
            'end_date' => '2028-06-30',
            'status' => 'UPCOMING',
        ]);
        $subject = Subject::query()->create(['name' => 'Arts', 'coefficient' => 1]);
        $firstAssignment = $this->createAssignment(
            $teacher,
            $this->createClass($firstYear, '5ème A'),
            $subject,
        );
        $secondAssignment = $this->createAssignment(
            $teacher,
            $this->createClass($secondYear, '5ème B'),
            $subject,
        );

        $this->actingAs($secretary)->postJson('/api/schedules', [
            'assignment_id' => $firstAssignment->assignment_id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertCreated();

        $this->actingAs($secretary)->postJson('/api/schedules', [
            'assignment_id' => $secondAssignment->assignment_id,
            'day_of_week' => 1,
            'start_time' => '08:00',
            'end_time' => '09:00',
        ])->assertCreated();
    }

    private function createTeacher(string $code): Teacher
    {
        $user = User::factory()->create(['role' => 'ENSEIGNANT']);

        return Teacher::query()->create([
            'user_id' => $user->id,
            'teacher_registration_code' => $code,
            'specialty' => 'Enseignement',
        ])->load('user');
    }

    private function createSchoolYear(): SchoolYear
    {
        return SchoolYear::query()->create([
            'label' => '2026-2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-06-30',
            'status' => 'ONGOING',
        ]);
    }

    private function createClass(SchoolYear $year, string $name): SchoolClass
    {
        return SchoolClass::query()->create([
            'name' => $name,
            'level' => 'Collège',
            'capacity' => 30,
            'school_year_id' => $year->school_year_id,
        ]);
    }

    private function createAssignment(Teacher $teacher, SchoolClass $schoolClass, Subject $subject): Assignment
    {
        return Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $schoolClass->class_id,
            'subject_id' => $subject->subject_id,
        ]);
    }
}
