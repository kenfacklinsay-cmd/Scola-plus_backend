<?php

namespace App\Http\Requests\Api\Finance;

use App\Models\SchoolClass;
use App\Services\Finance\SchoolMoney;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Valider la création des frais annuels d'une classe. */
class StoreTuitionFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->aRole('DIRECTEUR') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
            'total_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'installments' => ['sometimes', 'required', 'array', 'min:1', 'max:12'],
            'installments.*.label' => ['required_with:installments', 'string', 'max:80', 'distinct'],
            'installments.*.amount' => [
                'required_with:installments',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:9999999999.99',
            ],
            'installments.*.due_date' => ['required_with:installments', 'date'],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,class_id',
                Rule::unique('tuition_fees', 'class_id')->where(fn ($query) => $query
                    ->where('school_year_id', $this->input('school_year_id'))),
            ],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        $money = new SchoolMoney;

        return [function (Validator $validator) use ($money): void {
            if ($validator->errors()->hasAny(['class_id', 'school_year_id'])) {
                return;
            }

            $schoolClass = SchoolClass::query()->find($this->integer('class_id'));

            if ($schoolClass !== null && (int) $schoolClass->school_year_id !== $this->integer('school_year_id')) {
                $validator->errors()->add('class_id', 'La classe ne correspond pas à l’année scolaire sélectionnée.');
            }

            if (! $this->exists('installments') || $validator->errors()->any()) {
                return;
            }

            $installmentsTotal = collect($this->input('installments'))
                ->sum(fn (array $installment) => $money->toCents($installment['amount']));

            if ($installmentsTotal !== $money->toCents($this->input('total_amount'))) {
                $validator->errors()->add('installments', 'Le total des tranches doit être égal aux frais annuels.');
            }
        }];
    }
}
