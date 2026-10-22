<?php

namespace App\Services\SchoolAssignments;

use App\Models\Assignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Modifier une affectation sans changer l'enseignant d'évaluations déjà saisies. */
class SaveAssignment
{
    /** @param  array{teacher_id: int}  $data */
    public function update(Assignment $assignment, array $data): Assignment
    {
        return DB::transaction(function () use ($assignment, $data): Assignment {
            $lockedAssignment = Assignment::query()
                ->whereKey($assignment->assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $lockedAssignment->teacher_id !== $data['teacher_id']
                && $lockedAssignment->assessments()->exists()
            ) {
                throw ValidationException::withMessages([
                    'teacher_id' => [
                        'Cet enseignant ne peut plus être remplacé, car des évaluations sont déjà liées à cette affectation.',
                    ],
                ]);
            }

            $lockedAssignment->update($data);

            return $lockedAssignment->load(['teacher.user', 'schoolClass.schoolYear', 'subject']);
        });
    }
}
