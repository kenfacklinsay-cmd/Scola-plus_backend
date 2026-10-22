<?php

namespace App\Http\Requests\Api\Finance;

use Illuminate\Foundation\Http\FormRequest;

/** Valider un paiement réellement reçu au guichet de l'école. */
class RecordPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->aRole('DIRECTEUR') || $user->aRole('COMPTABILITE'));
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,student_id'],
            'installment_id' => ['required', 'integer', 'exists:installments,installment_id'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', 'string', 'max:40'],
        ];
    }
}
