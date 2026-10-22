<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Periods\StorePeriodRequest;
use App\Http\Resources\Api\PeriodResource;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Consulter les périodes scolaires et permettre au secrétariat de les créer. */
class PeriodController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'school_year_id' => ['required', 'integer', 'exists:school_years,school_year_id'],
        ]);

        $periods = Period::query()
            ->where('school_year_id', $filters['school_year_id'])
            ->orderBy('start_date')
            ->orderBy('period_id')
            ->get();

        return PeriodResource::collection($periods);
    }

    public function store(StorePeriodRequest $request): JsonResponse
    {
        $period = Period::query()->create($request->validated());

        return (new PeriodResource($period))->response()->setStatusCode(201);
    }
}
