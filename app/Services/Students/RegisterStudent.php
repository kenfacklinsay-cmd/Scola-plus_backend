<?php

namespace App\Services\Students;

use App\Models\Enrollment;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Créer en une transaction la fiche, l'inscription et le contact familial. */
class RegisterStudent
{
    /** @param  array<string, mixed>  $data */
    public function handle(array $data): Student
    {
        return DB::transaction(function () use ($data): Student {
            $class = SchoolClass::query()
                ->where('class_id', $data['class_id'])
                ->where('school_year_id', $data['school_year_id'])
                ->lockForUpdate()
                ->first();

            if (! $class) {
                throw ValidationException::withMessages([
                    'class_id' => ['Cette classe ne fait pas partie de l’année scolaire choisie.'],
                ]);
            }

            if ($class->capacity !== null) {
                $enrolledCount = Enrollment::query()
                    ->where('class_id', $class->class_id)
                    ->where('school_year_id', $data['school_year_id'])
                    ->where('status', 'ACTIVE')
                    ->count();

                if ($enrolledCount >= $class->capacity) {
                    throw ValidationException::withMessages([
                        'class_id' => ['Cette classe a atteint sa capacité maximale.'],
                    ]);
                }
            }

            $student = Student::query()->create([
                'matricule' => $data['matricule'] ?? 'E'.Str::ulid(),
                ...Arr::only($data, [
                    'last_name',
                    'first_name',
                    'birth_date',
                    'birth_place',
                    'gender',
                    'address',
                ]),
            ]);

            $student->enrollments()->create([
                'class_id' => $class->class_id,
                'school_year_id' => $data['school_year_id'],
                'enrollment_date' => today(),
                'status' => 'ACTIVE',
            ]);

            $parent = isset($data['parent_id'])
                ? ParentAccount::query()->lockForUpdate()->findOrFail($data['parent_id'])
                : ParentAccount::query()->create([
                    'first_name' => $data['parent']['first_name'],
                    'last_name' => $data['parent']['last_name'],
                    'telephone' => $data['parent']['telephone'],
                    'address' => $data['parent']['address'] ?? null,
                ]);

            $student->parents()->attach($parent->parent_id, [
                'relationship' => $data['parent']['relationship'] ?? null,
            ]);

            return $student->load([
                'parents',
                'enrollments.schoolClass',
                'enrollments.schoolYear',
            ]);
        });
    }
}
