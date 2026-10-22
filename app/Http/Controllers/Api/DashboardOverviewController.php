<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DashboardOverviewRequest;
use App\Services\Dashboard\BuildDashboardOverview;
use Illuminate\Http\JsonResponse;

/** Fournir les indicateurs scolaires et financiers à la direction. */
class DashboardOverviewController extends Controller
{
    public function __invoke(
        DashboardOverviewRequest $request,
        BuildDashboardOverview $dashboard,
    ): JsonResponse {
        return response()->json([
            'data' => $dashboard->forPeriod(
                (int) $request->validated('school_year_id'),
                (int) $request->validated('period_id'),
            ),
        ]);
    }
}
