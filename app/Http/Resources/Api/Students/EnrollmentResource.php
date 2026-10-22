<?php

namespace App\Http\Resources\Api\Students;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter l'affectation annuelle de l'élève à une classe. */
class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->enrollment_id,
            'enrollment_date' => $this->enrollment_date?->toDateString(),
            'status' => $this->status,
            'class' => $this->whenLoaded('schoolClass', fn () => [
                'id' => $this->schoolClass->class_id,
                'name' => $this->schoolClass->name,
                'level' => $this->schoolClass->level,
            ]),
            'school_year' => $this->whenLoaded('schoolYear', fn () => [
                'id' => $this->schoolYear->school_year_id,
                'label' => $this->schoolYear->label,
            ]),
        ];
    }
}
