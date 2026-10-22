<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->teacher_id,
            'teacher_registration_code' => $this->teacher_registration_code,
            'firstname' => $this->user->firstname,
            'lastname' => $this->user->lastname,
            'specialty' => $this->specialty,
        ];
    }
}
