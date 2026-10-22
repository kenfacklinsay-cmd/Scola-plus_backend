<?php

namespace App\Http\Requests\Api\Assignments;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider le changement d'enseignant d'une affectation existante. */
class UpdateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') || $this->user()?->aRole('SECRETARIAT');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('teachers', 'teacher_id')->where(fn ($query) => $query->whereIn(
                    'user_id',
                    User::query()->select('id')->where('role', 'ENSEIGNANT')->where('status', 'ACTIF'),
                )),
            ],
        ];
    }
}
