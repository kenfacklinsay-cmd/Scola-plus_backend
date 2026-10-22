<?php

namespace App\Http\Requests\Api\Subjects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la création ou la modification d'une matière. */
class SaveSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') || $this->user()?->aRole('SECRETARIAT');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('subjects', 'name')->ignore($subject?->subject_id, 'subject_id'),
            ],
            'coefficient' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }
}
