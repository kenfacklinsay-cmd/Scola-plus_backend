<?php

namespace App\Http\Requests\Api\Students;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider un changement de classe dans la même année scolaire. */
class TransferStudentEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-school-structure', SchoolClass::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return [
            'class_id' => [
                'required',
                'integer',
                Rule::exists('classes', 'class_id')->where(
                    fn ($query) => $query->where('school_year_id', $enrollment->school_year_id),
                ),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'class_id.required' => 'La classe de destination est obligatoire.',
            'class_id.exists' => 'La classe doit appartenir à la même année scolaire que l’inscription.',
        ];
    }
}
