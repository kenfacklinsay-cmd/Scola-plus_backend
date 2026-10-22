<?php

namespace App\Notifications;

use App\Models\AttendanceRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Informer le parent dans son espace qu'une absence vient d'être enregistrée. */
class StudentAbsenceRecorded extends Notification
{
    use Queueable;

    public function __construct(private readonly AttendanceRecord $attendance) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $this->attendance->loadMissing('enrollment.student', 'enrollment.schoolClass');
        $student = $this->attendance->enrollment->student;

        return [
            'event' => 'student_absence',
            'title' => 'Absence signalée',
            'message' => "{$student->first_name} {$student->last_name} a été marqué absent.",
            'student_id' => $student->student_id,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'class_name' => $this->attendance->enrollment->schoolClass->name,
            'attendance_date' => $this->attendance->attendance_date->toDateString(),
        ];
    }
}
