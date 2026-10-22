<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter une classe, son année, son titulaire et ses effectifs. */
class SchoolClassResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->class_id,
            'name' => $this->name,
            'level' => $this->level,
            'capacity' => $this->capacity,
            'enrolled_students_count' => $this->when(
                $this->resource->getAttribute('enrolled_students_count') !== null,
                fn () => $this->resource->getAttribute('enrolled_students_count'),
            ),
            'school_year' => $this->whenLoaded('schoolYear', fn () => new SchoolYearResource($this->schoolYear)),
            'titular_teacher' => $this->whenLoaded('titularTeacher', fn () => [
                'id' => $this->titularTeacher?->teacher_id,
                'firstname' => $this->titularTeacher?->user?->firstname,
                'lastname' => $this->titularTeacher?->user?->lastname,
            ]),
        ];
    }
}
