<?php

namespace App\Http\Requests\Api\Students;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la modification partielle d'une fiche élève. */
class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('student')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Student $student */
        $student = $this->route('student');

        return [
            'matricule' => ['sometimes', 'required', 'string', 'max:30', Rule::unique('students', 'matricule')->ignore($student->student_id, 'student_id')],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'birth_date' => ['sometimes', 'required', 'date', 'before:today'],
            'birth_place' => ['sometimes', 'required', 'string', 'max:150'],
            'gender' => ['sometimes', 'required', 'string', 'max:20'],
            'address' => ['sometimes', 'required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'matricule.unique' => 'Ce matricule est déjà utilisé.',
            'birth_date.before' => 'La date de naissance doit être antérieure à aujourd’hui.',
        ];
    }
}
