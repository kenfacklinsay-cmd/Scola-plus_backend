<?php

namespace App\Http\Requests\Api\Finance;

use Illuminate\Foundation\Http\FormRequest;

/** Valider une réduction accordée à un élève sur les frais annuels. */
class StoreDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,student_id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
