<?php

namespace App\Services\Students;

use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Changer la classe d'une inscription sans altérer les résultats existants. */
class TransferStudentEnrollment
{
    public function handle(Enrollment $enrollment, int $targetClassId): Enrollment
    {
        return DB::transaction(function () use ($enrollment, $targetClassId): Enrollment {
            Student::query()
                ->whereKey($enrollment->student_id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedEnrollment = Enrollment::query()
                ->whereKey($enrollment->enrollment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedEnrollment->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'enrollment' => ['Seule une inscription active peut changer de classe.'],
                ]);
            }

            if ((int) $lockedEnrollment->class_id === $targetClassId) {
                return $lockedEnrollment->load(['student', 'schoolClass', 'schoolYear']);
            }

            $targetClass = SchoolClass::query()
                ->where('class_id', $targetClassId)
                ->where('school_year_id', $lockedEnrollment->school_year_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasGrades = Grade::query()
                ->where('student_id', $lockedEnrollment->student_id)
                ->whereHas('assessment.period', fn ($query) => $query->where(
                    'school_year_id',
                    $lockedEnrollment->school_year_id,
                ))
                ->exists();

            $hasPayments = Payment::query()
                ->where('student_id', $lockedEnrollment->student_id)
                ->whereHas('installment.tuitionFee', fn ($query) => $query->where(
                    'school_year_id',
                    $lockedEnrollment->school_year_id,
                ))
                ->exists();

            if ($hasGrades || $hasPayments) {
                throw ValidationException::withMessages([
                    'class_id' => [
                        'Le changement de classe est bloqué car des notes ou des paiements existent déjà pour cette année scolaire.',
                    ],
                ]);
            }

            if ($targetClass->capacity !== null) {
                $activeCount = Enrollment::query()
                    ->where('class_id', $targetClass->class_id)
                    ->where('school_year_id', $lockedEnrollment->school_year_id)
                    ->where('status', 'ACTIVE')
                    ->count();

                if ($activeCount >= $targetClass->capacity) {
                    throw ValidationException::withMessages([
                        'class_id' => ['Cette classe a atteint sa capacité maximale.'],
                    ]);
                }
            }

            $lockedEnrollment->update(['class_id' => $targetClass->class_id]);

            return $lockedEnrollment->load(['student', 'schoolClass', 'schoolYear']);
        });
    }
}
