<?php

namespace App\Http\Requests\Api\Classes;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la création ou la modification d'une classe. */
class SaveSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-school-structure', SchoolClass::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $class = $this->route('schoolClass');
        $schoolYearId = $this->input('school_year_id', $class?->school_year_id);

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('classes', 'name')
                    ->where(fn ($query) => $query->where('school_year_id', $schoolYearId))
                    ->ignore($class?->class_id, 'class_id'),
            ],
            'level' => ['required', 'string', 'max:50'],
            'school_year_id' => [$class ? 'prohibited' : 'required', 'integer', 'exists:school_years,school_year_id'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'titular_teacher_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('teachers', 'teacher_id')->where(fn ($query) => $query->whereIn(
                    'user_id',
                    User::query()->select('id')->where('role', 'ENSEIGNANT')->where('status', 'ACTIF'),
                )),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'Ce champ est obligatoire.',
            'name.unique' => 'Une classe portant ce nom existe déjà pour cette année scolaire.',
            'school_year_id.prohibited' => 'L’année scolaire d’une classe existante ne peut pas être modifiée.',
            'school_year_id.exists' => 'L’année scolaire sélectionnée n’existe pas.',
            'capacity.min' => 'La capacité doit être supérieure à zéro.',
            'titular_teacher_id.exists' => 'L’enseignant titulaire sélectionné n’existe pas.',
        ];
    }
}
