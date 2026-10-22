<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Assessments\SaveAssessmentGradesRequest;
use App\Http\Resources\Api\AssessmentResource;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Period;
use App\Models\SchoolClass;
use App\Services\Gradebook\CalculateClassAverages;
use App\Services\Gradebook\SaveAssessmentGrades;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/** Consulter la liste des notes d'une évaluation et les résultats d'une classe. */
class GradebookController extends Controller
{
    public function showAssessmentGrades(Assessment $assessment): JsonResponse
    {
        $this->authorizeAssessment($assessment);

        $assessment->load(['assignment.subject', 'assignment.schoolClass', 'period']);
        $classId = $assessment->assignment->class_id;
        $schoolYearId = $assessment->period->school_year_id;

        $enrollments = Enrollment::query()
            ->where('class_id', $classId)
            ->where('school_year_id', $schoolYearId)
            ->with(['student.grades' => fn ($query) => $query
                ->where('assessment_id', $assessment->assessment_id)])
            ->get();

        $students = $enrollments->map(function (Enrollment $enrollment): array {
            $student = $enrollment->student;
            $grade = $student->grades->first();

            return [
                'id' => $student->student_id,
                'matricule' => $student->matricule,
                'last_name' => $student->last_name,
                'first_name' => $student->first_name,
                'value' => $grade?->value === null ? null : (float) $grade->value,
                'appreciation' => $grade?->appreciation,
            ];
        })->sort(function (array $first, array $second): int {
            $lastNameOrder = strcmp(
                Str::ascii(mb_strtolower($first['last_name'])),
                Str::ascii(mb_strtolower($second['last_name'])),
            );

            if ($lastNameOrder !== 0) {
                return $lastNameOrder;
            }

            return strcmp(
                Str::ascii(mb_strtolower($first['first_name'])),
                Str::ascii(mb_strtolower($second['first_name'])),
            );
        })->values();

        return response()->json([
            'assessment' => (new AssessmentResource($assessment))->resolve(),
            'students' => $students,
        ]);
    }

    public function saveAssessmentGrades(
        SaveAssessmentGradesRequest $request,
        Assessment $assessment,
        SaveAssessmentGrades $saveGrades,
    ): JsonResponse {
        $counts = $saveGrades->save($assessment, $request->validated('grades'));

        return response()->json([
            'assessment_id' => $assessment->assessment_id,
            'saved_count' => $counts['saved'],
            'missing_count' => $counts['missing'],
        ]);
    }

    public function classSummary(
        SchoolClass $schoolClass,
        Period $period,
        CalculateClassAverages $calculateAverages,
    ): JsonResponse {
        abort_unless((int) $schoolClass->school_year_id === (int) $period->school_year_id, 404);

        return response()->json($calculateAverages->calculate($schoolClass, $period));
    }

    private function authorizeAssessment(Assessment $assessment): void
    {
        $teacherId = request()->user()?->teacherProfile?->teacher_id;

        abort_unless(
            $teacherId !== null
                && (int) $assessment->assignment()->value('teacher_id') === (int) $teacherId,
            403,
        );
    }
}
