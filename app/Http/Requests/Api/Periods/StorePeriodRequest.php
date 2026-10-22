<?php

namespace App\Http\Requests\Api\Periods;

use App\Models\SchoolYear;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Valider une période de notation contenue dans une année scolaire. */
class StorePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->aRole('DIRECTEUR') || $user->aRole('SECRETARIAT'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => [
                'required',
                'string',
                'max:80',
                Rule::unique('periods', 'label')->where(fn ($query) => $query
                    ->where('school_year_id', $this->input('school_year_id'))),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['school_year_id', 'start_date', 'end_date'])) {
                return;
            }

            $schoolYear = SchoolYear::query()->find($this->integer('school_year_id'));

            if ($schoolYear === null) {
                return;
            }

            $startDate = Carbon::parse($this->input('start_date'))->toDateString();
            $endDate = Carbon::parse($this->input('end_date'))->toDateString();

            if (
                $startDate < $schoolYear->start_date->toDateString()
                || $endDate > $schoolYear->end_date->toDateString()
            ) {
                $validator->errors()->add('start_date', 'Les dates de la période doivent rester dans l’année scolaire.');

                return;
            }

            $overlapsExistingPeriod = $schoolYear->periods()
                ->whereDate('start_date', '<=', $endDate)
                ->whereDate('end_date', '>=', $startDate)
                ->exists();

            if ($overlapsExistingPeriod) {
                $validator->errors()->add('start_date', 'Cette période chevauche une autre période de l’année scolaire.');
            }
        }];
    }
}
