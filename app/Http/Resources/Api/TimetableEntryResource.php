<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter un créneau d'emploi du temps sans exposer d'autres affectations. */
class TimetableEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->timetable_entry_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'room' => $this->room,
            'assignment' => $this->whenLoaded('assignment', fn () => [
                'id' => $this->assignment->assignment_id,
                'class' => [
                    'id' => $this->assignment->schoolClass->class_id,
                    'name' => $this->assignment->schoolClass->name,
                    'school_year' => $this->assignment->schoolClass->schoolYear->label,
                ],
                'subject' => $this->assignment->subject->name,
                'teacher' => [
                    'id' => $this->assignment->teacher->teacher_id,
                    'firstname' => $this->assignment->teacher->user->firstname,
                    'lastname' => $this->assignment->teacher->user->lastname,
                ],
            ]),
        ];
    }
}
