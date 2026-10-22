<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Finance\RecordPaymentRequest;
use App\Http\Resources\Api\PaymentResource;
use App\Models\Payment;
use App\Services\Finance\RecordTuitionPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Consulter les encaissements, enregistrer un paiement et fournir son reçu. */
class PaymentController extends Controller
{
    /** Rechercher les paiements d'une année ou d'un élève. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,school_year_id'],
            'student_id' => ['nullable', 'integer', 'exists:students,student_id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $payments = Payment::query()
            ->with(['student', 'installment.tuitionFee.schoolClass', 'installment.tuitionFee.schoolYear'])
            ->when($filters['school_year_id'] ?? null, fn ($query, $yearId) => $query
                ->whereHas('installment.tuitionFee', fn ($feeQuery) => $feeQuery->where('school_year_id', $yearId)))
            ->when($filters['student_id'] ?? null, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('payment_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('payment_date', '<=', $date))
            ->latest('payment_date')
            ->latest('payment_id')
            ->paginate(50);

        return PaymentResource::collection($payments)->response();
    }

    /** Enregistrer un encaissement partiel et attribuer un numéro de reçu unique. */
    public function store(RecordPaymentRequest $request, RecordTuitionPayment $recordPayment): JsonResponse
    {
        $payment = $recordPayment->record($request->validated());

        return (new PaymentResource($payment))->response()->setStatusCode(201);
    }

    /** Retourner les informations à afficher ou imprimer sur le reçu. */
    public function receipt(Payment $payment): JsonResponse
    {
        $payment->loadMissing(['student', 'installment.tuitionFee.schoolClass', 'installment.tuitionFee.schoolYear']);

        return response()->json([
            'data' => [
                'receipt_number' => $payment->receipt_number,
                'payment_date' => $payment->payment_date->toIso8601String(),
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
                'student' => [
                    'matricule' => $payment->student->matricule,
                    'name' => trim($payment->student->first_name.' '.$payment->student->last_name),
                ],
                'class' => $payment->installment->tuitionFee->schoolClass->name,
                'school_year' => $payment->installment->tuitionFee->schoolYear->label,
                'installment' => $payment->installment->label,
            ],
        ]);
    }
}
