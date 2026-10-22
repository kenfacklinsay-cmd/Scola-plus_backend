<?php

namespace App\Services\Finance;

use App\Models\Discount;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TuitionFee;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Appliquer une réduction au total des frais avant tout paiement. */
class ApplyTuitionDiscount
{
    public function __construct(private SchoolMoney $money) {}

    /** @param  array{student_id: int, amount: numeric, reason: string}  $data */
    public function apply(TuitionFee $tuitionFee, array $data): Discount
    {
        return DB::transaction(function () use ($tuitionFee, $data): Discount {
            $lockedFee = TuitionFee::query()
                ->whereKey($tuitionFee->tuition_fee_id)
                ->lockForUpdate()
                ->firstOrFail();
            $student = Student::query()
                ->whereKey($data['student_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $isEnrolled = Enrollment::query()
                ->where('student_id', $student->student_id)
                ->where('class_id', $lockedFee->class_id)
                ->where('school_year_id', $lockedFee->school_year_id)
                ->exists();

            if (! $isEnrolled) {
                throw ValidationException::withMessages([
                    'student_id' => ['Cet élève n’est pas inscrit dans la classe concernée pour cette année scolaire.'],
                ]);
            }

            if (! $lockedFee->installments()->exists()) {
                throw ValidationException::withMessages([
                    'tuition_fee' => ['Configurez les tranches avant d’accorder une réduction.'],
                ]);
            }

            $hasPayments = Payment::query()
                ->where('student_id', $student->student_id)
                ->whereHas('installment', fn ($query) => $query
                    ->where('tuition_fee_id', $lockedFee->tuition_fee_id))
                ->exists();

            if ($hasPayments) {
                throw ValidationException::withMessages([
                    'discount' => ['Une réduction doit être enregistrée avant le premier paiement afin de préserver l’échéancier.'],
                ]);
            }

            $existingDiscounts = Discount::query()
                ->where('student_id', $student->student_id)
                ->where('tuition_fee_id', $lockedFee->tuition_fee_id)
                ->get(['amount'])
                ->sum(fn ($discount) => $this->money->toCents($discount->amount));
            $newDiscount = $this->money->toCents($data['amount']);
            $grossTotal = $this->money->toCents($lockedFee->total_amount);

            if ($existingDiscounts + $newDiscount > $grossTotal) {
                throw ValidationException::withMessages([
                    'amount' => ['Le total des réductions ne peut pas dépasser les frais annuels.'],
                ]);
            }

            return Discount::query()->create([
                ...$data,
                'tuition_fee_id' => $lockedFee->tuition_fee_id,
            ]);
        });
    }
}
