<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider les filtres de période du tableau de bord global. */
class DashboardOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $schoolYearId = $this->input('school_year_id');

        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
            'period_id' => [
                'required',
                'integer',
                Rule::exists('periods', 'period_id')->where(
                    fn ($query) => $query->where('school_year_id', $schoolYearId),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'school_year_id.required' => 'L’année scolaire est obligatoire.',
            'school_year_id.exists' => 'L’année scolaire sélectionnée n’existe pas.',
            'period_id.required' => 'La période est obligatoire.',
            'period_id.exists' => 'La période doit appartenir à l’année scolaire sélectionnée.',
        ];
    }
}
