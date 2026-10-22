<?php

namespace App\Services\Students;

use App\Models\Student;

/** Mettre à jour les données administratives d'un élève. */
class UpdateStudent
{
    /** @param array<string, mixed> $data */
    public function handle(Student $student, array $data): Student
    {
        $student->update($data);

        return $student->load(['parents', 'enrollments.schoolClass', 'enrollments.schoolYear']);
    }
}
