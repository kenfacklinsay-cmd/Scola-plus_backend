<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->payment_id,
            'receipt_number' => $this->receipt_number,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->toIso8601String(),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->student_id,
                'matricule' => $this->student->matricule,
                'first_name' => $this->student->first_name,
                'last_name' => $this->student->last_name,
            ]),
            'installment' => $this->whenLoaded('installment', fn () => [
                'id' => $this->installment->installment_id,
                'label' => $this->installment->label,
                'tuition_fee' => [
                    'class' => [
                        'id' => $this->installment->tuitionFee->schoolClass->class_id,
                        'name' => $this->installment->tuitionFee->schoolClass->name,
                    ],
                    'school_year' => [
                        'id' => $this->installment->tuitionFee->schoolYear->school_year_id,
                        'label' => $this->installment->tuitionFee->schoolYear->label,
                    ],
                ],
            ]),
        ];
    }
}
