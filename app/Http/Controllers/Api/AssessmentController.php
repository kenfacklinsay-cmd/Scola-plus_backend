<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Assessments\StoreAssessmentRequest;
use App\Http\Resources\Api\AssessmentResource;
use App\Models\Assessment;
use App\Models\Assignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Lister et créer les évaluations des classes d'un enseignant. */
class AssessmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:assignments,assignment_id'],
            'period_id' => ['required', 'integer', 'exists:periods,period_id'],
        ]);
        $teacherId = $request->user()->teacherProfile?->teacher_id;

        abort_unless(
            $teacherId !== null && Assignment::query()
                ->whereKey($filters['assignment_id'])
                ->where('teacher_id', $teacherId)
                ->exists(),
            403,
        );

        $assessments = Assessment::query()
            ->where('assignment_id', $filters['assignment_id'])
            ->where('period_id', $filters['period_id'])
            ->with(['assignment.subject', 'assignment.schoolClass', 'period'])
            ->withCount('grades')
            ->orderBy('assessment_date')
            ->orderBy('assessment_id')
            ->get();

        return AssessmentResource::collection($assessments);
    }

    public function store(StoreAssessmentRequest $request): JsonResponse
    {
        $assessment = Assessment::query()->create($request->validated())
            ->load(['assignment.subject', 'assignment.schoolClass', 'period'])
            ->loadCount('grades');

        return (new AssessmentResource($assessment))->response()->setStatusCode(201);
    }
}
