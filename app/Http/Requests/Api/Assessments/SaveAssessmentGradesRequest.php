<?php

namespace App\Http\Requests\Api\Assessments;

use App\Models\Assessment;
use App\Models\Enrollment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Valider les notes envoyées pour les élèves inscrits à cette classe. */
class SaveAssessmentGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');
        $teacherId = $this->user()?->teacherProfile?->teacher_id;

        return $this->user() !== null
            && $this->user()->aRole('ENSEIGNANT')
            && $assessment instanceof Assessment
            && $teacherId !== null
            && (int) $assessment->assignment()->value('teacher_id') === (int) $teacherId;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.student_id' => ['required', 'integer', 'distinct', 'exists:students,student_id'],
            'grades.*.value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:20'],
            'grades.*.appreciation' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->any()) {
                return;
            }

            $assessment = $this->route('assessment');
            $assignment = $assessment->assignment;
            $schoolYearId = $assessment->period->school_year_id;
            $studentIds = collect($this->input('grades', []))->pluck('student_id')->all();

            $enrolledIds = Enrollment::query()
                ->where('class_id', $assignment->class_id)
                ->where('school_year_id', $schoolYearId)
                ->whereIn('student_id', $studentIds)
                ->distinct()
                ->pluck('student_id')
                ->all();

            if (count($enrolledIds) !== count($studentIds)) {
                $validator->errors()->add('grades', 'Chaque élève doit être inscrit dans cette classe pour cette année scolaire.');
            }
        }];
    }
}
