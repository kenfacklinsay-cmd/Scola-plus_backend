<?php

namespace App\Http\Requests\Api\Finance;

use App\Models\TuitionFee;
use App\Services\Finance\SchoolMoney;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Vérifier qu'un échéancier couvre exactement les frais annuels. */
class SaveInstallmentScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'installments' => ['required', 'array', 'min:1', 'max:12'],
            'installments.*.label' => ['required', 'string', 'max:80', 'distinct'],
            'installments.*.amount' => [
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:9999999999.99',
            ],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        $money = new SchoolMoney;

        return [function (Validator $validator) use ($money): void {
            if ($validator->errors()->any()) {
                return;
            }

            $tuitionFee = $this->route('tuitionFee');

            if (! $tuitionFee instanceof TuitionFee) {
                return;
            }

            $installmentsTotal = collect($this->input('installments'))
                ->sum(fn (array $installment) => $money->toCents($installment['amount']));

            if ($installmentsTotal !== $money->toCents($tuitionFee->total_amount)) {
                $validator->errors()->add('installments', 'Le total des tranches doit être égal aux frais annuels.');
            }
        }];
    }
}
