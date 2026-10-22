<?php

namespace App\Services\Gradebook;

use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Grade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Enregistrer en une transaction les notes saisies d'une évaluation. */
class SaveAssessmentGrades
{
    /**
     * @param  array<int, array{student_id: int, value?: numeric|null, appreciation?: string|null}>  $grades
     * @return array{saved: int, missing: int}
     */
    public function save(Assessment $assessment, array $grades): array
    {
        return DB::transaction(function () use ($assessment, $grades): array {
            $lockedAssessment = Assessment::query()
                ->whereKey($assessment->assessment_id)
                ->with(['assignment', 'period'])
                ->lockForUpdate()
                ->firstOrFail();

            $studentIds = collect($grades)->pluck('student_id')->all();
            $enrolledIds = Enrollment::query()
                ->where('class_id', $lockedAssessment->assignment->class_id)
                ->where('school_year_id', $lockedAssessment->period->school_year_id)
                ->whereIn('student_id', $studentIds)
                ->lockForUpdate()
                ->pluck('student_id')
                ->all();

            if (count($enrolledIds) !== count($studentIds)) {
                throw ValidationException::withMessages([
                    'grades' => ['Un ou plusieurs élèves ne sont pas inscrits dans cette classe pour cette année scolaire.'],
                ]);
            }

            $saved = 0;
            $missing = 0;

            foreach ($grades as $gradeData) {
                $value = $gradeData['value'] ?? null;
                if ($value === null) {
                    $missing++;

                    continue;
                }

                $attributes = ['value' => $value];

                if (array_key_exists('appreciation', $gradeData)) {
                    $attributes['appreciation'] = $gradeData['appreciation'];
                }

                Grade::query()->updateOrCreate([
                    'assessment_id' => $lockedAssessment->assessment_id,
                    'student_id' => $gradeData['student_id'],
                ], $attributes);
                $saved++;
            }

            return ['saved' => $saved, 'missing' => $missing];
        });
    }
}
