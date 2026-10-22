<?php

namespace App\Services\SchoolClasses;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Enregistrer une classe en préservant les contraintes d'effectif. */
class SaveSchoolClass
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data): SchoolClass
    {
        return SchoolClass::query()->create($data);
    }

    /** @param  array<string, mixed>  $data */
    public function update(SchoolClass $schoolClass, array $data): SchoolClass
    {
        return DB::transaction(function () use ($schoolClass, $data): SchoolClass {
            $lockedClass = SchoolClass::query()
                ->whereKey($schoolClass->class_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('capacity', $data) && $data['capacity'] !== null) {
                $activeEnrollments = Enrollment::query()
                    ->where('class_id', $lockedClass->class_id)
                    ->where('school_year_id', $lockedClass->school_year_id)
                    ->where('status', 'ACTIVE')
                    ->count();

                if ($data['capacity'] < $activeEnrollments) {
                    throw ValidationException::withMessages([
                        'capacity' => ['La capacité ne peut pas être inférieure à l’effectif déjà inscrit.'],
                    ]);
                }
            }

            $lockedClass->update($data);

            return $lockedClass->load(['schoolYear', 'titularTeacher.user'])
                ->loadCount(['enrollments as enrolled_students_count' => fn ($query) => $query->where('status', 'ACTIVE')]);
        });
    }
}
