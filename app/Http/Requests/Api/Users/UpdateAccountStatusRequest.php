<?php

namespace App\Http\Requests\Api\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Valider la suspension ou la réactivation d'un compte scolaire. */
class UpdateAccountStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(['ACTIF', 'INACTIF'])],
        ];
    }
}
