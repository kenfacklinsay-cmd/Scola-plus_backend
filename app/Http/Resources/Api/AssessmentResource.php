<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Présenter une évaluation et le contexte de son affectation. */
class AssessmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->assessment_id,
            'label' => $this->label,
            'assessment_date' => $this->assessment_date?->toDateString(),
            'period' => $this->whenLoaded('period', fn () => [
                'id' => $this->period->period_id,
                'label' => $this->period->label,
            ]),
            'assignment' => $this->whenLoaded('assignment', fn () => [
                'id' => $this->assignment->assignment_id,
                'subject' => [
                    'id' => $this->assignment->subject->subject_id,
                    'name' => $this->assignment->subject->name,
                    'coefficient' => $this->assignment->subject->coefficient,
                ],
                'class' => [
                    'id' => $this->assignment->schoolClass->class_id,
                    'name' => $this->assignment->schoolClass->name,
                ],
            ]),
            'grades_count' => $this->when(isset($this->grades_count), $this->grades_count),
        ];
    }
}
