<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Assignments\StoreAssignmentRequest;
use App\Http\Requests\Api\Assignments\UpdateAssignmentRequest;
use App\Http\Resources\Api\AssignmentResource;
use App\Models\Assignment;
use App\Services\SchoolAssignments\SaveAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Consulter les affectations et associer matières, classes et enseignants. */
class AssignmentController extends Controller
{
    public function mine(Request $request): AnonymousResourceCollection
    {
        $teacherId = $request->user()->teacherProfile?->teacher_id;

        abort_unless($teacherId !== null, 403);

        return AssignmentResource::collection(
            Assignment::query()
                ->where('teacher_id', $teacherId)
                ->with(['teacher.user', 'schoolClass.schoolYear', 'subject'])
                ->orderBy('class_id')
                ->orderBy('subject_id')
                ->get(),
        );
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'class_id' => ['sometimes', 'integer', 'exists:classes,class_id'],
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $assignments = Assignment::query()
            ->with(['teacher.user', 'schoolClass.schoolYear', 'subject'])
            ->when($filters['class_id'] ?? null, fn ($query, $id) => $query->where('class_id', $id))
            ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query
                ->whereHas('schoolClass', fn ($classQuery) => $classQuery->where('school_year_id', $id)))
            ->orderBy('class_id')
            ->orderBy('subject_id')
            ->paginate(50)
            ->withQueryString();

        return AssignmentResource::collection($assignments);
    }

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $assignment = Assignment::query()->create($request->validated())
            ->load(['teacher.user', 'schoolClass.schoolYear', 'subject']);

        return (new AssignmentResource($assignment))->response()->setStatusCode(201);
    }

    public function update(
        UpdateAssignmentRequest $request,
        Assignment $assignment,
        SaveAssignment $saveAssignment,
    ): AssignmentResource {
        return new AssignmentResource($saveAssignment->update($assignment, $request->validated()));
    }
}
