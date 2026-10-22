<?php

namespace App\Http\Controllers\Api\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Students\RegisterStudentRequest;
use App\Http\Requests\Api\Students\TransferStudentEnrollmentRequest;
use App\Http\Requests\Api\Students\UpdateStudentRequest;
use App\Http\Resources\Api\Students\EnrollmentResource;
use App\Http\Resources\Api\Students\StudentResource;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\Students\RegisterStudent;
use App\Services\Students\TransferStudentEnrollment;
use App\Services\Students\UpdateStudent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

/** Exposer la recherche et l'inscription des élèves au secrétariat. */
class StudentController extends Controller
{
    public function teacherStudents(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'class_id' => ['nullable', 'integer', 'exists:classes,class_id'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,school_year_id'],
        ]);
        $teacherId = $request->user()->teacherProfile?->teacher_id;
        abort_unless($teacherId !== null, 403);

        $classIds = Assignment::query()
            ->where('teacher_id', $teacherId)
            ->whereHas('schoolClass', fn ($query) => $query
                ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query->where('school_year_id', $id)))
            ->distinct()
            ->pluck('class_id');

        if (($filters['class_id'] ?? null) && ! $classIds->contains((int) $filters['class_id'])) {
            abort(403, 'Cette classe ne vous est pas attribuée.');
        }

        $classIds = isset($filters['class_id']) ? collect([(int) $filters['class_id']]) : $classIds;
        $students = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->whereIn('class_id', $classIds)
                ->where('status', 'ACTIVE')
                ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query->where('school_year_id', $id)))
            ->with(['enrollments' => fn ($query) => $query
                ->whereIn('class_id', $classIds)
                ->where('status', 'ACTIVE')
                ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query->where('school_year_id', $id))
                ->with(['schoolClass', 'schoolYear'])])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(50)
            ->withQueryString();

        return StudentResource::collection($students);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'class_id' => ['nullable', 'integer', 'exists:classes,class_id'],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $students = Student::query()
            ->with(['parents', 'enrollments.schoolClass', 'enrollments.schoolYear'])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $term = '%'.Str::lower(trim($search)).'%';

                $query->where(function ($query) use ($term): void {
                    $query->whereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(matricule) LIKE ?', [$term]);
                });
            })
            ->when(
                ($filters['class_id'] ?? null) || ($filters['school_year_id'] ?? null),
                function ($query) use ($filters): void {
                    $query->whereHas('enrollments', function ($query) use ($filters): void {
                        $query->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
                            ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query->where('school_year_id', $id));
                    });
                },
            )
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        return StudentResource::collection($students);
    }

    public function store(RegisterStudentRequest $request, RegisterStudent $register): JsonResponse
    {
        $student = $register->handle($request->validated());

        return (new StudentResource($student))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        return new StudentResource($student->load([
            'parents',
            'enrollments.schoolClass',
            'enrollments.schoolYear',
        ]));
    }

    public function update(UpdateStudentRequest $request, Student $student, UpdateStudent $updateStudent): StudentResource
    {
        return new StudentResource($updateStudent->handle($student, $request->validated()));
    }

    public function transferEnrollment(
        TransferStudentEnrollmentRequest $request,
        Student $student,
        Enrollment $enrollment,
        TransferStudentEnrollment $transferEnrollment,
    ): EnrollmentResource {
        abort_unless((int) $enrollment->student_id === (int) $student->student_id, 404);

        return new EnrollmentResource($transferEnrollment->handle(
            $enrollment,
            (int) $request->validated('class_id'),
        ));
    }
}
