<?php

namespace App\Http\Resources\Api\Students;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter une fiche élève et ses relations chargées explicitement. */
class StudentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->student_id,
            'matricule' => $this->matricule,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'birth_date' => $this->birth_date?->toDateString(),
            'birth_place' => $this->birth_place,
            'gender' => $this->gender,
            'address' => $this->address,
            'has_photo' => filled($this->photo),
            'parents' => ParentResource::collection($this->whenLoaded('parents')),
            'enrollments' => EnrollmentResource::collection($this->whenLoaded('enrollments')),
        ];
    }
}
