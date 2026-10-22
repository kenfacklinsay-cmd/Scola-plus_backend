<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Notifications\StudentAbsenceRecorded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Gérer l'appel quotidien et le suivi des absences. */
class AttendanceController extends Controller
{
    private const STATUSES = ['PRESENT', 'ABSENT', 'LATE', 'EXCUSED'];

    public function teacherRoster(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,class_id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
        ]);
        $schoolClass = SchoolClass::query()->findOrFail($filters['class_id']);
        $teacherId = $request->user()->teacherProfile?->teacher_id;
        abort_unless(
            $teacherId !== null
                && $schoolClass->assignments()->where('teacher_id', $teacherId)->exists(),
            403,
            'Cette classe ne vous est pas attribuée.',
        );

        $enrollments = Enrollment::query()
            ->with(['student', 'attendanceRecords' => fn ($query) => $query
                ->whereDate('attendance_date', $filters['date'])])
            ->where('class_id', $schoolClass->class_id)
            ->where('school_year_id', $schoolClass->school_year_id)
            ->where('status', 'ACTIVE')
            ->whereHas('student')
            ->get()
            ->sortBy(fn (Enrollment $enrollment): string => mb_strtolower(
                $enrollment->student->last_name.' '.$enrollment->student->first_name,
            ))
            ->values();

        return response()->json([
            'data' => [
                'class' => [
                    'id' => $schoolClass->class_id,
                    'name' => $schoolClass->name,
                    'school_year_id' => $schoolClass->school_year_id,
                ],
                'date' => $filters['date'],
                'students' => $enrollments->map(function (Enrollment $enrollment): array {
                    $record = $enrollment->attendanceRecords->first();

                    return [
                        'id' => $enrollment->student->student_id,
                        'enrollment_id' => $enrollment->enrollment_id,
                        'matricule' => $enrollment->student->matricule,
                        'first_name' => $enrollment->student->first_name,
                        'last_name' => $enrollment->student->last_name,
                        'status' => $record?->status ?? 'PRESENT',
                        'notes' => $record?->notes ?? '',
                    ];
                }),
            ],
        ]);
    }

    public function saveTeacherRoster(Request $request): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,class_id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,student_id'],
            'records.*.status' => ['required', 'string', Rule::in(self::STATUSES)],
            'records.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $schoolClass = SchoolClass::query()->findOrFail($data['class_id']);
        $teacherId = $request->user()->teacherProfile?->teacher_id;
        abort_unless(
            $teacherId !== null
                && $schoolClass->assignments()->where('teacher_id', $teacherId)->exists(),
            403,
            'Cette classe ne vous est pas attribuée.',
        );
        $studentIds = collect($data['records'])->pluck('student_id')->map(fn ($id) => (int) $id);
        $enrollments = Enrollment::query()
            ->with(['student.parents.user', 'schoolClass'])
            ->where('class_id', $schoolClass->class_id)
            ->where('school_year_id', $schoolClass->school_year_id)
            ->where('status', 'ACTIVE')
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
        abort_unless($enrollments->count() === $studentIds->count(), 422, 'La liste contient un élève qui n’est pas inscrit dans cette classe.');

        $absencesNotified = 0;
        DB::transaction(function () use ($data, $enrollments, $request, &$absencesNotified): void {
            foreach ($data['records'] as $entry) {
                $enrollment = $enrollments->get((int) $entry['student_id']);
                $record = AttendanceRecord::query()
                    ->where('enrollment_id', $enrollment->enrollment_id)
                    ->whereDate('attendance_date', $data['date'])
                    ->lockForUpdate()
                    ->first();
                $wasAbsent = $record?->status === 'ABSENT';
                $record ??= new AttendanceRecord([
                    'enrollment_id' => $enrollment->enrollment_id,
                    'attendance_date' => $data['date'],
                ]);
                $record->forceFill([
                    'status' => $entry['status'],
                    'notes' => $entry['notes'] ?? null,
                    'marked_by' => $request->user()->getKey(),
                ])->save();

                if ($entry['status'] === 'ABSENT' && ! $wasAbsent) {
                    foreach ($enrollment->student->parents as $parent) {
                        if ($parent->user?->status === 'ACTIF') {
                            $parent->user->notify(new StudentAbsenceRecorded($record));
                            $absencesNotified++;
                        }
                    }
                }
            }
        });

        return response()->json([
            'message' => 'Les présences ont été enregistrées.',
            'data' => [
                'saved_count' => count($data['records']),
                'parent_notifications_count' => $absencesNotified,
            ],
        ]);
    }

    public function absences(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date', 'before_or_equal:today'],
            'class_id' => ['nullable', 'integer', 'exists:classes,class_id'],
        ]);
        $date = $filters['date'] ?? today()->toDateString();
        $records = AttendanceRecord::query()
            ->with(['enrollment.student', 'enrollment.schoolClass', 'marker'])
            ->whereDate('attendance_date', $date)
            ->where('status', 'ABSENT')
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query
                ->whereHas('enrollment', fn ($enrollmentQuery) => $enrollmentQuery->where('class_id', $id)))
            ->orderBy('attendance_id')
            ->get();

        return response()->json([
            'data' => $records->map(fn (AttendanceRecord $record): array => [
                'id' => $record->attendance_id,
                'date' => $record->attendance_date->toDateString(),
                'student' => [
                    'id' => $record->enrollment->student->student_id,
                    'first_name' => $record->enrollment->student->first_name,
                    'last_name' => $record->enrollment->student->last_name,
                    'matricule' => $record->enrollment->student->matricule,
                ],
                'class' => [
                    'id' => $record->enrollment->schoolClass->class_id,
                    'name' => $record->enrollment->schoolClass->name,
                ],
                'notes' => $record->notes,
                'marked_by' => trim($record->marker->firstname.' '.$record->marker->lastname),
            ]),
        ]);
    }
}
