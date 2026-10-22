<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstallmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->installment_id,
            'label' => $this->label,
            'amount' => $this->amount,
            'due_date' => $this->due_date?->toDateString(),
        ];
    }
}
