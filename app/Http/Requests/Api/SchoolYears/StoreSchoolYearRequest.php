<?php

namespace App\Http\Requests\Api\SchoolYears;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la création d'une année scolaire. */
class StoreSchoolYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:20', Rule::unique('school_years', 'label')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', Rule::in(['UPCOMING', 'ONGOING', 'COMPLETED'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Ce champ est obligatoire.',
            'label.unique' => 'Cette année scolaire existe déjà.',
            'end_date.after_or_equal' => 'La date de fin doit suivre la date de début.',
            'status.in' => 'Le statut de l’année scolaire est invalide.',
        ];
    }
}
