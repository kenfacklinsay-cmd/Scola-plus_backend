<?php

namespace App\Services\Finance;

use App\Models\Enrollment;
use App\Models\Installment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\TuitionFee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Enregistrer un paiement partiel et générer son numéro de reçu. */
class RecordTuitionPayment
{
    public function __construct(
        private SchoolMoney $money,
        private CalculateTuitionBalance $calculateBalance,
    ) {}

    /** @param  array{student_id: int, installment_id: int, amount: numeric, payment_date: string, payment_method: string}  $data */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $installmentReference = Installment::query()->findOrFail($data['installment_id']);
            $tuitionFee = TuitionFee::query()
                ->whereKey($installmentReference->tuition_fee_id)
                ->lockForUpdate()
                ->firstOrFail();
            $installment = $tuitionFee->installments()
                ->whereKey($data['installment_id'])
                ->lockForUpdate()
                ->firstOrFail();
            $student = Student::query()
                ->whereKey($data['student_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $isEnrolled = Enrollment::query()
                ->where('student_id', $student->student_id)
                ->where('class_id', $tuitionFee->class_id)
                ->where('school_year_id', $tuitionFee->school_year_id)
                ->exists();

            if (! $isEnrolled) {
                throw ValidationException::withMessages([
                    'student_id' => ['Cet élève n’est pas inscrit dans la classe concernée pour cette année scolaire.'],
                ]);
            }

            $scheduleTotal = $tuitionFee->installments()
                ->get(['amount'])
                ->sum(fn ($row) => $this->money->toCents($row->amount));

            if ($scheduleTotal !== $this->money->toCents($tuitionFee->total_amount)) {
                throw ValidationException::withMessages([
                    'installment_id' => ['L’échéancier des frais n’est pas complet ou ne correspond plus au total annuel.'],
                ]);
            }

            $balance = $this->calculateBalance->calculate($tuitionFee, $student);
            $installmentBalance = collect($balance['installments'])
                ->firstWhere('id', $installment->installment_id);
            $paymentAmount = $this->money->toCents($data['amount']);

            if (
                $installmentBalance === null
                || $paymentAmount > $this->money->toCents($installmentBalance['remaining_amount'])
                || $paymentAmount > $this->money->toCents($balance['remaining_total'])
            ) {
                throw ValidationException::withMessages([
                    'amount' => ['Le paiement dépasse le montant restant dû pour cette tranche ou pour l’année scolaire.'],
                ]);
            }

            $payment = Payment::query()->create([
                ...$data,
                // La colonne est NOT NULL ; le numéro définitif remplace cet identifiant dans la transaction.
                'receipt_number' => 'TMP-'.Str::ulid(),
                'payment_date' => Carbon::parse($data['payment_date']),
                'status' => 'PAID',
            ]);
            $year = Carbon::parse($data['payment_date'])->format('Y');
            $payment->update([
                'receipt_number' => 'SP-'.$year.'-'.str_pad((string) $payment->payment_id, 8, '0', STR_PAD_LEFT),
            ]);

            return $payment->load(['student', 'installment.tuitionFee.schoolClass', 'installment.tuitionFee.schoolYear']);
        });
    }
}
