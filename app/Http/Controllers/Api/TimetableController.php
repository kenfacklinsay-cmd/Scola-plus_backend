<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Schedules\SaveTimetableEntryRequest;
use App\Http\Resources\Api\TimetableEntryResource;
use App\Models\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Gérer les créneaux hebdomadaires et exposer à chaque enseignant son planning. */
class TimetableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,school_year_id'],
        ]);

        return TimetableEntryResource::collection(
            TimetableEntry::query()
                ->with(['assignment.teacher.user', 'assignment.schoolClass.schoolYear', 'assignment.subject'])
                ->when($filters['school_year_id'] ?? null, fn ($query, $yearId) => $query
                    ->whereHas('assignment.schoolClass', fn ($classQuery) => $classQuery->where('school_year_id', $yearId)))
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(),
        );
    }

    public function mine(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'school_year_id' => ['sometimes', 'integer', 'exists:school_years,school_year_id'],
        ]);
        $teacherId = $request->user()->teacherProfile?->teacher_id;
        abort_unless($teacherId !== null, 403);

        return TimetableEntryResource::collection(
            TimetableEntry::query()
                ->whereHas('assignment', fn ($query) => $query->where('teacher_id', $teacherId))
                ->when($filters['school_year_id'] ?? null, fn ($query, $yearId) => $query
                    ->whereHas('assignment.schoolClass', fn ($classQuery) => $classQuery
                        ->where('school_year_id', $yearId)))
                ->with(['assignment.teacher.user', 'assignment.schoolClass.schoolYear', 'assignment.subject'])
                ->orderBy('day_of_week')
                ->orderBy('start_time')
                ->get(),
        );
    }

    public function store(SaveTimetableEntryRequest $request): JsonResponse
    {
        $entry = TimetableEntry::query()->create($request->validated())
            ->load(['assignment.teacher.user', 'assignment.schoolClass.schoolYear', 'assignment.subject']);

        return (new TimetableEntryResource($entry))->response()->setStatusCode(201);
    }

    public function update(SaveTimetableEntryRequest $request, TimetableEntry $schedule): TimetableEntryResource
    {
        $schedule->update($request->validated());

        return new TimetableEntryResource($schedule->load([
            'assignment.teacher.user',
            'assignment.schoolClass.schoolYear',
            'assignment.subject',
        ]));
    }

    public function destroy(TimetableEntry $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([], 204);
    }
}
