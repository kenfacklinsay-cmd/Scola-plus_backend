<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Subjects\SaveSubjectRequest;
use App\Http\Resources\Api\SubjectResource;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Consulter et administrer le catalogue des matières. */
class SubjectController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SubjectResource::collection(Subject::query()->orderBy('name')->paginate(50));
    }

    public function store(SaveSubjectRequest $request): JsonResponse
    {
        $subject = Subject::query()->create($request->validated());

        return (new SubjectResource($subject))->response()->setStatusCode(201);
    }

    public function update(SaveSubjectRequest $request, Subject $subject): SubjectResource
    {
        $subject->update($request->validated());

        return new SubjectResource($subject->refresh());
    }
}
