<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter une affectation de matière, de classe et d'enseignant. */
class AssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->assignment_id,
            'teacher' => [
                'id' => $this->teacher->teacher_id,
                'firstname' => $this->teacher->user->firstname,
                'lastname' => $this->teacher->user->lastname,
            ],
            'class' => [
                'id' => $this->schoolClass->class_id,
                'name' => $this->schoolClass->name,
                'level' => $this->schoolClass->level,
                'school_year' => [
                    'id' => $this->schoolClass->schoolYear->school_year_id,
                    'label' => $this->schoolClass->schoolYear->label,
                ],
            ],
            'subject' => [
                'id' => $this->subject->subject_id,
                'name' => $this->subject->name,
                'coefficient' => $this->subject->coefficient,
            ],
        ];
    }
}
