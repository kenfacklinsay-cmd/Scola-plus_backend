<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_can_take_attendance_and_parent_receives_an_in_app_absence_notification(): void
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
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);
        $teacher = Teacher::query()->create([
            'user_id' => $teacherUser->id,
            'teacher_registration_code' => 'ENS-ATT-001',
            'specialty' => 'Mathématiques',
        ]);
        Assignment::query()->create([
            'teacher_id' => $teacher->teacher_id,
            'class_id' => $class->class_id,
            'subject_id' => Subject::query()->create([
                'name' => 'Mathématiques',
                'coefficient' => 2,
            ])->subject_id,
        ]);
        $student = Student::query()->create([
            'matricule' => 'E-ATT-001',
            'last_name' => 'Mukeba',
            'first_name' => 'Amani',
            'birth_date' => '2012-03-12',
            'birth_place' => 'Kinshasa',
            'gender' => 'F',
            'address' => 'Kinshasa',
        ]);
        $enrollment = Enrollment::query()->create([
            'student_id' => $student->student_id,
            'class_id' => $class->class_id,
            'school_year_id' => $year->school_year_id,
            'enrollment_date' => '2026-09-01',
            'status' => 'ACTIVE',
        ]);
        $parentUser = User::factory()->create(['role' => 'PARENT']);
        $parent = ParentAccount::query()->create([
            'user_id' => $parentUser->id,
            'first_name' => 'Pierre',
            'last_name' => 'Mukeba',
            'telephone' => '+243810000002',
        ]);
        $parent->students()->attach($student->student_id, ['relationship' => 'Père']);
        $date = now()->toDateString();

        $this->actingAs($teacherUser)
            ->getJson('/api/teacher/attendance?class_id='.$class->class_id.'&date='.$date)
            ->assertOk()
            ->assertJsonPath('data.students.0.id', $student->student_id)
            ->assertJsonPath('data.students.0.status', 'PRESENT');

        $this->actingAs($teacherUser)
            ->putJson('/api/teacher/attendance', [
                'class_id' => $class->class_id,
                'date' => $date,
                'records' => [
                    ['student_id' => $student->student_id, 'status' => 'ABSENT', 'notes' => 'Non signalée'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.parent_notifications_count', 1);

        $this->assertDatabaseHas('attendance_records', [
            'enrollment_id' => $enrollment->enrollment_id,
            'attendance_date' => $date.' 00:00:00',
            'status' => 'ABSENT',
            'marked_by' => $teacherUser->id,
        ]);
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($teacherUser)
            ->putJson('/api/teacher/attendance', [
                'class_id' => $class->class_id,
                'date' => $date,
                'records' => [
                    ['student_id' => $student->student_id, 'status' => 'ABSENT'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.parent_notifications_count', 0);
        $this->assertDatabaseCount('notifications', 1);

        $this->actingAs($parentUser)
            ->getJson('/api/parent/notifications')
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.data.student_name', 'Amani Mukeba')
            ->assertJsonPath('data.0.data.class_name', '6ème A');

        $notificationId = $parentUser->unreadNotifications()->firstOrFail()->id;
        $this->actingAs($parentUser)
            ->patchJson("/api/parent/notifications/{$notificationId}/read")
            ->assertOk();
        $this->assertSame(0, $parentUser->fresh()->unreadNotifications()->count());

        $director = User::factory()->create(['role' => 'DIRECTEUR']);
        $this->actingAs($director)
            ->getJson('/api/attendance/absences?date='.$date)
            ->assertOk()
            ->assertJsonPath('data.0.student.first_name', 'Amani')
            ->assertJsonPath('data.0.class.name', '6ème A');
    }

    public function test_teacher_cannot_mark_attendance_for_a_class_not_assigned_to_them(): void
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
        $teacherUser = User::factory()->create(['role' => 'ENSEIGNANT']);

        $this->actingAs($teacherUser)
            ->getJson('/api/teacher/attendance?class_id='.$class->class_id.'&date='.now()->toDateString())
            ->assertForbidden();

        $this->assertSame(0, AttendanceRecord::query()->count());
    }
}
