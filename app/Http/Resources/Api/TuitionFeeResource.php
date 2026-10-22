<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TuitionFeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->tuition_fee_id,
            'total_amount' => $this->total_amount,
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->class_id,
                'name' => $this->schoolClass->name,
                'level' => $this->schoolClass->level,
            ]),
            'school_year' => $this->whenLoaded('schoolYear', fn () => [
                'id' => $this->schoolYear->school_year_id,
                'label' => $this->schoolYear->label,
            ]),
            'installments' => InstallmentResource::collection($this->whenLoaded('installments')),
        ];
    }
}
