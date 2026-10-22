<?php

namespace App\Services\Gradebook;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\Student;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/** Préparer les résultats d'un élève et les détails de sa période pour le bulletin. */
class BuildStudentBulletin
{
    public function __construct(private CalculateClassAverages $calculateClassAverages) {}

    /** @return array<string, mixed> */
    public function build(Student $student, Period $period): array
    {
        $enrollment = Enrollment::query()
            ->with(['schoolClass', 'schoolYear'])
            ->where('student_id', $student->student_id)
            ->where('school_year_id', $period->school_year_id)
            ->first();

        if ($enrollment === null) {
            throw (new ModelNotFoundException)->setModel(Enrollment::class);
        }

        $class = $enrollment->schoolClass;
        $summary = $this->calculateClassAverages->calculate($class, $period);
        $studentResult = collect($summary['students'])->firstWhere('student_id', $student->student_id);
        $subjectResults = collect($studentResult['subjects'] ?? [])->keyBy('id');

        $assignments = Assignment::query()
            ->where('class_id', $class->class_id)
            ->with([
                'subject',
                'teacher.user',
                'assessments' => fn ($query) => $query
                    ->where('period_id', $period->period_id)
                    ->with(['grades' => fn ($gradeQuery) => $gradeQuery
                        ->where('student_id', $student->student_id)]),
            ])
            ->get();

        $subjects = $assignments->map(function (Assignment $assignment) use ($subjectResults): array {
            $result = $subjectResults->get($assignment->subject_id);

            return [
                'name' => $assignment->subject->name,
                'coefficient' => (int) $assignment->subject->coefficient,
                'teacher' => trim($assignment->teacher->user->firstname.' '.$assignment->teacher->user->lastname),
                'average' => $result['average'] ?? null,
                'grades_count' => $result['grades_count'] ?? 0,
                'assessments' => $assignment->assessments->map(function ($assessment): array {
                    $grade = $assessment->grades->first();

                    return [
                        'label' => $assessment->label,
                        'date' => $assessment->assessment_date->format('d/m/Y'),
                        'value' => $grade?->value === null ? null : (float) $grade->value,
                        'appreciation' => $grade?->appreciation,
                    ];
                })->all(),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'student' => $student,
            'class' => $class,
            'period' => $period,
            'schoolYear' => $enrollment->schoolYear,
            'average' => $studentResult['average'] ?? null,
            'mention' => $studentResult['mention'] ?? null,
            'rank' => $studentResult['rank'] ?? null,
            'class_size' => $summary['students_with_average_count'],
            'subjects' => $subjects,
        ];
    }
}
