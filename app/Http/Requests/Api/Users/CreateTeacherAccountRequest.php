<?php

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider le compte utilisateur et le profil professionnel d'un enseignant. */
class CreateTeacherAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['DIRECTEUR', 'SECRETARIAT'], true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:100'],
            'lastname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'telephone' => ['nullable', 'string', 'max:20', Rule::unique('users', 'telephone')],
            'teacher_registration_code' => ['required', 'string', 'max:40', Rule::unique('teachers', 'teacher_registration_code')],
            'specialty' => ['required', 'string', 'max:100'],
            'initial_password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
