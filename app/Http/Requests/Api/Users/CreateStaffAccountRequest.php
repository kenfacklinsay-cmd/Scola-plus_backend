<?php

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la création d'un compte du secrétariat ou de la comptabilité. */
class CreateStaffAccountRequest extends FormRequest
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
            'role' => ['required', Rule::in(['SECRETARIAT', 'COMPTABILITE'])],
            'initial_password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
