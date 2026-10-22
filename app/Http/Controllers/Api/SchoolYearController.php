<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SchoolYears\StoreSchoolYearRequest;
use App\Http\Resources\Api\SchoolYearResource;
use App\Models\SchoolYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Fournir les années scolaires aux formulaires et gérer leur création. */
class SchoolYearController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SchoolYearResource::collection(
            SchoolYear::query()->orderByDesc('start_date')->get(),
        );
    }

    public function store(StoreSchoolYearRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] ??= 'UPCOMING';

        $schoolYear = SchoolYear::query()->create($data);

        return (new SchoolYearResource($schoolYear))
            ->response()
            ->setStatusCode(201);
    }
}
