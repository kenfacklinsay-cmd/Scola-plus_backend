<?php

namespace App\Services\Finance;

use App\Models\TuitionFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Remplacer l'échéancier d'une classe avant que des paiements y soient enregistrés. */
class SaveInstallmentSchedule
{
    public function __construct(private SchoolMoney $money) {}

    /**
     * @param  array<int, array{label: string, amount: numeric, due_date: string}>  $installments
     */
    public function save(TuitionFee $tuitionFee, array $installments): TuitionFee
    {
        return DB::transaction(function () use ($tuitionFee, $installments): TuitionFee {
            $lockedFee = TuitionFee::query()
                ->whereKey($tuitionFee->tuition_fee_id)
                ->lockForUpdate()
                ->firstOrFail();

            $installmentsTotal = collect($installments)
                ->sum(fn (array $installment) => $this->money->toCents($installment['amount']));

            if ($installmentsTotal !== $this->money->toCents($lockedFee->total_amount)) {
                throw ValidationException::withMessages([
                    'installments' => ['Le total des tranches doit être égal aux frais annuels.'],
                ]);
            }

            $hasPayments = $lockedFee->installments()->whereHas('payments')->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'installments' => ['L’échéancier ne peut plus être modifié après l’enregistrement d’un paiement.'],
                ]);
            }

            if ($lockedFee->discounts()->exists()) {
                throw ValidationException::withMessages([
                    'installments' => ['L’échéancier ne peut plus être modifié après l’attribution d’une réduction.'],
                ]);
            }

            $lockedFee->installments()->delete();
            $lockedFee->installments()->createMany($installments);

            return $lockedFee->load([
                'schoolClass',
                'schoolYear',
                'installments' => fn ($query) => $query->orderBy('due_date')->orderBy('installment_id'),
            ]);
        });
    }
}
