<?php

namespace App\Http\Requests\Api\Assessments;

use App\Models\Assignment;
use App\Models\Period;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Valider une évaluation d'un enseignant dans une période de sa classe. */
class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->aRole('ENSEIGNANT') && $user->teacherProfile()->exists();
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', 'exists:assignments,assignment_id'],
            'period_id' => ['required', 'integer', 'exists:periods,period_id'],
            'label' => [
                'required',
                'string',
                'max:100',
                Rule::unique('assessments', 'label')->where(fn ($query) => $query
                    ->where('assignment_id', $this->input('assignment_id'))
                    ->where('period_id', $this->input('period_id'))),
            ],
            'assessment_date' => ['required', 'date'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['assignment_id', 'period_id', 'assessment_date'])) {
                return;
            }

            $teacherId = $this->user()->teacherProfile?->teacher_id;
            $assignment = Assignment::query()->with('schoolClass')->find($this->integer('assignment_id'));
            $period = Period::query()->find($this->integer('period_id'));

            if ($assignment === null || (int) $assignment->teacher_id !== (int) $teacherId) {
                $validator->errors()->add('assignment_id', 'Cette affectation ne vous appartient pas.');

                return;
            }

            if ($period === null || (int) $period->school_year_id !== (int) $assignment->schoolClass->school_year_id) {
                $validator->errors()->add('period_id', 'Cette période ne correspond pas à l’année scolaire de la classe.');

                return;
            }

            $date = Carbon::parse($this->input('assessment_date'))->toDateString();

            if ($date < $period->start_date->toDateString() || $date > $period->end_date->toDateString()) {
                $validator->errors()->add('assessment_date', 'La date doit se situer dans la période sélectionnée.');
            }
        }];
    }
}
