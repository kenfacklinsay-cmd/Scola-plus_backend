<?php

namespace App\Http\Requests\Api\Students;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\RequiredIf;

/** Valider la fiche de l'élève, son inscription et le contact familial. */
class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('students', 'matricule')],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today'],
            'birth_place' => ['required', 'string', 'max:150'],
            'gender' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:2000'],
            'class_id' => ['required', 'integer', Rule::exists('classes', 'class_id')],
            'school_year_id' => ['required', 'integer', Rule::exists('school_years', 'school_year_id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('parents', 'parent_id')],
            'parent.first_name' => [new RequiredIf(! $this->filled('parent_id')), 'string', 'max:100'],
            'parent.last_name' => [new RequiredIf(! $this->filled('parent_id')), 'string', 'max:100'],
            'parent.telephone' => [new RequiredIf(! $this->filled('parent_id')), 'string', 'max:20'],
            'parent.address' => ['nullable', 'string', 'max:2000'],
            'parent.relationship' => ['nullable', 'string', 'max:50'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Ce champ est obligatoire.',
            'birth_date.before' => 'La date de naissance doit être antérieure à aujourd’hui.',
            'matricule.unique' => 'Ce matricule est déjà utilisé.',
            'class_id.exists' => 'La classe sélectionnée n’existe pas.',
            'school_year_id.exists' => 'L’année scolaire sélectionnée n’existe pas.',
        ];
    }
}
