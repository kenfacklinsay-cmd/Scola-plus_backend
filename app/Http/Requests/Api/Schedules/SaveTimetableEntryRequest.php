<?php

namespace App\Http\Requests\Api\Schedules;

use App\Models\Assignment;
use App\Models\TimetableEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Valider la création ou la modification d'un créneau hebdomadaire. */
class SaveTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && in_array($this->user()->role, ['DIRECTEUR', 'SECRETARIAT'], true);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer', 'exists:assignments,assignment_id'],
            'day_of_week' => ['required', 'integer', 'between:1,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['assignment_id', 'day_of_week', 'start_time', 'end_time'])) {
                return;
            }

            $assignment = Assignment::query()->find($this->integer('assignment_id'));

            if ($assignment === null) {
                return;
            }

            $schoolYearId = $assignment->schoolClass()->value('school_year_id');
            $current = $this->route('schedule');
            $overlap = TimetableEntry::query()
                ->where('day_of_week', $this->integer('day_of_week'))
                ->where('start_time', '<', $this->input('end_time'))
                ->where('end_time', '>', $this->input('start_time'))
                ->when($current instanceof TimetableEntry, fn ($query) => $query
                    ->where('timetable_entry_id', '!=', $current->getKey()))
                ->whereHas('assignment', fn ($query) => $query
                    ->whereHas('schoolClass', fn ($classQuery) => $classQuery
                        ->where('school_year_id', $schoolYearId))
                    ->where(fn ($assignmentQuery) => $assignmentQuery
                        ->where('teacher_id', $assignment->teacher_id)
                        ->orWhere('class_id', $assignment->class_id)))
                ->exists();

            if ($overlap) {
                $validator->errors()->add(
                    'start_time',
                    'Ce créneau chevauche déjà un cours pour cet enseignant ou cette classe.',
                );
            }
        }];
    }
}
