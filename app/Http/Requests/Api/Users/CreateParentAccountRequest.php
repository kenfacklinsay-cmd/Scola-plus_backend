<?php

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la création des accès d'un parent ou tuteur déjà enregistré. */
class CreateParentAccountRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'initial_password' => ['required', 'string', 'min:12', 'confirmed'],
        ];
    }
}
