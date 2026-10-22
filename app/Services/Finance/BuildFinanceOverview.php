<?php

namespace App\Services\Finance;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\TuitionFee;
use Carbon\Carbon;

/** Préparer les indicateurs financiers et les élèves en retard d'une année scolaire. */
class BuildFinanceOverview
{
    public function __construct(private SchoolMoney $money) {}

    /** @return array<string, mixed> */
    public function forSchoolYear(int $schoolYearId): array
    {
        $fees = TuitionFee::query()
            ->with(['schoolClass', 'installments' => fn ($query) => $query
                ->orderBy('due_date')->orderBy('installment_id')])
            ->where('school_year_id', $schoolYearId)
            ->get();
        $feeIds = $fees->modelKeys();
        $classIds = $fees->pluck('class_id')->unique()->all();

        $enrollments = Enrollment::query()
            ->with('student')
            ->where('school_year_id', $schoolYearId)
            ->whereIn('class_id', $classIds)
            ->get()
            ->groupBy('class_id');
        $enrollmentCount = Enrollment::query()->where('school_year_id', $schoolYearId)->count();

        $discounts = Discount::query()
            ->whereIn('tuition_fee_id', $feeIds)
            ->get(['student_id', 'tuition_fee_id', 'amount'])
            ->groupBy(fn ($row) => $row->tuition_fee_id.':'.$row->student_id)
            ->map(fn ($rows) => (int) $rows->sum(fn ($row) => $this->money->toCents($row->amount)));

        $payments = Payment::query()
            ->join('installments', 'installments.installment_id', '=', 'payments.installment_id')
            ->whereIn('installments.tuition_fee_id', $feeIds)
            ->where('payments.status', 'PAID')
            ->get(['payments.student_id', 'payments.installment_id', 'payments.amount'])
            ->groupBy(fn ($row) => $row->student_id.':'.$row->installment_id)
            ->map(fn ($rows) => (int) $rows->sum(fn ($row) => $this->money->toCents($row->amount)));

        $today = Carbon::today()->toDateString();
        $expected = 0;
        $received = 0;
        $remaining = 0;
        $overdueAmount = 0;
        $overdueStudents = [];

        foreach ($fees as $fee) {
            $grossTotal = $this->money->toCents($fee->total_amount);
            $installments = $fee->installments;
            $grossAmounts = $installments->map(fn ($row) => $this->money->toCents($row->amount))->all();
            $scheduleTotal = array_sum($grossAmounts);

            foreach ($enrollments->get($fee->class_id, collect()) as $enrollment) {
                $student = $enrollment->student;
                $discountTotal = min(
                    $grossTotal,
                    $discounts->get($fee->tuition_fee_id.':'.$student->student_id, 0),
                );
                $expected += max(0, $grossTotal - $discountTotal);
                $allocations = $this->allocateDiscount($discountTotal, $grossAmounts, $scheduleTotal);
                $studentPaid = 0;
                $studentRemaining = 0;
                $studentOverdue = 0;
                $overdueInstallments = [];

                foreach ($installments as $index => $installment) {
                    $paid = $payments->get($student->student_id.':'.$installment->installment_id, 0);
                    $received += $paid;
                    $studentPaid += $paid;
                    $balance = max(0, $grossAmounts[$index] - ($allocations[$index] ?? 0) - $paid);
                    $studentRemaining += $balance;

                    if ($balance > 0 && $installment->due_date->toDateString() < $today) {
                        $studentOverdue += $balance;
                        $overdueInstallments[] = [
                            'installment_id' => $installment->installment_id,
                            'label' => $installment->label,
                            'due_date' => $installment->due_date->toDateString(),
                            'remaining_amount' => $this->money->format($balance),
                        ];
                    }
                }

                $remaining += $studentRemaining;
                $overdueAmount += $studentOverdue;

                if ($studentOverdue > 0) {
                    $overdueStudents[] = [
                        'student' => [
                            'id' => $student->student_id,
                            'matricule' => $student->matricule,
                            'first_name' => $student->first_name,
                            'last_name' => $student->last_name,
                        ],
                        'class' => ['id' => $fee->schoolClass->class_id, 'name' => $fee->schoolClass->name],
                        'remaining_total' => $this->money->format($studentRemaining),
                        'overdue_total' => $this->money->format($studentOverdue),
                        'installments' => $overdueInstallments,
                    ];
                }
            }
        }

        return [
            'school_year_id' => $schoolYearId,
            'students_enrolled' => $enrollmentCount,
            'classes_with_fees' => $fees->count(),
            'total_expected' => $this->money->format($expected),
            'total_received' => $this->money->format($received),
            'remaining_total' => $this->money->format($remaining),
            'overdue_students_count' => count($overdueStudents),
            'overdue_total' => $this->money->format($overdueAmount),
            'overdue_students' => $overdueStudents,
        ];
    }

    /** @param  array<int, int>  $grossAmounts
     * @return array<int, int>
     */
    private function allocateDiscount(int $discount, array $grossAmounts, int $grossTotal): array
    {
        if ($grossTotal <= 0 || $discount <= 0) {
            return array_fill(0, count($grossAmounts), 0);
        }

        $allocations = [];
        $remainders = [];
        $allocated = 0;

        foreach ($grossAmounts as $index => $amount) {
            $product = $discount * $amount;
            $allocations[$index] = intdiv($product, $grossTotal);
            $remainders[] = ['index' => $index, 'value' => $product % $grossTotal];
            $allocated += $allocations[$index];
        }

        usort($remainders, fn (array $a, array $b) => ($b['value'] <=> $a['value']) ?: ($a['index'] <=> $b['index']));

        for ($index = 0, $left = $discount - $allocated; $index < $left; $index++) {
            $allocations[$remainders[$index]['index']]++;
        }

        ksort($allocations);

        return $allocations;
    }
}
