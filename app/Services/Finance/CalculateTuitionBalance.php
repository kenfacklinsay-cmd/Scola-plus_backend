<?php

namespace App\Services\Finance;

use App\Models\Student;
use App\Models\TuitionFee;
use Carbon\Carbon;

/** Calculer les sommes dues en appliquant les réductions au prorata des tranches. */
class CalculateTuitionBalance
{
    public function __construct(private SchoolMoney $money) {}

    /** @return array<string, mixed> */
    public function calculate(TuitionFee $tuitionFee, Student $student): array
    {
        $grossTotal = $this->money->toCents($tuitionFee->total_amount);
        $discountTotal = (int) $tuitionFee->discounts()
            ->where('student_id', $student->student_id)
            ->get(['amount'])
            ->sum(fn ($discount) => $this->money->toCents($discount->amount));
        $discountTotal = min($grossTotal, $discountTotal);

        $installments = $tuitionFee->installments()
            ->with(['payments' => fn ($query) => $query
                ->where('student_id', $student->student_id)
                ->where('status', 'PAID')])
            ->orderBy('due_date')
            ->orderBy('installment_id')
            ->get();

        $grossAmounts = $installments
            ->map(fn ($installment) => $this->money->toCents($installment->amount))
            ->all();
        $installmentsGross = array_sum($grossAmounts);
        $discountAllocations = $this->allocateDiscount($discountTotal, $grossAmounts, $installmentsGross);
        $paidTotal = 0;
        $overdueTotal = 0;
        $balances = [];
        $today = Carbon::today()->toDateString();

        foreach ($installments as $index => $installment) {
            $grossAmount = $grossAmounts[$index];
            $allocatedDiscount = $discountAllocations[$index] ?? 0;
            $adjustedAmount = $grossAmount - $allocatedDiscount;
            $paidAmount = (int) $installment->payments->sum(fn ($payment) => $this->money->toCents($payment->amount));
            $remainingAmount = max(0, $adjustedAmount - $paidAmount);
            $paidTotal += $paidAmount;

            if ($remainingAmount > 0 && $installment->due_date->toDateString() < $today) {
                $overdueTotal += $remainingAmount;
                $status = 'OVERDUE';
            } elseif ($remainingAmount === 0) {
                $status = 'PAID';
            } elseif ($paidAmount > 0) {
                $status = 'PARTIAL';
            } else {
                $status = 'UPCOMING';
            }

            $balances[] = [
                'id' => $installment->installment_id,
                'label' => $installment->label,
                'due_date' => $installment->due_date->toDateString(),
                'gross_amount' => $this->money->format($grossAmount),
                'discount_amount' => $this->money->format($allocatedDiscount),
                'amount_due' => $this->money->format($adjustedAmount),
                'paid_amount' => $this->money->format($paidAmount),
                'remaining_amount' => $this->money->format($remainingAmount),
                'status' => $status,
            ];
        }

        $netTotal = max(0, $grossTotal - $discountTotal);

        return [
            'student' => [
                'id' => $student->student_id,
                'matricule' => $student->matricule,
                'last_name' => $student->last_name,
                'first_name' => $student->first_name,
            ],
            'tuition_fee_id' => $tuitionFee->tuition_fee_id,
            'gross_total' => $this->money->format($grossTotal),
            'discount_total' => $this->money->format($discountTotal),
            'net_total' => $this->money->format($netTotal),
            'paid_total' => $this->money->format($paidTotal),
            'remaining_total' => $this->money->format(max(0, $netTotal - $paidTotal)),
            'overdue_total' => $this->money->format($overdueTotal),
            'installments' => $balances,
        ];
    }

    /** @param  array<int, int>  $grossAmounts
     * @return array<int, int>
     */
    private function allocateDiscount(int $discountTotal, array $grossAmounts, int $grossTotal): array
    {
        if ($grossTotal === 0 || $discountTotal === 0) {
            return array_fill(0, count($grossAmounts), 0);
        }

        $allocations = [];
        $remainders = [];
        $allocatedTotal = 0;

        foreach ($grossAmounts as $index => $grossAmount) {
            $numerator = $discountTotal * $grossAmount;
            $allocations[$index] = intdiv($numerator, $grossTotal);
            $remainders[] = ['index' => $index, 'remainder' => $numerator % $grossTotal];
            $allocatedTotal += $allocations[$index];
        }

        usort($remainders, fn (array $first, array $second) => ($second['remainder'] <=> $first['remainder']) ?: ($first['index'] <=> $second['index']));

        $remainingCents = $discountTotal - $allocatedTotal;

        for ($index = 0; $index < $remainingCents; $index++) {
            $allocations[$remainders[$index]['index']]++;
        }

        ksort($allocations);

        return $allocations;
    }
}
