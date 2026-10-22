<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/** Définir qui peut consulter la photo privée d'un élève. */
class StudentPolicy
{
    public function update(User $user, Student $student): bool
    {
        return $user->aRole('DIRECTEUR') || $user->aRole('SECRETARIAT');
    }

    public function updatePhoto(User $user, Student $student): bool
    {
        return $user->aRole('DIRECTEUR') || $user->aRole('SECRETARIAT');
    }

    public function viewPhoto(User $user, Student $student): bool
    {
        if ($user->aRole('DIRECTEUR') || $user->aRole('SECRETARIAT')) {
            return true;
        }

        if ($user->aRole('PARENT')) {
            return $user->parentAccount()
                ->whereHas('students', fn ($query) => $query->whereKey($student->student_id))
                ->exists();
        }

        if ($user->aRole('ENSEIGNANT')) {
            $teacherId = $user->teacherProfile()->value('teacher_id');

            return $teacherId !== null && $student->enrollments()
                ->where('status', 'ACTIVE')
                ->whereHas('schoolClass.assignments', fn ($query) => $query->where('teacher_id', $teacherId))
                ->exists();
        }

        return false;
    }
}
