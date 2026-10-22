<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Classes\SaveSchoolClassRequest;
use App\Http\Resources\Api\SchoolClassResource;
use App\Models\SchoolClass;
use App\Services\SchoolClasses\SaveSchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Rechercher, créer et modifier les classes de l'établissement. */
class SchoolClassController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $classes = SchoolClass::query()
            ->with(['schoolYear', 'titularTeacher.user'])
            ->withCount(['enrollments as enrolled_students_count' => fn ($query) => $query->where('status', 'ACTIVE')])
            ->when($filters['school_year_id'] ?? null, fn ($query, $id) => $query->where('school_year_id', $id))
            ->orderBy('level')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return SchoolClassResource::collection($classes);
    }

    public function store(SaveSchoolClassRequest $request, SaveSchoolClass $classes): JsonResponse
    {
        $schoolClass = $classes->create($request->validated())
            ->load(['schoolYear', 'titularTeacher.user'])
            ->loadCount(['enrollments as enrolled_students_count' => fn ($query) => $query->where('status', 'ACTIVE')]);

        return (new SchoolClassResource($schoolClass))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        SaveSchoolClassRequest $request,
        SchoolClass $schoolClass,
        SaveSchoolClass $classes,
    ): SchoolClassResource {
        return new SchoolClassResource($classes->update($schoolClass, $request->validated()));
    }
}
