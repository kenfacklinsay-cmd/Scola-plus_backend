<?php

namespace App\Http\Resources\Api\Students;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter les coordonnées d'un parent ou tuteur. */
class ParentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->parent_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'telephone' => $this->telephone,
            'has_account' => $this->user_id !== null,
            'account_email' => $this->whenLoaded('user', fn () => $this->user?->email),
            'relationship' => $this->pivot?->relationship,
            'students' => $this->whenLoaded('students', fn () => $this->students->map(fn ($student) => [
                'id' => $student->student_id,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
            ])),
        ];
    }
}
